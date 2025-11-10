import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Configurar Pusher global para que Echo lo use
window.Pusher = Pusher;

// Obtener el token CSRF del meta tag
const getCsrfToken = (): string | null => {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    return metaTag ? metaTag.getAttribute('content') : null;
};

console.log('[Bootstrap] Inicializando Laravel Echo');

// Configuración de Laravel Echo con Pusher
// Echo maneja automáticamente la autenticación con sesión web usando CSRF
window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY || 'a637463b7b6b72bc1298',
    cluster: import.meta.env.VITE_PUSHER_CLUSTER || 'sa1',
    forceTLS: false, // Cambiar a true en producción con HTTPS
    encrypted: true,
    auth: {
        headers: {
            'X-CSRF-TOKEN': getCsrfToken() || '',
            'X-Requested-With': 'XMLHttpRequest',
        },
    },
    authEndpoint: '/broadcasting/auth',
});

console.log('[Bootstrap] ✅ Laravel Echo inicializado correctamente');
