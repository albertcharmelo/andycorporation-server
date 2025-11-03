# Documentación API - Sistema de Gestión de Pedidos

## Flujo General del Sistema

### 1. Proceso de Compra (Cliente)
1. **Agregar productos al carrito** → `POST /api/cart/add`
2. **Seleccionar dirección de envío** → `GET /api/addresses`
3. **Crear orden** → `POST /api/checkout/create-order`
   - Seleccionar tipo de delivery: `free`, `express`, `pickup`
   - Subir comprobante de pago
4. **Confirmar pago** → `POST /api/checkout/confirm-payment/{orderId}`

### 2. Gestión Admin
1. **Verificar comprobante** → `POST /api/admin/orders/{orderId}/verify-payment`
2. **Actualizar estados** → `PUT /api/admin/orders/{orderId}/update-status`
   - `received` → `invoiced` → `in_agency`
3. **Asignar delivery** → `POST /api/admin/orders/{orderId}/assign-delivery`

### 3. Gestión Delivery
1. **Ver pedidos asignados** → `GET /api/delivery/orders`
2. **Marcar en camino** → `PUT /api/delivery/orders/{orderId}/update-status`
3. **Actualizar ubicación** → `POST /api/delivery/orders/{orderId}/update-location`
4. **Marcar entregado** → `PUT /api/delivery/orders/{orderId}/update-status`
5. **SOS si necesario** → `POST /api/delivery/orders/{orderId}/sos`

### 4. Seguimiento Cliente
1. **Ver historial** → `GET /api/orders`
2. **Ver timeline** → `GET /api/orders/{orderId}`
3. **Tracking en tiempo real** → `GET /api/orders/{orderId}/track`

## Estados del Pedido

| Estado | Descripción | Quién puede cambiar |
|--------|-------------|-------------------|
| `pending_payment` | Pago pendiente | Cliente |
| `paid` | Pagado (confirmado por cliente) | Cliente |
| `received` | Recibido (verificado por admin) | Admin |
| `invoiced` | Facturado | Admin |
| `in_agency` | En agencia | Admin |
| `on_the_way` | En camino | Delivery |
| `delivered` | Entregado | Delivery |
| `cancelled` | Cancelado | Admin |
| `refunded` | Reembolsado | Admin |

## Tipos de Delivery

| Tipo | Descripción | Costo |
|------|-------------|-------|
| `free` | Delivery gratis dentro de 2km | $0 (≤2km) o express (>2km) |
| `express` | Delivery express | $2.000 base + $500/km adicional |
| `pickup` | Retiro en tienda | $0 |

## Endpoints Detallados

### Cliente - Historial de Pedidos

#### `GET /api/orders`
**Descripción:** Obtener historial de pedidos del usuario autenticado

**Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
- `status` (string, opcional): Filtrar por estado del pedido
- `date_from` (date, opcional): Fecha desde (YYYY-MM-DD)
- `date_to` (date, opcional): Fecha hasta (YYYY-MM-DD)
- `page` (integer, opcional): Número de página para paginación

**Response:**
```json
{
  "orders": {
    "data": [
      {
        "id": 1,
        "user_id": 1,
        "address_id": 1,
        "subtotal": 50000.00,
        "shipping_cost": 2000.00,
        "total": 52000.00,
        "payment_method": "manual_transfer",
        "payment_reference": "ABC1234567",
        "delivery_type": "express",
        "status": "delivered",
        "sos_status": false,
        "created_at": "2025-10-27T10:00:00.000000Z",
        "user": {...},
        "delivery": {...},
        "address": {...},
        "items": [...]
      }
    ],
    "current_page": 1,
    "per_page": 15,
    "total": 25
  },
  "filters": {
    "status": "delivered",
    "date_from": "2025-10-01",
    "date_to": "2025-10-31"
  }
}
```

#### `GET /api/orders/{orderId}`
**Descripción:** Ver detalle completo y timeline de un pedido

**Response:**
```json
{
  "order": {
    "id": 1,
    "status": "on_the_way",
    "delivery_type": "express",
    "total": 52000.00,
    "delivery": {
      "id": 2,
      "name": "Juan Pérez",
      "tel": "3001234567"
    },
    "address": {
      "address_line_1": "Calle 123 #45-67",
      "latitude": 4.6097,
      "longitude": -74.0817
    },
    "items": [...],
    "delivery_locations": [...]
  },
  "timeline": [
    {
      "id": 1,
      "status": "pending_payment",
      "status_label": "Pago Pendiente",
      "comment": null,
      "changed_by": "Sistema",
      "changed_at": "2025-10-27T10:00:00.000000Z",
      "latitude": null,
      "longitude": null
    },
    {
      "id": 2,
      "status": "paid",
      "status_label": "Pagado",
      "comment": "Pago confirmado por el cliente",
      "changed_by": "María García",
      "changed_at": "2025-10-27T10:05:00.000000Z",
      "latitude": null,
      "longitude": null
    }
  ]
}
```

#### `GET /api/orders/{orderId}/track`
**Descripción:** Obtener ubicación actual del delivery (solo si está en camino)

**Response (Pedido en camino):**
```json
{
  "order": {
    "id": 1,
    "status": "on_the_way",
    "delivery": {
      "name": "Juan Pérez",
      "tel": "3001234567"
    }
  },
  "delivery_location": {
    "latitude": 4.6100,
    "longitude": -74.0820,
    "updated_at": "2025-10-27T11:30:00.000000Z"
  },
  "delivery_info": {
    "name": "Juan Pérez",
    "tel": "3001234567"
  }
}
```

**Response (Pedido no en camino):**
```json
{
  "message": "El pedido no está en camino",
  "status": "in_agency",
  "status_label": "En Agencia"
}
```

### Admin - Gestión de Pedidos

#### `GET /api/admin/orders`
**Descripción:** Listar todos los pedidos con filtros

**Headers:**
```
Authorization: Bearer {admin_token}
```

**Query Parameters:**
- `status` (string, opcional): Filtrar por estado
- `delivery_id` (integer, opcional): Filtrar por delivery asignado
- `date_from` (date, opcional): Fecha desde
- `date_to` (date, opcional): Fecha hasta
- `sos_only` (boolean, opcional): Solo pedidos con SOS activo

**Response:**
```json
{
  "orders": {
    "data": [...],
    "current_page": 1,
    "per_page": 20,
    "total": 150
  },
  "filters": {
    "status": "paid",
    "delivery_id": 2,
    "sos_only": false
  }
}
```

#### `POST /api/admin/orders/{orderId}/verify-payment`
**Descripción:** Verificar comprobante de pago y aprobar/rechazar

**Body:**
```json
{
  "approved": true,
  "comment": "Pago verificado correctamente"
}
```

**Response:**
```json
{
  "message": "Pago verificado exitosamente",
  "order": {
    "id": 1,
    "status": "received",
    "user": {...},
    "delivery": null,
    "address": {...}
  }
}
```

#### `PUT /api/admin/orders/{orderId}/update-status`
**Descripción:** Actualizar estado del pedido (received → invoiced → in_agency)

**Body:**
```json
{
  "status": "invoiced",
  "comment": "Factura generada #FAC-001"
}
```

#### `POST /api/admin/orders/{orderId}/assign-delivery`
**Descripción:** Asignar delivery al pedido

**Body:**
```json
{
  "delivery_user_id": 2,
  "comment": "Delivery asignado para entrega inmediata"
}
```

#### `GET /api/admin/orders/{orderId}/payment-proof`
**Descripción:** Obtener archivo del comprobante de pago

**Response:** Archivo de imagen (Content-Type: image/jpeg)

### Delivery - Gestión de Entregas

#### `GET /api/delivery/orders`
**Descripción:** Listar pedidos asignados al delivery

**Headers:**
```
Authorization: Bearer {delivery_token}
```

**Query Parameters:**
- `status` (string, opcional): Filtrar por estado
- `sos_only` (boolean, opcional): Solo pedidos con SOS

**Response:**
```json
{
  "orders": {
    "data": [
      {
        "id": 1,
        "status": "in_agency",
        "delivery_type": "express",
        "total": 52000.00,
        "sos_status": false,
        "user": {...},
        "address": {...},
        "items": [...]
      }
    ]
  },
  "filters": {
    "status": "in_agency",
    "sos_only": false
  }
}
```

#### `PUT /api/delivery/orders/{orderId}/update-status`
**Descripción:** Actualizar estado del pedido (in_agency → on_the_way → delivered)

**Body:**
```json
{
  "status": "on_the_way",
  "comment": "Salí de la agencia",
  "latitude": 4.6097,
  "longitude": -74.0817
}
```

#### `POST /api/delivery/orders/{orderId}/update-location`
**Descripción:** Actualizar ubicación en tiempo real (solo si está en camino)

**Body:**
```json
{
  "latitude": 4.6100,
  "longitude": -74.0820
}
```

**Response:**
```json
{
  "message": "Ubicación actualizada exitosamente",
  "location": {
    "id": 1,
    "order_id": 1,
    "delivery_user_id": 2,
    "latitude": 4.6100,
    "longitude": -74.0820,
    "created_at": "2025-10-27T11:30:00.000000Z"
  }
}
```

#### `POST /api/delivery/orders/{orderId}/sos`
**Descripción:** Activar SOS para un pedido

**Body:**
```json
{
  "comment": "Accidente en la vía, necesito ayuda"
}
```

**Response:**
```json
{
  "message": "SOS activado exitosamente",
  "order": {
    "id": 1,
    "sos_status": true,
    "sos_comment": "Accidente en la vía, necesito ayuda",
    "sos_reported_at": "2025-10-27T11:35:00.000000Z"
  }
}
```

## Sistema de Chat

### Funcionalidad del Chat
- **Cliente ↔ Admin**: Chat completo antes de confirmación de pago
- **Delivery**: Solo puede ver/escribir mensajes después de ser asignado, sin acceso a mensajes previos
- **Tiempo Real**: Mensajes se sincronizan automáticamente via Pusher
- **Archivos**: Soporte para imágenes y archivos adjuntos

### Endpoints de Chat

#### `GET /api/orders/{orderId}/chat`
**Descripción:** Obtener mensajes del chat de una orden

**Headers:**
```
Authorization: Bearer {token}
```

**Response (Cliente/Admin):**
```json
{
  "order_id": 1,
  "messages": [
    {
      "id": 1,
      "order_id": 1,
      "user_id": 1,
      "message": "Hola, tengo una pregunta sobre mi pedido",
      "message_type": "text",
      "file_path": null,
      "is_delivery_message": false,
      "is_read": true,
      "created_at": "2025-10-27T10:00:00.000000Z",
      "user": {
        "id": 1,
        "name": "Juan Pérez"
      }
    }
  ],
  "user_role": "client"
}
```

**Response (Delivery):**
```json
{
  "order_id": 1,
  "messages": [
    {
      "id": 5,
      "order_id": 1,
      "user_id": 2,
      "message": "Hola, soy el delivery asignado",
      "message_type": "text",
      "file_path": null,
      "is_delivery_message": true,
      "is_read": false,
      "created_at": "2025-10-27T11:00:00.000000Z",
      "user": {
        "id": 2,
        "name": "Carlos Delivery"
      }
    }
  ],
  "user_role": "delivery"
}
```

#### `POST /api/orders/{orderId}/chat`
**Descripción:** Enviar mensaje en el chat

**Headers:**
```
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Body (Texto):**
```
message: "Hola, ¿cuándo llegará mi pedido?"
message_type: "text"
```

**Body (Con archivo):**
```
message: "Aquí está la foto del problema"
message_type: "image"
file: [archivo de imagen]
```

**Response:**
```json
{
  "message": "Mensaje enviado exitosamente",
  "data": {
    "id": 6,
    "order_id": 1,
    "user_id": 1,
    "message": "Hola, ¿cuándo llegará mi pedido?",
    "message_type": "text",
    "file_path": null,
    "is_delivery_message": false,
    "is_read": false,
    "created_at": "2025-10-27T12:00:00.000000Z",
    "user": {
      "id": 1,
      "name": "Juan Pérez"
    }
  }
}
```

#### `PUT /api/orders/{orderId}/chat/mark-read`
**Descripción:** Marcar mensajes como leídos

**Response:**
```json
{
  "message": "Mensajes marcados como leídos",
  "updated_count": 3
}
```

#### `GET /api/orders/{orderId}/chat/stats`
**Descripción:** Obtener estadísticas del chat

**Response:**
```json
{
  "stats": {
    "total_messages": 15,
    "unread_messages": 2,
    "delivery_messages": 5,
    "pre_delivery_messages": 10,
    "last_message_at": "2025-10-27T12:00:00.000000Z"
  }
}
```

#### `GET /api/orders/{orderId}/chat/attachment/{messageId}`
**Descripción:** Obtener archivo adjunto

**Response:** Archivo (Content-Type según tipo de archivo)

## Eventos en Tiempo Real (Pusher)

### Canales Privados
- `order.{orderId}`: Notificaciones específicas del pedido
- `order.{orderId}.chat`: Chat específico del pedido
- `user.{userId}`: Notificaciones para el usuario
- `admin.orders`: Notificaciones generales para admin

### Eventos

#### `order.status.changed`
**Descripción:** Se dispara cuando cambia el estado de un pedido

**Datos:**
```json
{
  "order": {...},
  "old_status": "in_agency",
  "new_status": "on_the_way",
  "changed_by": {...},
  "timestamp": "2025-10-27T11:30:00.000000Z"
}
```

#### `delivery.location.updated`
**Descripción:** Se dispara cuando el delivery actualiza su ubicación

**Datos:**
```json
{
  "order": {...},
  "location": {
    "latitude": 4.6100,
    "longitude": -74.0820,
    "timestamp": "2025-10-27T11:30:00.000000Z"
  },
  "delivery": {...}
}
```

#### `order.sos.triggered`
**Descripción:** Se dispara cuando se activa SOS en un pedido

**Datos:**
```json
{
  "order": {...},
  "sos_comment": "Accidente en la vía, necesito ayuda",
  "sos_reported_at": "2025-10-27T11:35:00.000000Z"
}
```

#### `order.message.sent`
**Descripción:** Se dispara cuando se envía un mensaje en el chat

**Datos:**
```json
{
  "order": {
    "id": 1,
    "status": "on_the_way",
    "user": {...},
    "delivery": {...}
  },
  "message": {
    "id": 6,
    "message": "Hola, ¿cuándo llegará mi pedido?",
    "message_type": "text",
    "file_path": null,
    "is_delivery_message": false,
    "created_at": "2025-10-27T12:00:00.000000Z",
    "user": {...}
  },
  "sender": {
    "id": 1,
    "name": "Juan Pérez",
    "role": "client"
  }
}
```

## Configuración Requerida

### Variables de Entorno (.env)
```env
# Pusher Configuration
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=your_cluster

# Store Coordinates (configurar según ubicación real)
STORE_LATITUDE=4.6097
STORE_LONGITUDE=-74.0817
```

## Integración con Pusher (Frontend)

### Instalación de Pusher en el Frontend

#### Para React/Vue/Angular:
```bash
npm install pusher-js
```

#### Para JavaScript vanilla:
```html
<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
```

### Configuración del Cliente Pusher

```javascript
import Pusher from 'pusher-js';

// Configuración de Pusher
const pusher = new Pusher('your_app_key', {
  cluster: 'your_cluster',
  encrypted: true,
  authEndpoint: '/api/broadcasting/auth', // Para canales privados
  auth: {
    headers: {
      'Authorization': `Bearer ${userToken}`,
      'Accept': 'application/json',
    }
  }
});

// Suscribirse a canales
const orderChannel = pusher.subscribe('private-order.1');
const userChannel = pusher.subscribe('private-user.1');
const adminChannel = pusher.subscribe('private-admin.orders');
```

### Eventos a Escuchar

#### 1. Cambios de Estado del Pedido
```javascript
orderChannel.bind('order.status.changed', function(data) {
  console.log('Estado del pedido cambió:', data);
  // data.order.status contiene el nuevo estado
  // data.old_status contiene el estado anterior
  // data.changed_by contiene quien hizo el cambio
  updateOrderStatus(data.order.status);
});
```

#### 2. Actualización de Ubicación del Delivery
```javascript
orderChannel.bind('delivery.location.updated', function(data) {
  console.log('Ubicación del delivery actualizada:', data);
  // data.location.latitude y data.location.longitude
  // data.location.timestamp
  updateDeliveryLocationOnMap(data.location);
});
```

#### 3. Activación de SOS
```javascript
orderChannel.bind('order.sos.triggered', function(data) {
  console.log('SOS activado:', data);
  // data.sos_comment contiene el comentario del delivery
  // data.sos_reported_at contiene la fecha/hora
  showSOSAlert(data.sos_comment);
});
```

#### 4. Mensajes del Chat
```javascript
orderChannel.bind('order.message.sent', function(data) {
  console.log('Nuevo mensaje:', data);
  // data.message contiene el mensaje completo
  // data.sender contiene información del remitente
  addMessageToChat(data.message);
});
```

### Ejemplo Completo de Implementación

```javascript
class OrderTrackingService {
  constructor(userToken, orderId) {
    this.userToken = userToken;
    this.orderId = orderId;
    this.pusher = null;
    this.channel = null;
  }

  connect() {
    this.pusher = new Pusher('your_app_key', {
      cluster: 'your_cluster',
      encrypted: true,
      authEndpoint: '/api/broadcasting/auth',
      auth: {
        headers: {
          'Authorization': `Bearer ${this.userToken}`,
          'Accept': 'application/json',
        }
      }
    });

    this.channel = this.pusher.subscribe(`private-order.${this.orderId}`);
    this.setupEventListeners();
  }

  setupEventListeners() {
    // Estado del pedido
    this.channel.bind('order.status.changed', (data) => {
      this.onOrderStatusChanged(data);
    });

    // Ubicación del delivery
    this.channel.bind('delivery.location.updated', (data) => {
      this.onDeliveryLocationUpdated(data);
    });

    // SOS
    this.channel.bind('order.sos.triggered', (data) => {
      this.onSOSTriggered(data);
    });

    // Mensajes del chat
    this.channel.bind('order.message.sent', (data) => {
      this.onMessageSent(data);
    });
  }

  onOrderStatusChanged(data) {
    // Actualizar UI con nuevo estado
    document.getElementById('order-status').textContent = data.order.status;
    document.getElementById('status-timestamp').textContent = data.timestamp;
    
    // Mostrar notificación
    this.showNotification(`Estado del pedido: ${data.order.status}`);
  }

  onDeliveryLocationUpdated(data) {
    // Actualizar mapa con nueva ubicación
    this.updateMapMarker(data.location.latitude, data.location.longitude);
    
    // Actualizar timestamp
    document.getElementById('last-location-update').textContent = data.location.timestamp;
  }

  onSOSTriggered(data) {
    // Mostrar alerta de SOS
    this.showSOSAlert(data.sos_comment);
    
    // Notificar al usuario
    this.showNotification('⚠️ SOS activado en tu pedido');
  }

  onMessageSent(data) {
    // Agregar mensaje al chat
    this.addMessageToChat(data.message);
    
    // Mostrar notificación si no es el usuario actual
    if (data.sender.id !== this.currentUserId) {
      this.showNotification(`Nuevo mensaje de ${data.sender.name}`);
    }
  }

  disconnect() {
    if (this.pusher) {
      this.pusher.disconnect();
    }
  }
}

// Uso del servicio
const trackingService = new OrderTrackingService(userToken, orderId);
trackingService.connect();
```

### Configuración de Canales Privados (Laravel)

Crear el archivo `routes/channels.php`:

```php
<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('order.{orderId}', function ($user, $orderId) {
    $order = \App\Models\Order::find($orderId);
    
    // Cliente puede ver sus propios pedidos
    if ($user->id === $order->user_id) {
        return true;
    }
    
    // Admin puede ver todos los pedidos
    if ($user->hasRole(['admin', 'super_admin'])) {
        return true;
    }
    
    // Delivery puede ver pedidos asignados a él
    if ($user->hasRole('delivery') && $order->delivery_user_id === $user->id) {
        return true;
    }
    
    return false;
});

Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('admin.orders', function ($user) {
    return $user->hasRole(['admin', 'super_admin']);
});
```

### Configuración de Broadcasting (Laravel)

En `config/broadcasting.php`:

```php
'pusher' => [
    'driver' => 'pusher',
    'key' => env('PUSHER_APP_KEY'),
    'secret' => env('PUSHER_APP_SECRET'),
    'app_id' => env('PUSHER_APP_ID'),
    'options' => [
        'cluster' => env('PUSHER_APP_CLUSTER'),
        'useTLS' => true,
    ],
],
```

### Manejo de Errores

```javascript
// Manejo de errores de conexión
pusher.connection.bind('error', function(err) {
  console.error('Error de conexión Pusher:', err);
});

pusher.connection.bind('disconnected', function() {
  console.log('Desconectado de Pusher');
  // Intentar reconectar
  setTimeout(() => {
    pusher.connect();
  }, 5000);
});

pusher.connection.bind('connected', function() {
  console.log('Conectado a Pusher');
});
```

### Roles y Permisos
- `client`: Puede ver sus pedidos y hacer tracking
- `delivery`: Puede gestionar pedidos asignados y actualizar ubicación
- `admin`: Puede gestionar todos los pedidos y asignar deliveries
- `super_admin`: Acceso completo al sistema

## Códigos de Error Comunes

| Código | Descripción |
|--------|-------------|
| 400 | Datos de entrada inválidos |
| 401 | Token de autenticación inválido |
| 403 | Sin permisos para realizar la acción |
| 404 | Recurso no encontrado |
| 422 | Error de validación |
| 500 | Error interno del servidor |

## Notas Importantes

1. **Autenticación:** Todos los endpoints requieren token Bearer válido
2. **Autorización:** Los endpoints están protegidos por roles específicos
3. **Paginación:** Los listados usan paginación de Laravel (15-20 items por página)
4. **Coordenadas:** Se usan coordenadas decimales (lat, lng) para ubicaciones
5. **Tiempo Real:** Los eventos se envían automáticamente via Pusher
6. **SOS:** Los pedidos con SOS activo se notifican a admin y cliente
7. **Estados:** Solo se permiten transiciones válidas de estado
8. **Delivery:** Solo usuarios con rol 'delivery' pueden ser asignados
