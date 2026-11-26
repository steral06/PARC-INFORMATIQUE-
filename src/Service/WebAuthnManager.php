<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * WebAuthn biometric authentication manager.
 *
 * Implements FIDO2 / WebAuthn Level 2 registration and authentication flows
 * using only PHP built-ins (openssl, hash) — no external CBOR library required.
 * The inner CborDecoder handles the subset of CBOR used by WebAuthn attestation
 * objects and COSE public keys.
 */
class WebAuthnManager
{
    // ── Relying Party settings ────────────────────────────────────────────────
    private const RP_ID   = 'localhost';
    private const RP_NAME = 'G-PARC GTAC2A-IARDT';
    private const TIMEOUT = 60000; // 60 s

    /** Acceptable origins. Extend when behind a reverse-proxy with a real domain. */
    private const ALLOWED_ORIGINS = [
        'http://localhost',
        'https://localhost',
        'http://localhost:8000',
        'https://localhost:8000',
        'http://127.0.0.1',
        'http://127.0.0.1:8000',
    ];

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {}

    // ── Public helpers ────────────────────────────────────────────────────────

    public function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function base64urlDecode(string $data): string
    {
        $pad = strlen($data) % 4;
        if ($pad) {
            $data .= str_repeat('=', 4 - $pad);
        }
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        if ($decoded === false) {
            throw new \RuntimeException('Invalid base64url string.');
        }
        return $decoded;
    }

    // ── Registration ──────────────────────────────────────────────────────────

    /**
     * Generate PublicKeyCredentialCreationOptions and store the challenge in session.
     */
    public function generateRegistrationOptions(string $userId, string $username): array
    {
        $challenge = random_bytes(32);
        $session   = $this->requestStack->getSession();
        $session->set('webauthn_challenge', base64_encode($challenge));
        $session->set('webauthn_challenge_expires', time() + 300);

        return [
            'rp'  => ['id' => self::RP_ID, 'name' => self::RP_NAME],
            'user' => [
                'id'          => $this->base64urlEncode($userId),
                'name'        => $username,
                'displayName' => $username,
            ],
            'challenge'        => $this->base64urlEncode($challenge),
            'pubKeyCredParams' => [
                ['alg' => -7,   'type' => 'public-key'], // ES256
                ['alg' => -257, 'type' => 'public-key'], // RS256
            ],
            'timeout'            => self::TIMEOUT,
            'attestation'        => 'none',
            'authenticatorSelection' => [
                'userVerification'   => 'required',
                'residentKey'        => 'preferred',
                'requireResidentKey' => false,
            ],
            'excludeCredentials' => [],
        ];
    }

    /**
     * Verify a registration response and return credential data.
     *
     * @return array{credentialId: string, publicKey: string, algorithm: int, signCount: int}
     * @throws \RuntimeException on failure
     */
    public function verifyRegistration(array $response): array
    {
        $session         = $this->requestStack->getSession();
        $storedChallenge = $session->get('webauthn_challenge');
        $expires         = (int) $session->get('webauthn_challenge_expires', 0);
        $session->remove('webauthn_challenge');
        $session->remove('webauthn_challenge_expires');

        if (!$storedChallenge || time() > $expires) {
            throw new \RuntimeException('Challenge expiré ou manquant.');
        }

        // 1. Decode clientDataJSON
        $clientDataJson = $this->base64urlDecode($response['clientDataJSON'] ?? '');
        $clientData     = json_decode($clientDataJson, true);

        if (!is_array($clientData) || ($clientData['type'] ?? '') !== 'webauthn.create') {
            throw new \RuntimeException('Type de clientData invalide.');
        }

        // 2. Verify challenge
        $receivedChallenge = $this->base64urlDecode($clientData['challenge'] ?? '');
        if (!hash_equals(base64_decode($storedChallenge), $receivedChallenge)) {
            throw new \RuntimeException('Challenge ne correspond pas.');
        }

        // 3. Verify origin
        if (!in_array($clientData['origin'] ?? '', self::ALLOWED_ORIGINS, true)) {
            throw new \RuntimeException('Origine non autorisée : ' . ($clientData['origin'] ?? '(vide)'));
        }

        // 4. Decode attestationObject (CBOR → PHP array)
        $attestationRaw = $this->base64urlDecode($response['attestationObject'] ?? '');
        $attObj         = CborDecoder::decode($attestationRaw);

        // 5. Parse authenticatorData
        $authDataRaw = $attObj['authData'] ?? '';
        if (!is_string($authDataRaw)) {
            throw new \RuntimeException('authData manquant dans l\'attestationObject.');
        }
        $parsed = $this->parseAuthenticatorData($authDataRaw);

        // 6. Verify rpIdHash
        $expectedRpIdHash = hash('sha256', self::RP_ID, true);
        if (!hash_equals($expectedRpIdHash, $parsed['rpIdHash'])) {
            throw new \RuntimeException('rpIdHash invalide.');
        }

        // 7. Check flags: UP (bit 0) and UV (bit 2) and AT (bit 6)
        $flags = ord($parsed['flags']);
        if (!($flags & 0x01)) { throw new \RuntimeException('User Presence non confirmé.'); }
        if (!($flags & 0x04)) { throw new \RuntimeException('User Verification requis mais absent.'); }
        if (!($flags & 0x40)) { throw new \RuntimeException('Aucune clé publique dans la réponse (AT flag absent).'); }

        // 8. Extract credentialId and public key
        $credentialId = $this->base64urlEncode($parsed['credentialId']);
        $publicKey    = $this->extractPublicKeyFromCose($parsed['publicKeyCbor']);

        return [
            'credentialId' => $credentialId,
            'publicKey'    => $publicKey['pem'],
            'algorithm'    => $publicKey['algorithm'],
            'signCount'    => $parsed['signCount'],
        ];
    }

    // ── Authentication ────────────────────────────────────────────────────────

    /**
     * Generate PublicKeyCredentialRequestOptions and store the challenge in session.
     *
     * @param \App\Entity\WebAuthnCredential[] $credentials
     */
    public function generateAuthenticationOptions(array $credentials): array
    {
        $challenge = random_bytes(32);
        $session   = $this->requestStack->getSession();
        $session->set('webauthn_challenge', base64_encode($challenge));
        $session->set('webauthn_challenge_expires', time() + 300);

        $allowCredentials = array_map(fn ($c) => [
            'id'         => $c->getCredentialId(),
            'type'       => 'public-key',
            'transports' => ['internal'],
        ], $credentials);

        return [
            'challenge'        => $this->base64urlEncode($challenge),
            'timeout'          => self::TIMEOUT,
            'rpId'             => self::RP_ID,
            'allowCredentials' => $allowCredentials,
            'userVerification' => 'required',
        ];
    }

    /**
     * Verify an authentication response.
     *
     * @return int The new signCount (caller must persist it)
     * @throws \RuntimeException on failure
     */
    public function verifyAuthentication(array $response, string $publicKeyPem, int $storedSignCount): int
    {
        $session         = $this->requestStack->getSession();
        $storedChallenge = $session->get('webauthn_challenge');
        $expires         = (int) $session->get('webauthn_challenge_expires', 0);
        $session->remove('webauthn_challenge');
        $session->remove('webauthn_challenge_expires');

        if (!$storedChallenge || time() > $expires) {
            throw new \RuntimeException('Challenge expiré ou manquant.');
        }

        // 1. Decode clientDataJSON
        $clientDataJson = $this->base64urlDecode($response['clientDataJSON'] ?? '');
        $clientData     = json_decode($clientDataJson, true);

        if (!is_array($clientData) || ($clientData['type'] ?? '') !== 'webauthn.get') {
            throw new \RuntimeException('Type de clientData invalide.');
        }

        // 2. Verify challenge
        $receivedChallenge = $this->base64urlDecode($clientData['challenge'] ?? '');
        if (!hash_equals(base64_decode($storedChallenge), $receivedChallenge)) {
            throw new \RuntimeException('Challenge ne correspond pas.');
        }

        // 3. Verify origin
        if (!in_array($clientData['origin'] ?? '', self::ALLOWED_ORIGINS, true)) {
            throw new \RuntimeException('Origine non autorisée : ' . ($clientData['origin'] ?? '(vide)'));
        }

        // 4. Decode authenticatorData (raw bytes — not CBOR-wrapped here)
        $authData = $this->base64urlDecode($response['authenticatorData'] ?? '');
        $parsed   = $this->parseAuthenticatorData($authData);

        // 5. Verify rpIdHash
        $expectedRpIdHash = hash('sha256', self::RP_ID, true);
        if (!hash_equals($expectedRpIdHash, $parsed['rpIdHash'])) {
            throw new \RuntimeException('rpIdHash invalide.');
        }

        // 6. Check flags
        $flags = ord($parsed['flags']);
        if (!($flags & 0x01)) { throw new \RuntimeException('User Presence non confirmé.'); }
        if (!($flags & 0x04)) { throw new \RuntimeException('User Verification requis mais absent.'); }

        // 7. Verify signature
        $clientDataHash = hash('sha256', $clientDataJson, true);
        $signedData     = $authData . $clientDataHash;
        $signatureBytes = $this->base64urlDecode($response['signature'] ?? '');

        $pubKey = openssl_pkey_get_public($publicKeyPem);
        if (!$pubKey) {
            throw new \RuntimeException('Clé publique invalide : ' . openssl_error_string());
        }

        $result = openssl_verify($signedData, $signatureBytes, $pubKey, OPENSSL_ALGO_SHA256);
        if ($result !== 1) {
            throw new \RuntimeException('Signature invalide. Authentification rejetée.');
        }

        // 8. Verify signCount (anti-cloning)
        $newSignCount = $parsed['signCount'];
        if ($storedSignCount > 0 && $newSignCount <= $storedSignCount) {
            throw new \RuntimeException('SignCount invalide — clonage de clé potentiellement détecté.');
        }

        return $newSignCount;
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    /**
     * Parse the binary authenticatorData structure (§6.1 WebAuthn spec).
     *
     * @return array{rpIdHash: string, flags: string, signCount: int, credentialId: string, publicKeyCbor: string}
     */
    private function parseAuthenticatorData(string $authData): array
    {
        if (strlen($authData) < 37) {
            throw new \RuntimeException('AuthenticatorData trop court (' . strlen($authData) . ' octets).');
        }

        $offset    = 0;
        $rpIdHash  = substr($authData, $offset, 32); $offset += 32;
        $flags     = substr($authData, $offset, 1);  $offset += 1;
        $signCount = unpack('N', substr($authData, $offset, 4))[1]; $offset += 4;

        $credentialId  = '';
        $publicKeyCbor = '';
        $flagByte      = ord($flags);

        if ($flagByte & 0x40) { // AT flag: attestedCredentialData present
            // Skip AAGUID (16 bytes)
            $offset += 16;

            // Credential ID length (2 bytes, big-endian)
            $credIdLen    = unpack('n', substr($authData, $offset, 2))[1]; $offset += 2;
            $credentialId = substr($authData, $offset, $credIdLen);        $offset += $credIdLen;

            // Remaining bytes = COSE public key
            $publicKeyCbor = substr($authData, $offset);
        }

        return [
            'rpIdHash'     => $rpIdHash,
            'flags'        => $flags,
            'signCount'    => $signCount,
            'credentialId' => $credentialId,
            'publicKeyCbor' => $publicKeyCbor,
        ];
    }

    /**
     * Decode a COSE_Key CBOR map and return a PEM public key.
     *
     * Supports EC P-256 (kty=2, alg=-7) and RSA-2048 (kty=3, alg=-257).
     *
     * @return array{pem: string, algorithm: int}
     */
    private function extractPublicKeyFromCose(string $coseData): array
    {
        $cose = CborDecoder::decode($coseData);

        // kty: 1=OKP, 2=EC2, 3=RSA
        $kty = (int) ($cose[1] ?? 0);
        $alg = (int) ($cose[3] ?? -7);

        if ($kty === 2) {
            // EC P-256 key: x=cose[-2], y=cose[-3]
            $x = (string) ($cose[-2] ?? '');
            $y = (string) ($cose[-3] ?? '');

            if (strlen($x) !== 32 || strlen($y) !== 32) {
                throw new \RuntimeException(
                    sprintf('Coordonnées EC P-256 invalides (x=%d oct, y=%d oct).', strlen($x), strlen($y))
                );
            }

            $keyPoint  = "\x04" . $x . $y; // uncompressed point
            $oidSeq    = hex2bin('301306072a8648ce3d020106082a8648ce3d030107'); // EC + P-256 OIDs
            $bitString = "\x03" . $this->derLen(strlen($keyPoint) + 1) . "\x00" . $keyPoint;
            $spki      = "\x30" . $this->derLen(strlen($oidSeq) + strlen($bitString)) . $oidSeq . $bitString;

            $pem = "-----BEGIN PUBLIC KEY-----\n"
                 . chunk_split(base64_encode($spki), 64, "\n")
                 . "-----END PUBLIC KEY-----\n";

            return ['pem' => $pem, 'algorithm' => -7];
        }

        if ($kty === 3) {
            // RSA key: n=cose[-1], e=cose[-2]
            $n = (string) ($cose[-1] ?? '');
            $e = (string) ($cose[-2] ?? '');

            if ($n === '' || $e === '') {
                throw new \RuntimeException('Paramètres RSA (n, e) manquants.');
            }

            // Ensure modulus has no leading sign bit ambiguity
            if (ord($n[0]) & 0x80) { $n = "\x00" . $n; }
            if (ord($e[0]) & 0x80) { $e = "\x00" . $e; }

            $modDer    = "\x02" . $this->derLen(strlen($n)) . $n;
            $expDer    = "\x02" . $this->derLen(strlen($e)) . $e;
            $seqDer    = "\x30" . $this->derLen(strlen($modDer) + strlen($expDer)) . $modDer . $expDer;
            $oidSeq    = hex2bin('300d06092a864886f70d0101010500'); // rsaEncryption OID
            $bitString = "\x03" . $this->derLen(strlen($seqDer) + 1) . "\x00" . $seqDer;
            $spki      = "\x30" . $this->derLen(strlen($oidSeq) + strlen($bitString)) . $oidSeq . $bitString;

            $pem = "-----BEGIN PUBLIC KEY-----\n"
                 . chunk_split(base64_encode($spki), 64, "\n")
                 . "-----END PUBLIC KEY-----\n";

            return ['pem' => $pem, 'algorithm' => -257];
        }

        throw new \RuntimeException('Type de clé COSE non supporté (kty=' . $kty . ').');
    }

    /** Encode a DER length field (supports up to 65535 bytes). */
    private function derLen(int $len): string
    {
        if ($len < 128) {
            return chr($len);
        }
        if ($len < 256) {
            return chr(0x81) . chr($len);
        }
        return chr(0x82) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF);
    }
}

/**
 * Minimal pure-PHP CBOR decoder covering the subset needed by WebAuthn:
 *   - Major types 0 (uint), 1 (nint), 2 (bstr), 3 (tstr), 4 (array), 5 (map)
 *   - Additional info for 1-byte, 2-byte, 4-byte, 8-byte argument lengths
 *   - Tags (type 6) are ignored (inner value decoded)
 *   - Simple / float values (type 7) are decoded to null / bool / float
 *
 * @internal
 */
final class CborDecoder
{
    private string $data;
    private int    $pos;

    private function __construct(string $data)
    {
        $this->data = $data;
        $this->pos  = 0;
    }

    /**
     * Decode the first CBOR item in $data and return a PHP value.
     * Maps are returned as PHP arrays keyed by their CBOR key (int or string).
     * Byte strings are returned as raw PHP strings.
     */
    public static function decode(string $data): mixed
    {
        $decoder = new self($data);
        return $decoder->decodeItem();
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function readByte(): int
    {
        if ($this->pos >= strlen($this->data)) {
            throw new \RuntimeException('CBOR: unexpected end of data at offset ' . $this->pos);
        }
        return ord($this->data[$this->pos++]);
    }

    private function readBytes(int $n): string
    {
        if ($this->pos + $n > strlen($this->data)) {
            throw new \RuntimeException('CBOR: not enough bytes at offset ' . $this->pos . ' (need ' . $n . ')');
        }
        $slice      = substr($this->data, $this->pos, $n);
        $this->pos += $n;
        return $slice;
    }

    private function decodeArgument(int $info): int
    {
        return match (true) {
            $info <= 23  => $info,
            $info === 24 => $this->readByte(),
            $info === 25 => unpack('n', $this->readBytes(2))[1],
            $info === 26 => unpack('N', $this->readBytes(4))[1],
            $info === 27 => (function () {
                // 64-bit: pack as two 32-bit unsigned integers
                ['h' => $hi, 'l' => $lo] = unpack('Nh/Nl', $this->readBytes(8));
                // PHP integers are 64-bit on modern systems
                return ($hi << 32) | $lo;
            })(),
            default => throw new \RuntimeException('CBOR: unsupported additional info ' . $info),
        };
    }

    private function decodeItem(): mixed
    {
        $initial   = $this->readByte();
        $majorType = ($initial >> 5) & 0x07;
        $info      = $initial & 0x1F;

        return match ($majorType) {
            0 => $this->decodeArgument($info),                            // uint
            1 => -1 - $this->decodeArgument($info),                      // nint
            2 => $this->readBytes($this->decodeArgument($info)),          // bstr
            3 => $this->readBytes($this->decodeArgument($info)),          // tstr (UTF-8)
            4 => $this->decodeArray($this->decodeArgument($info)),        // array
            5 => $this->decodeMap($this->decodeArgument($info)),          // map
            6 => (function () { $this->decodeArgument($info); return $this->decodeItem(); })(), // tag: skip, decode inner
            7 => $this->decodeSimple($info),                              // float / simple
            default => throw new \RuntimeException('CBOR: invalid major type ' . $majorType),
        };
    }

    private function decodeArray(int $count): array
    {
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $result[] = $this->decodeItem();
        }
        return $result;
    }

    private function decodeMap(int $count): array
    {
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $key          = $this->decodeItem();
            $result[$key] = $this->decodeItem();
        }
        return $result;
    }

    private function decodeSimple(int $info): mixed
    {
        return match ($info) {
            20      => false,
            21      => true,
            22      => null,
            23      => null,             // undefined → null
            25      => (function () {    // float16
                $bytes = $this->readBytes(2);
                $val   = unpack('n', $bytes)[1];
                $exp   = ($val >> 10) & 0x1F;
                $mant  = $val & 0x03FF;
                $sign  = ($val >> 15) ? -1 : 1;
                if ($exp === 0) { return $sign * (2 ** -14) * ($mant / 1024); }
                if ($exp === 31) { return $sign * INF; }
                return $sign * (2 ** ($exp - 15)) * (1 + $mant / 1024);
            })(),
            26      => unpack('G', $this->readBytes(4))[1],   // float32
            27      => unpack('E', $this->readBytes(8))[1],   // float64
            default => null,
        };
    }
}
