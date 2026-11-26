import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['sidebar', 'backdrop'];

    toggle() {
        if (this.hasSidebarTarget) {
            this.sidebarTarget.classList.toggle('show');
        }
        if (this.hasBackdropTarget) {
            this.backdropTarget.classList.toggle('show');
        }
    }

    close() {
        if (this.hasSidebarTarget) {
            this.sidebarTarget.classList.remove('show');
        }
        if (this.hasBackdropTarget) {
            this.backdropTarget.classList.remove('show');
        }
    }
}
