import '@hotwired/turbo';
import './stimulus_bootstrap.js';
import * as bootstrap from 'bootstrap';
import 'bootstrap/dist/css/bootstrap.min.css';
import './styles/app.css';

if (typeof window.bootstrap === 'undefined') {
    window.bootstrap = bootstrap;
}

console.log('G-Parc initialized with Turbo, Bootstrap 5 and AssetMapper.');

