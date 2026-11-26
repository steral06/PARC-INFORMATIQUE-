import { Controller } from '@hotwired/stimulus';

/*
 * Raccourcis clavier de G-Parc.
 *
 * Principe : le contrôleur ne connaît aucune route. Il cherche dans la page les éléments
 * portant data-shortcut="x" et les clique (ou leur donne le focus s'il s'agit d'un champ).
 * Les droits sont donc respectés automatiquement : un lien absent du HTML (réservé au
 * super-admin, par exemple) n'a pas de raccourci.
 *
 * Utilisation : <body data-controller="shortcuts" data-action="keydown@document->shortcuts#handle">
 */
export default class extends Controller {
    static targets = ['row'];

    connect() {
        this.current = null;
        this.hintTimer = null;
    }

    disconnect() {
        clearTimeout(this.hintTimer);
    }

    handle(event) {
        // Pages où les raccourcis sont coupés (connexion, clavier PIN)
        if (document.querySelector('[data-shortcuts-disabled]')) return;
        // On ne touche pas aux raccourcis du navigateur (Ctrl+S, Ctrl+F, AltGr...)
        // MAIS on autorise Alt+ArrowDown/Up pour la navigation de formulaire
        if (event.ctrlKey || event.metaKey) return;
        if (event.altKey && !(event.key === 'ArrowDown' || event.key === 'ArrowUp')) return;

        const key = event.key;
        const typing = this.isTyping(event.target);
        const modalOpen = document.body.classList.contains('modal-open');

        if (key === 'Escape') {
            if (modalOpen) return; // Bootstrap ferme la modale lui-même
            if (typing) {
                event.target.blur();
            } else {
                this.clearSelection();
            }
            return;
        }

        // Alt+ArrowDown / Alt+ArrowUp : navigation entre champs du formulaire
        if (event.altKey && (key === 'ArrowDown' || key === 'ArrowUp')) {
            event.preventDefault();
            this.moveFormFocus(key === 'ArrowDown' ? 1 : -1);
            return;
        }

        // Pendant la saisie ou quand une modale est ouverte, les lettres restent des lettres
        if (typing || modalOpen) return;

        if (key === 'ArrowDown' || key === 'ArrowUp') {
            if (this.rowTargets.length === 0) return;
            event.preventDefault();
            this.moveSelection(key === 'ArrowDown' ? 1 : -1);
            return;
        }

        if (event.repeat || key.length !== 1) return;

        const shortcut = key.toLowerCase();
        const candidates = this.candidates(shortcut);
        if (candidates.length === 0) return;

        const target = this.resolve(candidates);
        event.preventDefault();

        if (!target) {
            this.showHint('Sélectionnez d\'abord une ligne avec les flèches ↑ ↓');
            return;
        }
        
        this.activate(target);
    }

    // Clic sur une ligne : elle devient la ligne sélectionnée (sauf clic sur un lien ou un bouton)
    pick(event) {
        if (event.target.closest('a, button, input, select, textarea, label')) return;
        this.select(event.currentTarget);
    }

    // ---- Navigation formulaire (Alt+ArrowDown/Up) ------------------------------

    moveFormFocus(step) {
        const form = document.activeElement?.closest('form');
        if (!form) return;
        const fields = [...form.querySelectorAll('input, select, textarea, button[type="submit"]')].filter(
            el => !el.disabled && el.type !== 'hidden' && el.offsetParent !== null
        );
        if (fields.length === 0) return;
        const idx = fields.indexOf(document.activeElement);
        let next = idx === -1 ? (step > 0 ? 0 : fields.length - 1) : Math.min(fields.length - 1, Math.max(0, idx + step));
        fields[next].focus();
    }

    // ---- Sélection de ligne -------------------------------------------------

    moveSelection(step) {
        const rows = this.rowTargets;
        const index = this.current ? rows.indexOf(this.current) : -1;
        let next;
        if (index === -1) {
            next = step > 0 ? 0 : rows.length - 1;
        } else {
            next = Math.min(rows.length - 1, Math.max(0, index + step));
        }
        this.select(rows[next]);
    }

    select(row) {
        if (this.current) {
            this.current.classList.remove('is-selected');
            this.current.removeAttribute('aria-selected');
        }
        this.current = row;
        row.classList.add('is-selected');
        row.setAttribute('aria-selected', 'true');
        row.scrollIntoView({ block: 'nearest' });
    }

    clearSelection() {
        if (!this.current) return;
        this.current.classList.remove('is-selected');
        this.current.removeAttribute('aria-selected');
        this.current = null;
    }

    get selectedRow() {
        return this.current && this.rowTargets.includes(this.current) ? this.current : null;
    }

    // ---- Recherche de la cible ----------------------------------------------

    candidates(shortcut) {
        return [...document.querySelectorAll('[data-shortcut]')].filter(
            (el) => el.dataset.shortcut === shortcut && !el.disabled,
        );
    }

    // Ligne sélectionnée d'abord, puis éléments de la page situés hors des lignes du tableau
    resolve(candidates) {
        const row = this.selectedRow;
        if (row) {
            const inRow = candidates.find((el) => row.contains(el));
            if (inRow) return inRow;
        }
        return candidates.find((el) => !el.closest('[data-shortcuts-target="row"]')) || null;
    }

    activate(el) {
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName)) {
            el.focus();
            if (typeof el.select === 'function') el.select();
        } else {
            el.click();
        }
    }

    isTyping(el) {
        return !!el && (el.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName));
    }

    // ---- Petit message d'aide -----------------------------------------------

    showHint(message) {
        let el = document.getElementById('shortcut-hint');
        if (!el) {
            el = document.createElement('div');
            el.id = 'shortcut-hint';
            el.className = 'shortcut-hint';
            el.setAttribute('role', 'status');
            document.body.appendChild(el);
        }
        el.textContent = message;
        el.classList.add('is-visible');
        clearTimeout(this.hintTimer);
        this.hintTimer = setTimeout(() => el.classList.remove('is-visible'), 2200);
    }
}
