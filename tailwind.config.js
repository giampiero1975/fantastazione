// In tailwind.config.js

export default {
  content: [
    "./resources/**/*.blade.php",  // Scansiona tutti i file .blade.php in resources
    "./resources/**/*.js",       // Scansiona tutti i file .js in resources (per Alpine, ecc.)
    // "./resources/**/*.vue",    // Decommenta o aggiungi se usi Vue.js
    "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php", // Necessario per gli stili della paginazione di Laravel, se la usi
  ],
  theme: {
    extend: {
      // ... la tua configurazione del tema ...
      fontFamily: {
        sans: ['Figtree', /* ...altri font... */], // Assicurati che questo sia definito se lo usi (Breeze lo fa)
      },
    },
  },
  plugins: [
    require('@tailwindcss/forms'), // Assicurati che questo plugin sia correttamente importato/richiesto se usi la sintassi ESM o CJS
    // Se hai convertito in ESM:
    // import forms from '@tailwindcss/forms';
    // plugins: [forms],
  ],
  // Assicurati che la sezione plugins sia corretta per la sintassi che usi (ESM o CJS nel tuo .js)
  // Se usi ESM nel .js:
  // plugins: [
  //  forms // Usa la variabile importata
  // ],
};