# Verificación de Broadcasting

## Problema
El evento `OrderMessageSent` se emite en Laravel pero no llega al frontend de Next.js.

## Pasos para verificar

### 1. Verificar configuración en `.env`

```bash
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=1860301
PUSHER_APP_KEY=a637463b7b6b72bc1298
PUSHER_APP_SECRET=4abef8a51467f3e22b70
PUSHER_APP_CLUSTER=sa1
```

### 2. Verificar logs después de enviar mensaje

Deberías ver en `storage/logs/laravel.log`:

```
OrderChatController: Enviando mensaje
SendOrderMessage: Disparando evento OrderMessageSent
OrderMessageSent: shouldBroadcast?
OrderMessageSent: broadcastOn
OrderMessageSent: broadcastWith
SendOrderMessage: Evento OrderMessageSent disparado exitosamente
```

### 3. Verificar en el frontend (Next.js)

En la consola del navegador deberías ver:

```
[Pusher] 🔧 Inicializando Pusher...
[Pusher] ✅ Conectado a Pusher
[Chat] 🔌 Suscribiéndose al canal: private-order.14
[Chat] ✅ Suscrito exitosamente al canal: private-order.14
[Chat] 📨 ===== EVENTO order.message.sent RECIBI DO =====
```

### 4. Verificar que el frontend use la misma clave de Pusher

En `andybacklog` debe tener:
- `NEXT_PUBLIC_PUSHER_APP_KEY=a637463b7b6b72bc1298`
- `NEXT_PUBLIC_PUSHER_CLUSTER=sa1`

### 5. Comandos útiles

```bash
# Limpiar cache de configuración
php artisan config:clear
php artisan cache:clear

# Ver configuración actual
php artisan tinker
>>> config('broadcasting.default')
>>> config('broadcasting.connections.pusher')
```

