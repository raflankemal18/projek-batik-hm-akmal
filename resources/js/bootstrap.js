import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * JWT TOKEN INTERCEPTOR
 * Setiap kali axios melakukan request, kode ini akan mengecek LocalStorage.
 * Jika ada token, token akan ditempel ke Header Authorization.
 */
window.axios.interceptors.request.use((config) => {
    // Ambil token dari penyimpanan browser
    const token = localStorage.getItem('auth_token');
    
    if (token) {
        // Tempelkan token: "Authorization: Bearer eyJhbGciOi..."
        config.headers.Authorization = `Bearer ${token}`;
    }
    
    return config;
}, (error) => {
    return Promise.reject(error);
});