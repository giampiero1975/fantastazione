import axios from 'axios'; // 1. Importa axios usando la sintassi ES Module

window.axios = axios; // 2. Assegna l'istanza importata a window.axios (mantiene la compatibilità se altro codice lo usa globalmente)

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'; // 3. Questa riga rimane uguale, configura l'istanza importata