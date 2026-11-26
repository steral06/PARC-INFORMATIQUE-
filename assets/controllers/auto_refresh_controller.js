import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        interval: { type: Number, default: 5000 },
        active: { type: Boolean, default: true }
    };

    connect() {
        if (this.activeValue) {
            this.start();
        }
    }

    disconnect() {
        this.stop();
    }

    start() {
        this.stop();
        this.timer = setInterval(() => {
            // Ne pas rafraîchir si une modale est ouverte
            if (document.querySelector('.modal.show')) {
                return;
            }
            if (window.Turbo) {
                window.Turbo.visit(window.location.href, { action: 'replace' });
            }
        }, this.intervalValue);
    }

    stop() {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
    }
}
