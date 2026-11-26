import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        pingUrl: { type: String, default: '/auth/ping' },
        interval: { type: Number, default: 3000 }
    };

    connect() {
        // Envoi du premier ping immédiatement
        this.checkStatus();

        // Envoi périodique toutes les 3 secondes pour rafraîchir le statut en ligne et détecter une restriction
        this.timer = setInterval(() => {
            this.checkStatus();
        }, this.intervalValue);
    }

    disconnect() {
        if (this.timer) {
            clearInterval(this.timer);
        }
    }

    async checkStatus() {
        try {
            const response = await fetch(this.pingUrlValue, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            // Si le Superadmin a restreint cette machine, déconnexion immédiate sans rechargement
            if (data.blocked === true) {
                if (this.timer) {
                    clearInterval(this.timer);
                }
                window.location.href = data.redirect || '/login';
            }
        } catch (err) {
            // Erreur réseau temporaire, on ne coupe pas brutalement
        }
    }
}
