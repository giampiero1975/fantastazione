// In resources/js/app.js

import './bootstrap'; // Bootstrap di Laravel (include Axios, ecc.)

import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();

// IMPORTA TOMSELECT E RENDILO GLOBALE
import TomSelect from 'tom-select';
window.TomSelect = TomSelect; // Ora puoi usare new TomSelect(...) negli script nelle viste Blade