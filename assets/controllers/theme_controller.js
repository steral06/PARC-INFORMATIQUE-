import { Controller } from '@hotwired/stimulus';

/*
 * Contrôleur de Thème (Mode Clair / Mode Sombre) pour GTAC2A-IARDT G-Parc
 * - Compatible avec Bootstrap 5.3 data-bs-theme="light"|"dark"
 * - Persistance dans localStorage ('gparc-theme')
 * - Prise en charge des préférences système (prefers-color-scheme)
 * - Mise à jour dynamique des icônes et étiquettes
 */
export default class extends Controller {
    static targets = ['toggleBtn', 'label'];

    connect() {
        this.applyTheme(this.getStoredTheme());

        // Écoute des préférences système si non défini manuellement
        this.mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        this.systemThemeListener = (e) => {
            if (!localStorage.getItem('gparc-theme')) {
                this.applyTheme(e.matches ? 'dark' : 'light');
            }
        };
        this.mediaQuery.addEventListener('change', this.systemThemeListener);
    }

    disconnect() {
        if (this.mediaQuery && this.systemThemeListener) {
            this.mediaQuery.removeEventListener('change', this.systemThemeListener);
        }
    }

    getStoredTheme() {
        const stored = localStorage.getItem('gparc-theme');
        if (stored === 'light' || stored === 'dark') {
            return stored;
        }
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    toggle() {
        const current = document.documentElement.getAttribute('data-bs-theme') || 'light';
        const next = current === 'dark' ? 'light' : 'dark';
        localStorage.setItem('gparc-theme', next);
        this.applyTheme(next);
        this.showToast(next);
    }

    setLight() {
        localStorage.setItem('gparc-theme', 'light');
        this.applyTheme('light');
        this.showToast('light');
    }

    setDark() {
        localStorage.setItem('gparc-theme', 'dark');
        this.applyTheme('dark');
        this.showToast('dark');
    }

    applyTheme(theme) {
        document.documentElement.setAttribute('data-bs-theme', theme);

        const isDark = theme === 'dark';

        // Mise à jour de toutes les icônes soleils / lunes sur la page
        document.querySelectorAll('.theme-icon-sun').forEach(el => {
            el.classList.toggle('d-none', !isDark);
        });
        document.querySelectorAll('.theme-icon-moon').forEach(el => {
            el.classList.toggle('d-none', isDark);
        });

        // Mise à jour des labels éventuels
        document.querySelectorAll('.theme-current-label').forEach(el => {
            el.textContent = isDark ? 'Sombre' : 'Clair';
        });

        // Déclencher un événement global pour d'éventuels graphiques ou cartes
        window.dispatchEvent(new CustomEvent('theme:changed', { detail: { theme } }));
    }

    showToast(theme) {
        const hint = document.querySelector('.shortcut-hint');
        if (hint) {
            hint.innerHTML = `Mode <strong>${theme === 'dark' ? 'Sombre 🌙' : 'Clair ☀️'}</strong> activé (Touche M)`;
            hint.classList.add('is-visible');
            clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(() => {
                hint.classList.remove('is-visible');
            }, 2200);
        }
    }
}

