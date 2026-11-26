import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['status'];
    static values  = {
        registerUrl:      String,
        verifyUrl:        String,
        loginOptionsUrl:  String,
        loginVerifyUrl:   String,
    };

    // ── Helpers ──────────────────────────────────────────────────────────────

    b64uDecode(str) {
        str = str.replace(/-/g, '+').replace(/_/g, '/');
        while (str.length % 4) str += '=';
        const bin = atob(str);
        return Uint8Array.from(bin, c => c.charCodeAt(0));
    }

    b64uEncode(buf) {
        const bytes = new Uint8Array(buf);
        let str = '';
        bytes.forEach(b => str += String.fromCharCode(b));
        return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
    }

    setStatus(msg, type = 'info') {
        if (!this.hasStatusTarget) return;
        const colors = { info: '#6b7280', success: '#10b981', error: '#ef4444', loading: '#E47000' };
        this.statusTarget.textContent = msg;
        this.statusTarget.style.color = colors[type] || colors.info;
        this.statusTarget.style.display = msg ? 'block' : 'none';
    }

    // ── Registration ─────────────────────────────────────────────────────────

    async register() {
        if (!window.PublicKeyCredential) {
            this.setStatus('❌ Votre navigateur ne supporte pas WebAuthn.', 'error');
            return;
        }
        this.setStatus('⏳ Génération du challenge...', 'loading');

        try {
            const optRes = await fetch(this.registerUrlValue);
            if (!optRes.ok) throw new Error('Erreur serveur lors de la génération des options.');
            const options = await optRes.json();

            const publicKey = {
                ...options,
                challenge: this.b64uDecode(options.challenge),
                user: {
                    ...options.user,
                    id: this.b64uDecode(options.user.id),
                },
                excludeCredentials: (options.excludeCredentials || []).map(c => ({
                    ...c,
                    id: this.b64uDecode(c.id),
                })),
            };

            this.setStatus('🔐 Placez votre doigt sur le capteur...', 'loading');

            const credential = await navigator.credentials.create({ publicKey });
            if (!credential) throw new Error('Opération annulée.');

            const payload = {
                id:                credential.id,
                rawId:             this.b64uEncode(credential.rawId),
                type:              credential.type,
                clientDataJSON:    this.b64uEncode(credential.response.clientDataJSON),
                attestationObject: this.b64uEncode(credential.response.attestationObject),
            };

            this.setStatus('✅ Empreinte capturée. Enregistrement en cours...', 'loading');

            const verifyRes = await fetch(this.verifyUrlValue, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify(payload),
            });
            const verifyData = await verifyRes.json();

            if (!verifyData.success) {
                this.setStatus('❌ ' + (verifyData.error || 'Échec de l\'enregistrement.'), 'error');
                return;
            }

            this.setStatus('✅ Empreinte enregistrée ! (' + verifyData.deviceName + ')', 'success');
            setTimeout(() => window.location.reload(), 1500);

        } catch (err) {
            if (err.name === 'NotAllowedError') {
                this.setStatus('⚠️ Opération annulée ou refusée par l\'utilisateur.', 'error');
            } else {
                this.setStatus('❌ Erreur : ' + err.message, 'error');
            }
        }
    }

    // ── Delete credential ────────────────────────────────────────────────────

    async deleteCredential(event) {
        const btn = event.currentTarget;
        const id  = btn.dataset.credentialId;
        if (!confirm('Supprimer cet appareil biométrique ?')) return;

        btn.disabled = true;
        const res  = await fetch(`/webauthn/credentials/${id}`, { method: 'DELETE' });
        const data = await res.json();

        if (data.success) {
            btn.closest('[data-credential-item]')?.remove();
        } else {
            alert('Erreur : ' + (data.error || 'Impossible de supprimer.'));
            btn.disabled = false;
        }
    }

    // ── Login (triggered from login page) ────────────────────────────────────

    async loginCheck(username) {
        if (!username || !window.PublicKeyCredential) return;

        try {
            const res  = await fetch(`${this.loginOptionsUrlValue}?username=${encodeURIComponent(username)}`);
            const data = await res.json();

            if (!data.available) return;

            const btn = document.getElementById('webauthn-login-btn');
            if (btn) btn.classList.remove('d-none');

            this._loginOptions = data;
        } catch {
            // Silently fail — biometric is optional
        }
    }

    async loginWithBiometric() {
        const username = document.getElementById('username')?.value?.trim();
        if (!username) {
            this.setStatus('Veuillez saisir votre identifiant (ex: superadmin) d\'abord.', 'error');
            document.getElementById('username')?.focus();
            return;
        }

        this.setStatus('⏳ Vérification du compte...', 'loading');

        const optionsUrl = this.hasLoginOptionsUrlValue
            ? this.loginOptionsUrlValue
            : (this.element.dataset.webauthnLoginOptionsUrlValue || '/webauthn/login/options');

        try {
            const res = await fetch(`${optionsUrl}?username=${encodeURIComponent(username)}`);
            if (!res.ok) {
                const text = await res.text();
                throw new Error(`Erreur HTTP ${res.status}: ${text}`);
            }
            const data = await res.json();

            if (!data.available) {
                this.setStatus('⚠️ Aucun appareil biométrique enregistré pour cet utilisateur.', 'error');
                return;
            }

            this._loginOptions = data;
        } catch (e) {
            console.error('WebAuthn login error:', e);
            this.setStatus('❌ Erreur : ' + (e.message || 'Impossible de joindre le serveur.'), 'error');
            return;
        }

        this.setStatus('🔐 Placez votre doigt sur le capteur...', 'loading');

        try {
            const options   = this._loginOptions;
            const publicKey = {
                challenge:        this.b64uDecode(options.challenge),
                timeout:          options.timeout,
                rpId:             options.rpId,
                allowCredentials: (options.allowCredentials || []).map(c => ({
                    ...c,
                    id: this.b64uDecode(c.id),
                })),
                userVerification: options.userVerification || 'required',
            };

            const credential = await navigator.credentials.get({ publicKey });
            if (!credential) throw new Error('Opération annulée.');

            const payload = {
                id:                credential.id,
                rawId:             this.b64uEncode(credential.rawId),
                type:              credential.type,
                clientDataJSON:    this.b64uEncode(credential.response.clientDataJSON),
                authenticatorData: this.b64uEncode(credential.response.authenticatorData),
                signature:         this.b64uEncode(credential.response.signature),
            };

            this.setStatus('✅ Empreinte reconnue. Connexion en cours...', 'loading');

            const verifyUrl = this.hasLoginVerifyUrlValue
                ? this.loginVerifyUrlValue
                : (this.element.dataset.webauthnLoginVerifyUrlValue || '/webauthn/login/verify');

            const verifyRes = await fetch(verifyUrl, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify(payload),
            });
            const data = await verifyRes.json();

            if (!data.success) {
                this.setStatus('❌ ' + (data.error || 'Authentification échouée.'), 'error');
                return;
            }

            window.location.href = data.redirect;

        } catch (err) {
            if (err.name === 'NotAllowedError') {
                this.setStatus('⚠️ Opération annulée.', 'error');
            } else {
                this.setStatus('❌ ' + err.message, 'error');
            }
        }
    }
}

