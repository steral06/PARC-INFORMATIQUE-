<?php

namespace App\Service;

use App\Repository\PersonnelRepository;

class MatriculeGenerator
{
    public function __construct(
        private readonly PersonnelRepository $personnelRepository,
    ) {}

    /**
     * Génère un code de préfixe court et clair pour un nom de service donné
     * Exemples:
     * - "PRODUCTION" -> "PROD"
     * - "SANTE" -> "SANTE"
     * - "Commerciale" -> "COMM"
     * - "SINISTRE" -> "SIN"
     * - "REMISE DE CHECQUES" -> "RDC"
     * - "DSI / Informatique" -> "DSI"
     * - "DIRECTION GENERALE" -> "DIR"
     * - "FINANCE / COMPTABILITE" -> "FIN"
     * - "RESSOURCES HUMAINES" -> "RH"
     * - Service vide / null -> "PERS"
     */
    public function getServicePrefix(?string $service): string
    {
        if ($service === null || trim($service) === '') {
            return 'PERS';
        }

        $clean = strtoupper(trim($service));
        // Remplacement des caractères accentués
        $clean = strtr($clean, [
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ý' => 'Y', 'Ç' => 'C',
        ]);

        // Mappings standards
        if (str_contains($clean, 'PROD')) {
            return 'PROD';
        }
        if (str_contains($clean, 'SANTE')) {
            return 'SANTE';
        }
        if (str_contains($clean, 'COMM')) {
            return 'COMM';
        }
        if (str_contains($clean, 'SINISTRE')) {
            return 'SIN';
        }
        if (str_contains($clean, 'CHECQUE') || str_contains($clean, 'CHEQUE') || str_contains($clean, 'REMISE')) {
            return 'RDC';
        }
        if (str_contains($clean, 'INFO') || str_contains($clean, 'DSI')) {
            return 'DSI';
        }
        if (str_contains($clean, 'DIR')) {
            return 'DIR';
        }
        if (str_contains($clean, 'FIN') || str_contains($clean, 'COMPTA')) {
            return 'FIN';
        }
        if (str_contains($clean, 'RH') || str_contains($clean, 'HUMAINE')) {
            return 'RH';
        }
        if (str_contains($clean, 'LOGISTIQUE') || str_contains($clean, 'MOYEN')) {
            return 'LOG';
        }
        if (str_contains($clean, 'JURIDIQUE') || str_contains($clean, 'CONTENTIEUX')) {
            return 'JUR';
        }

        // Si service en plusieurs mots, prendre les initiales (ex: "RECOUVREMENT AMIABLE" -> "RA")
        $words = preg_split('/[\s\-_]+/', $clean);
        if (count($words) > 1 && strlen($words[0]) > 0 && strlen($words[1]) > 0) {
            $initials = '';
            foreach ($words as $w) {
                if ($w !== '' && !in_array($w, ['DE', 'DU', 'ET', 'LA', 'LE', 'DES', 'D', 'L'])) {
                    $initials .= $w[0];
                }
            }
            if (strlen($initials) >= 2) {
                return substr($initials, 0, 4);
            }
        }

        // Sinon prendre les 4 premières lettres alphanumériques
        $alpha = preg_replace('/[^A-Z]/', '', $clean);
        if (strlen($alpha) >= 3) {
            return substr($alpha, 0, 4);
        }

        return 'PERS';
    }

    /**
     * Génère un matricule unique complet composé du préfixe service et d'un numéro à 4 chiffres
     * Exemple: PROD-0058, SANTE-0012, PERS-0001
     */
    public function generateMatricule(?string $service, ?int $forceNumber = null): string
    {
        $prefix = $this->getServicePrefix($service);
        $number = $forceNumber ?? $this->personnelRepository->getNextMatriculeNumberForPrefix($prefix);

        return sprintf('%s-%04d', $prefix, $number);
    }
}

