import axios from 'axios';

const http = axios.create({
  headers: { 'X-Requested-With': 'XMLHttpRequest' },
  withCredentials: true,
});

// Otomatis kirim XSRF-TOKEN dari cookie
http.interceptors.request.use((config) => {
  const token = document.cookie
    .split('; ')
    .find(row => row.startsWith('XSRF-TOKEN='))
    ?.split('=')[1];
  if (token) {
    config.headers['X-XSRF-TOKEN'] = decodeURIComponent(token);
  }
  return config;
});

// Handle 401 globally
http.interceptors.response.use(
  (res) => res,
  (err) => {
    if (err.response?.status === 401) {
      window.location.href = '/login';
    }
    return Promise.reject(err);
  }
);

export default http;
