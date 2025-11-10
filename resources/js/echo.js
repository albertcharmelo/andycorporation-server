import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

// Configuración de Laravel Echo con Pusher
// Echo maneja automáticamente la autenticación con sesión web
window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY || 'a637463b7b6b72bc1298',
    cluster: import.meta.env.VITE_PUSHER_CLUSTER || 'sa1',
    forceTLS: false, // Cambiar a true en producción con HTTPS
    encrypted: true,
    channelAuthorization: {
        authorize: (channel, callback) => {
            console.log('Authorizing channel:', channel);
            return callback(null, {
                auth: {
                    headers: {
                        Authorization: `Bearer ${localStorage.getItem('api_token')}`,
                    },
                },
            });
        },
    },
    authEndpoint: '/broadcasting/auths',
    // authEndpoint se configura automáticamente según el contexto (web o API)
    // Para sesión web: usa /broadcasting/auth
    // Para API: usa /api/broadcasting/auth (si se proporciona token)
});
