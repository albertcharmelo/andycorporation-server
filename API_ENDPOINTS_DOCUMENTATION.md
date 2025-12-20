# Documentación de Endpoints API - Andy Corporation

## Base URL
```
http://localhost:8000/api
```

## Autenticación

La mayoría de los endpoints requieren autenticación mediante **Bearer Token** (Laravel Sanctum).

**Header requerido:**
```
Authorization: Bearer {tu_token}
```

Para obtener un token, usa los endpoints de autenticación (`/api/auth/login` o `/api/auth/register`).

---

## 📋 Índice

1. [Autenticación](#autenticación)
2. [Usuario](#usuario)
3. [Dashboard](#dashboard)
4. [Productos](#productos)
5. [Categorías](#categorías)
6. [Carrito](#carrito)
7. [Direcciones](#direcciones)
8. [Checkout y Órdenes](#checkout-y-órdenes)
9. [Chat de Órdenes](#chat-de-órdenes)
10. [Administración](#administración)
11. [Delivery](#delivery)
12. [Testing](#testing)

---

## 🔐 Autenticación

### POST `/api/auth/register`
**Descripción:** Registra un nuevo usuario en el sistema.

**Autenticación:** No requerida

**Payload:**
```json
{
  "name": "Juan Pérez",
  "email": "juan@example.com",
  "password": "password123",
  "cedula_type": "v",
  "cedula_ID": "12345678",
  "tel": "04121234567",
  "google_id": null,
  "avatar": null
}
```

**Campos:**
- `name` (requerido): Nombre completo del usuario
- `email` (requerido): Correo electrónico único
- `password` (opcional): Contraseña (mínimo 6 caracteres)
- `cedula_type` (opcional): Tipo de cédula (`v`, `j`, `e`, `g`, `r`, `p`)
- `cedula_ID` (opcional): Número de cédula (7-20 caracteres)
- `tel` (opcional): Teléfono (10-15 dígitos, único)
- `google_id` (opcional): ID de Google si se registra con Google
- `avatar` (opcional): URL del avatar

**Response (201):**
```json
{
  "message": "Usuario creado correctamente",
  "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
  "user": {
    "id": 1,
    "name": "Juan Pérez",
    "email": "juan@example.com",
    "roles": ["client"],
    "is_admin": false,
    "is_delivery": false,
    "is_client": true
  }
}
```

---

### POST `/api/auth/login`
**Descripción:** Inicia sesión y obtiene un token de acceso.

**Autenticación:** No requerida

**Payload:**
```json
{
  "email": "juan@example.com",
  "password": "password123",
  "type": "email"
}
```

**Campos:**
- `email` (requerido): Correo electrónico
- `password` (requerido): Contraseña
- `type` (opcional): Tipo de login (`email` o `phone`), por defecto `email`

**Response (200):**
```json
{
  "message": "Usuario logeado correctamente",
  "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
  "user": {
    "id": 1,
    "name": "Juan Pérez",
    "email": "juan@example.com",
    "roles": ["client"],
    "is_admin": false,
    "is_delivery": false,
    "is_client": true
  }
}
```

---

## 👤 Usuario

### GET `/api/user`
**Descripción:** Obtiene la información del usuario autenticado.

**Autenticación:** Requerida

**Response (200):**
```json
{
  "user": {
    "id": 1,
    "name": "Juan Pérez",
    "email": "juan@example.com",
    "roles": ["client"],
    "is_admin": false,
    "is_delivery": false,
    "is_client": true
  },
  "message": "Welcome to the API"
}
```

---

### POST `/api/broadcasting/auth`
**Descripción:** Autenticación para broadcasting (Pusher) con Sanctum.

**Autenticación:** Requerida

**Payload:**
```json
{
  "socket_id": "123.456",
  "channel_name": "private-order.1"
}
```

**Response (200):**
```json
{
  "auth": "pusher_key:signature"
}
```

---

## 📊 Dashboard

### GET `/api/dashboard`
**Descripción:** Obtiene estadísticas del dashboard según el rol del usuario.

**Autenticación:** Requerida

**Response para Admin (200):**
```json
{
  "success": true,
  "data": {
    "isAdmin": true,
    "stats": {
      "total_orders": 150,
      "pending_orders": 10,
      "total_revenue": 50000.00,
      "total_users": 200,
      "total_products": 50
    },
    "recent_orders": [...],
    "weekly_stats": [...],
    "top_products": [...]
  }
}
```

**Response para Cliente (200):**
```json
{
  "success": true,
  "data": {
    "isAdmin": false,
    "stats": {
      "total_orders": 5,
      "pending_orders": 1,
      "completed_orders": 3,
      "total_spent": 1500.00
    },
    "recent_orders": [...]
  }
}
```

---

## 🛍️ Productos

### GET `/api/products`
**Descripción:** Obtiene productos paginados (20 por página).

**Autenticación:** No requerida

**Query Parameters:**
- `page` (opcional): Número de página

**Response (200):**
```json
{
  "products": {
    "data": [
      {
        "id": 1,
        "name": "Producto Ejemplo",
        "price": 100.00,
        "regular_price": 120.00,
        "sale_price": 100.00,
        "images": [...],
        "categories": [...]
      }
    ],
    "current_page": 1,
    "per_page": 20,
    "total": 100
  },
  "message": "Productos obtenidos correctamente."
}
```

---

### GET `/api/products/listproducts`
**Descripción:** Obtiene todos los productos disponibles (paginados, 20 por página).

**Autenticación:** No requerida

**Response (200):**
```json
{
  "products": {
    "data": [...],
    "current_page": 1,
    "per_page": 20,
    "total": 100
  },
  "message": "Productos obtenidos correctamente."
}
```

---

### GET `/api/products/promo`
**Descripción:** Obtiene productos promocionales (con descuento).

**Autenticación:** No requerida

**Response (200):**
```json
{
  "products": [
    {
      "id": 1,
      "name": "Producto en Oferta",
      "regular_price": 120.00,
      "sale_price": 100.00,
      "images": [...],
      "categories": [...]
    }
  ]
}
```

---

### GET `/api/products/popularProducts`
**Descripción:** Obtiene productos populares ordenados por rating.

**Autenticación:** No requerida

**Response (200):**
```json
{
  "products": [
    {
      "id": 1,
      "name": "Producto Popular",
      "average_rating": 4.5,
      "images": [...]
    }
  ]
}
```

---

### GET `/api/products/salesProducts`
**Descripción:** Obtiene productos más vendidos.

**Autenticación:** No requerida

**Response (200):**
```json
{
  "products": [
    {
      "id": 1,
      "name": "Producto Más Vendido",
      "total_sales": 150,
      "images": [...]
    }
  ]
}
```

---

### GET `/api/products/searchByName`
**Descripción:** Busca productos por nombre con filtros opcionales.

**Autenticación:** No requerida

**Query Parameters:**
- `query` (opcional): Término de búsqueda
- `page` (opcional): Número de página
- `filters[price][min]` (opcional): Precio mínimo
- `filters[price][max]` (opcional): Precio máximo
- `filters[orderBy]` (opcional): Orden (`relevancia`, `menor_precio`, `mayor_precio`)

**Ejemplo:**
```
GET /api/products/searchByName?query=laptop&filters[price][min]=100&filters[price][max]=500&filters[orderBy]=menor_precio
```

**Response (200):**
```json
{
  "data": [...],
  "current_page": 1,
  "per_page": 20,
  "total": 15
}
```

---

### GET `/api/products/{product}`
**Descripción:** Obtiene un producto específico con sus relaciones (imágenes, categorías, productos relacionados).

**Autenticación:** No requerida

**Response (200):**
```json
{
  "id": 1,
  "name": "Producto Ejemplo",
  "description": "Descripción completa",
  "price": 100.00,
  "images": [...],
  "categories": [...],
  "relatedProducts": [...]
}
```

---

### GET `/api/products/syncProducts`
**Descripción:** Sincroniza todos los productos desde WooCommerce.

**Autenticación:** No requerida (debería estar protegida en producción)

**Response (200):**
```json
{
  "message": "Productos sincronizados correctamente."
}
```

---

## 📂 Categorías

### GET `/api/categories`
**Descripción:** Obtiene todas las categorías disponibles.

**Autenticación:** No requerida

**Response (200):**
```json
[
  {
    "id": 1,
    "name": "Electrónica",
    "slug": "electronica",
    "woocommerce_id": 10
  }
]
```

---

## 🛒 Carrito

### POST `/api/cart/add`
**Descripción:** Añade un producto al carrito o incrementa su cantidad si ya existe.

**Autenticación:** Requerida

**Payload:**
```json
{
  "product_id": 1,
  "quantity": 2
}
```

**Campos:**
- `product_id` (requerido): ID del producto
- `quantity` (requerido): Cantidad a añadir (mínimo 1)

**Response (200):**
```json
{
  "message": "Producto añadido al carrito."
}
```

---

### POST `/api/cart/update`
**Descripción:** Actualiza la cantidad de un producto en el carrito. Si la cantidad es 0, elimina el producto.

**Autenticación:** Requerida

**Payload:**
```json
{
  "product_id": 1,
  "quantity": 3
}
```

**Campos:**
- `product_id` (requerido): ID del producto
- `quantity` (requerido): Nueva cantidad (0 para eliminar)

**Response (200):**
```json
{
  "message": "Cantidad del producto actualizada en el carrito."
}
```

---

### POST `/api/cart/remove`
**Descripción:** Elimina un producto del carrito.

**Autenticación:** Requerida

**Payload:**
```json
{
  "product_id": 1
}
```

**Response (200):**
```json
{
  "message": "Producto eliminado del carrito exitosamente."
}
```

---

### GET `/api/cart`
**Descripción:** Obtiene el carrito completo del usuario con totales.

**Autenticación:** Requerida

**Response (200):**
```json
{
  "items": [
    {
      "cart_item_id": 1,
      "product_id": 1,
      "product_name": "Producto Ejemplo",
      "product_price": 100.00,
      "quantity": 2,
      "item_total": 200.00,
      "product_image": "https://example.com/image.jpg"
    }
  ],
  "subtotal": 200.00,
  "total": 200.00,
  "currency": "USD"
}
```

---

## 📍 Direcciones

### POST `/api/addresses/add`
**Descripción:** Crea una nueva dirección de envío.

**Autenticación:** Requerida

**Payload:**
```json
{
  "address_line_1": "Calle Principal 123",
  "address_line_2": "Edificio A, Apartamento 4B",
  "name": "Casa",
  "referencia": "Frente al parque",
  "postal_code": "1010",
  "is_default": true,
  "latitude": 10.4969,
  "longitude": -66.8983
}
```

**Campos:**
- `address_line_1` (requerido): Dirección principal
- `address_line_2` (opcional): Dirección secundaria
- `name` (opcional): Nombre de la dirección (ej: "Casa", "Oficina")
- `referencia` (opcional): Referencias adicionales
- `postal_code` (opcional): Código postal
- `is_default` (opcional): Establecer como dirección por defecto
- `latitude` (opcional): Latitud (-90 a 90)
- `longitude` (opcional): Longitud (-180 a 180)

**Response (201):**
```json
{
  "message": "Dirección guardada exitosamente.",
  "address": {
    "id": 1,
    "user_id": 1,
    "address_line_1": "Calle Principal 123",
    "is_default": true,
    ...
  }
}
```

---

### GET `/api/addresses`
**Descripción:** Obtiene todas las direcciones del usuario.

**Autenticación:** Requerida

**Response (200):**
```json
{
  "addresses": [
    {
      "id": 1,
      "address_line_1": "Calle Principal 123",
      "is_default": true,
      ...
    }
  ]
}
```

---

### GET `/api/addresses/{addressId}`
**Descripción:** Obtiene una dirección específica.

**Autenticación:** Requerida

**Response (200):**
```json
{
  "address": {
    "id": 1,
    "address_line_1": "Calle Principal 123",
    ...
  }
}
```

---

### PUT `/api/addresses/{addressId}`
**Descripción:** Actualiza una dirección existente.

**Autenticación:** Requerida

**Payload:**
```json
{
  "address_line_1": "Nueva Dirección 456",
  "is_default": false
}
```

**Response (200):**
```json
{
  "message": "Dirección actualizada exitosamente.",
  "address": {...}
}
```

---

### DELETE `/api/addresses/{addressId}`
**Descripción:** Elimina una dirección.

**Autenticación:** Requerida

**Response (200):**
```json
{
  "message": "Dirección eliminada exitosamente."
}
```

---

### POST `/api/addresses/{addressId}/set-default`
**Descripción:** Establece una dirección como predeterminada.

**Autenticación:** Requerida

**Response (200):**
```json
{
  "message": "Dirección establecida como predeterminada exitosamente.",
  "address": {...}
}
```

---

### POST `/api/shipping-cost`
**Descripción:** Calcula el costo de envío (stub - pendiente de implementación).

**Autenticación:** Requerida

**Payload:**
```json
{
  "address_id": 1
}
```

**Response (200):**
```json
{
  "shipping_cost": 0.00
}
```

---

## 💳 Checkout y Órdenes

### POST `/api/checkout/create-order`
**Descripción:** Crea una orden desde el carrito del usuario.

**Autenticación:** Requerida

**Payload (multipart/form-data):**
```
address_id: 1
payment_method: manual_transfer
payment_reference: ABC1234567
notes: Por favor entregar en la mañana
payment_proof: [archivo de imagen]
```

**Campos:**
- `address_id` (requerido): ID de la dirección de envío
- `payment_method` (requerido): Método de pago (`manual_transfer`)
- `payment_reference` (requerido): Referencia de pago única
- `notes` (opcional): Notas adicionales
- `payment_proof` (requerido): Archivo de imagen del comprobante (máx. 2MB)

**Response (201):**
```json
{
  "message": "Orden creada exitosamente. Pago pendiente.",
  "order": {
    "id": 1,
    "user_id": 1,
    "subtotal": 200.00,
    "shipping_cost": 0.00,
    "total": 200.00,
    "payment_method": "manual_transfer",
    "payment_reference": "ABC1234567",
    "status": "pending_payment",
    "items": [...],
    "address": {...}
  },
  "payment_instructions": {
    "method": "Transferencia Bancaria",
    "reference": "ABC1234567"
  }
}
```

---

### GET `/api/checkout/order-summary/{orderId}`
**Descripción:** Obtiene el resumen de una orden específica.

**Autenticación:** Requerida

**Response (200):**
```json
{
  "message": "Resumen de la orden.",
  "order": {
    "id": 1,
    "subtotal": 200.00,
    "total": 200.00,
    "status": "pending_payment",
    "items": [...],
    "address": {...}
  }
}
```

---

### POST `/api/checkout/confirm-payment/{orderId}`
**Descripción:** Confirma el pago de una orden (cambia el estado a `paid`).

**Autenticación:** Requerida

**Response (200):**
```json
{
  "message": "Pago confirmado exitosamente. La orden está siendo procesada.",
  "order": {
    "id": 1,
    "status": "paid",
    ...
  }
}
```

---

### GET `/api/orders`
**Descripción:** Obtiene todas las órdenes del usuario autenticado.

**Autenticación:** Requerida

**Query Parameters:**
- `status` (opcional): Filtrar por estado
- `per_page` (opcional): Items por página (default: 15)

**Response (200):**
```json
{
  "message": "Órdenes obtenidas exitosamente.",
  "orders": [...],
  "pagination": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 15,
    "total": 30
  }
}
```

---

### GET `/api/orders/{orderId}`
**Descripción:** Obtiene el detalle completo de una orden.

**Autenticación:** Requerida

**Response (200):**
```json
{
  "message": "Detalle de la orden.",
  "order": {
    "id": 1,
    "status": "paid",
    "items": [...],
    "address": {...},
    "paymentProof": {...}
  }
}
```

---

### GET `/api/orders/{orderId}/delivery-location`
**Descripción:** Obtiene la ubicación GPS del delivery asignado (solo si está en camino o entregado).

**Autenticación:** Requerida

**Response (200):**
```json
{
  "message": "Ubicación del delivery obtenida exitosamente.",
  "order_id": 1,
  "order_status": "on_the_way",
  "delivery": {
    "id": 5,
    "name": "Delivery Name"
  },
  "current_location": {
    "latitude": 10.4969,
    "longitude": -66.8983,
    "updated_at": "2025-01-15T10:30:00Z"
  },
  "location_history": [
    {
      "latitude": 10.4969,
      "longitude": -66.8983,
      "timestamp": "2025-01-15T10:30:00Z"
    }
  ],
  "has_location": true,
  "last_updated": "2025-01-15T10:30:00Z"
}
```

---

## 💬 Chat de Órdenes

### GET `/api/orders/{orderId}/chats`
**Descripción:** Obtiene todos los mensajes del chat de una orden.

**Autenticación:** Requerida

**Response (200):**
```json
{
  "order_id": 1,
  "messages": [
    {
      "id": 1,
      "order_id": 1,
      "user_id": 1,
      "message": "Hola, ¿cuándo llegará mi pedido?",
      "message_type": "text",
      "is_read": false,
      "created_at": "2025-01-15T10:00:00Z",
      "user": {
        "id": 1,
        "name": "Juan Pérez",
        "email": "juan@example.com"
      }
    }
  ],
  "user_role": "client"
}
```

---

### POST `/api/orders/{orderId}/chats`
**Descripción:** Envía un mensaje en el chat de una orden.

**Autenticación:** Requerida

**Payload (multipart/form-data):**
```
message: Hola, ¿cuándo llegará mi pedido?
message_type: text
file: [archivo opcional, máx. 10MB]
```

**Campos:**
- `message` (requerido): Contenido del mensaje (máx. 1000 caracteres)
- `message_type` (opcional): Tipo de mensaje (`text`, `image`, `file`)
- `file` (opcional): Archivo adjunto (máx. 10MB)

**Response (202):**
```json
{
  "message": "Mensaje en proceso de envío",
  "status": "processing"
}
```

---

### PUT `/api/orders/{orderId}/chats/mark-read`
**Descripción:** Marca todos los mensajes como leídos.

**Autenticación:** Requerida

**Response (200):**
```json
{
  "message": "Mensajes marcados como leídos",
  "updated_count": 5
}
```

---

### GET `/api/orders/{orderId}/chats/stats`
**Descripción:** Obtiene estadísticas del chat.

**Autenticación:** Requerida

**Response (200):**
```json
{
  "stats": {
    "total_messages": 10,
    "unread_messages": 2,
    "delivery_messages": 3,
    "pre_delivery_messages": 7,
    "last_message_at": "2025-01-15T10:30:00Z"
  }
}
```

---

### GET `/api/orders/{orderId}/chats/attachment/{messageId}`
**Descripción:** Descarga un archivo adjunto de un mensaje.

**Autenticación:** Requerida

**Response (200):** Archivo binario con headers apropiados

---

## 👨‍💼 Administración

**Nota:** Todos los endpoints de administración requieren rol `admin` o `super_admin`.

### GET `/api/admin/orders`
**Descripción:** Lista todas las órdenes con filtros y paginación.

**Autenticación:** Requerida (Admin)

**Query Parameters:**
- `per_page` (opcional): Items por página (default: 20)
- `status` (opcional): Filtrar por estado (`all`, `pending_payment`, `paid`, etc.)
- `payment_method` (opcional): Filtrar por método de pago
- `search` (opcional): Buscar por referencia, nombre de usuario, etc.
- `date_from` (opcional): Fecha desde (YYYY-MM-DD)
- `date_to` (opcional): Fecha hasta (YYYY-MM-DD)

**Response (200):**
```json
{
  "success": true,
  "data": {
    "data": [...],
    "current_page": 1,
    "per_page": 20,
    "total": 100
  }
}
```

---

### GET `/api/admin/orders/statistics`
**Descripción:** Obtiene estadísticas de órdenes para el dashboard.

**Autenticación:** Requerida (Admin)

**Query Parameters:**
- `date_from` (opcional): Fecha desde
- `date_to` (opcional): Fecha hasta

**Response (200):**
```json
{
  "success": true,
  "data": {
    "stats": {
      "total_orders": 150,
      "pending_payment": 10,
      "paid": 50,
      "shipped": 30,
      "completed": 40,
      "cancelled": 10,
      "refunded": 10,
      "total_revenue": 50000.00,
      "pending_revenue": 5000.00
    },
    "payment_methods": [...],
    "last_7_days": [...]
  }
}
```

---

### GET `/api/admin/orders/{id}`
**Descripción:** Obtiene el detalle de una orden específica.

**Autenticación:** Requerida (Admin)

**Response (200):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "user": {...},
    "address": {...},
    "items": [...],
    "paymentProof": {...}
  }
}
```

---

### PUT `/api/admin/orders/{id}/status`
**Descripción:** Actualiza el estado de una orden.

**Autenticación:** Requerida (Admin)

**Payload:**
```json
{
  "status": "paid",
  "notes": "Pago verificado correctamente"
}
```

**Estados válidos:** `pending_payment`, `paid`, `shipped`, `completed`, `cancelled`, `refunded`

**Response (200):**
```json
{
  "success": true,
  "message": "Estado actualizado correctamente",
  "data": {
    "order": {...},
    "previous_status": "pending_payment"
  }
}
```

---

### PUT `/api/admin/orders/{id}/notes`
**Descripción:** Actualiza las notas internas de una orden.

**Autenticación:** Requerida (Admin)

**Payload:**
```json
{
  "notes": "Cliente solicitó entrega en la mañana"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Notas actualizadas correctamente",
  "data": {...}
}
```

---

### GET `/api/admin/orders/{id}/payment-proof`
**Descripción:** Obtiene el comprobante de pago de una orden.

**Autenticación:** Requerida (Admin)

**Response (200):**
```json
{
  "success": true,
  "data": {
    "payment_proof": {
      "id": 1,
      "order_id": 1,
      "file_path": "payment_proofs/xxxxx.jpg",
      "notes": "..."
    },
    "url": "/storage/payment_proofs/xxxxx.jpg"
  }
}
```

---

### DELETE `/api/admin/orders/{id}`
**Descripción:** Elimina una orden (solo si está en `pending_payment` o `cancelled`).

**Autenticación:** Requerida (Admin)

**Response (200):**
```json
{
  "success": true,
  "message": "Orden eliminada correctamente"
}
```

---

### GET `/api/admin/orders/user/{userId}`
**Descripción:** Obtiene todas las órdenes de un usuario específico.

**Autenticación:** Requerida (Admin)

**Response (200):**
```json
{
  "success": true,
  "data": {
    "data": [...],
    "current_page": 1,
    "per_page": 20,
    "total": 5
  }
}
```

---

### GET `/api/admin/deliveries`
**Descripción:** Lista todos los usuarios con rol delivery.

**Autenticación:** Requerida (Admin)

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 5,
      "name": "Delivery Name",
      "email": "delivery@example.com",
      "tel": "04121234567",
      "active_orders": 2,
      "total_deliveries": 10
    }
  ]
}
```

---

### POST `/api/admin/deliveries`
**Descripción:** Crea un nuevo usuario delivery.

**Autenticación:** Requerida (Admin)

**Payload:**
```json
{
  "name": "Delivery Name",
  "email": "delivery@example.com",
  "password": "password123",
  "tel": "04121234567",
  "cedula_type": "v",
  "cedula_ID": "12345678"
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "Delivery creado exitosamente",
  "data": {...}
}
```

---

### POST `/api/admin/deliveries/assign/{orderId}`
**Descripción:** Asigna un delivery a una orden.

**Autenticación:** Requerida (Admin)

**Payload:**
```json
{
  "delivery_id": 5
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Delivery asignado exitosamente",
  "data": {
    "id": 1,
    "delivery": {
      "id": 5,
      "name": "Delivery Name"
    }
  }
}
```

---

### DELETE `/api/admin/deliveries/unassign/{orderId}`
**Descripción:** Desasigna el delivery de una orden.

**Autenticación:** Requerida (Admin)

**Response (200):**
```json
{
  "success": true,
  "message": "Delivery desasignado exitosamente"
}
```

---

### GET `/api/admin/deliveries/{deliveryId}/orders`
**Descripción:** Obtiene todas las órdenes asignadas a un delivery.

**Autenticación:** Requerida (Admin)

**Response (200):**
```json
{
  "success": true,
  "data": [...]
}
```

---

### DELETE `/api/admin/deliveries/{id}`
**Descripción:** Elimina un delivery (solo si no tiene órdenes activas).

**Autenticación:** Requerida (Admin)

**Response (200):**
```json
{
  "success": true,
  "message": "Delivery eliminado exitosamente"
}
```

---

### GET `/api/admin/chat/{orderId}/messages`
**Descripción:** Obtiene todos los mensajes del chat de una orden (vista admin).

**Autenticación:** Requerida (Admin)

**Response (200):**
```json
{
  "success": true,
  "data": [...]
}
```

---

### POST `/api/admin/chat/{orderId}/messages`
**Descripción:** Envía un mensaje en el chat de una orden (como admin).

**Autenticación:** Requerida (Admin)

**Payload:**
```json
{
  "message": "Su pedido está en camino"
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "Mensaje enviado",
  "data": {...}
}
```

---

### POST `/api/admin/chat/{orderId}/read`
**Descripción:** Marca mensajes como leídos (admin).

**Autenticación:** Requerida (Admin)

**Response (200):**
```json
{
  "success": true,
  "message": "Mensajes marcados como leídos"
}
```

---

### GET `/api/admin/chat/{orderId}/unread`
**Descripción:** Obtiene la cantidad de mensajes no leídos.

**Autenticación:** Requerida (Admin)

**Response (200):**
```json
{
  "success": true,
  "data": {
    "unread_count": 5
  }
}
```

---

## 🚚 Delivery

**Nota:** Todos los endpoints de delivery requieren rol `delivery`.

### GET `/api/delivery/orders`
**Descripción:** Lista las órdenes asignadas al delivery autenticado.

**Autenticación:** Requerida (Delivery)

**Query Parameters:**
- `status` (opcional): Filtrar por estado
- `sos_only` (opcional): Solo órdenes con SOS activo

**Response (200):**
```json
[
  {
    "id": 1,
    "numero": "PED-001",
    "usuario": "Juan Pérez",
    "status": "En camino",
    "direccion": "Calle Principal 123",
    "latitud": 10.4969,
    "longitud": -66.8983,
    "productos": [
      {
        "id": 1,
        "nombre": "Producto Ejemplo",
        "cantidad": 2,
        "precio": 100.00
      }
    ]
  }
]
```

---

### PUT `/api/delivery/orders/{orderId}/update-status`
**Descripción:** Actualiza el estado del pedido (`on_the_way` o `delivered`).

**Autenticación:** Requerida (Delivery)

**Payload:**
```json
{
  "status": "on_the_way",
  "comment": "Salí de la agencia",
  "latitude": 10.4969,
  "longitude": -66.8983
}
```

**Campos:**
- `status` (requerido): Nuevo estado (`on_the_way` o `delivered`)
- `comment` (opcional): Comentario sobre el cambio
- `latitude` (opcional): Latitud inicial (requerida si cambia a `on_the_way`)
- `longitude` (opcional): Longitud inicial (requerida si cambia a `on_the_way`)

**Response (200):**
```json
{
  "success": true,
  "message": "Estado actualizado exitosamente",
  "order": {...}
}
```

---

### POST `/api/delivery/orders/{orderId}/update-location`
**Descripción:** Actualiza la ubicación en tiempo real del delivery (solo si está en camino).

**Autenticación:** Requerida (Delivery)

**Payload:**
```json
{
  "latitude": 10.4969,
  "longitude": -66.8983
}
```

**Response (200):**
```json
{
  "message": "Ubicación actualizada exitosamente",
  "location": {
    "id": 1,
    "order_id": 1,
    "latitude": 10.4969,
    "longitude": -66.8983,
    "created_at": "2025-01-15T10:30:00Z"
  }
}
```

---

### POST `/api/delivery/orders/{orderId}/sos`
**Descripción:** Activa SOS para un pedido (notifica al admin).

**Autenticación:** Requerida (Delivery)

**Payload:**
```json
{
  "comment": "Necesito ayuda urgente"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "SOS activado exitosamente. El administrador ha sido notificado.",
  "data": {
    "id": 1,
    "numero": "PED-001",
    "sos_status": true,
    "sos_comment": "Necesito ayuda urgente",
    "sos_reported_at": "2025-01-15T10:30:00Z",
    "status": "En camino"
  }
}
```

---

## 🧪 Testing

### GET `/api/test/send-message/{orderId}`
**Descripción:** Endpoint de prueba para enviar mensajes sin autenticación (solo desarrollo).

**Autenticación:** No requerida

**Query Parameters:**
- `message`: Contenido del mensaje
- `user_id`: ID del usuario

**Ejemplo:**
```
GET /api/test/send-message/14?message=Hola desde test&user_id=1
```

---

## 📝 Notas Importantes

1. **Autenticación:** La mayoría de endpoints requieren token Bearer. Obtén el token mediante `/api/auth/login` o `/api/auth/register`.

2. **Roles:** 
   - `client`: Usuario normal
   - `delivery`: Usuario delivery
   - `admin` / `super_admin`: Administradores

3. **Estados de Orden:**
   - `pending_payment`: Pago pendiente
   - `paid`: Pagado
   - `shipped`: Enviado
   - `on_the_way`: En camino
   - `delivered`: Entregado
   - `completed`: Completado
   - `cancelled`: Cancelado
   - `refunded`: Reembolsado

4. **Códigos de Estado HTTP:**
   - `200`: Éxito
   - `201`: Creado
   - `202`: Aceptado (procesando)
   - `400`: Solicitud incorrecta
   - `401`: No autenticado
   - `403`: Sin permisos
   - `404`: No encontrado
   - `422`: Error de validación
   - `500`: Error del servidor

5. **Paginación:** Los endpoints de listado usan paginación de Laravel. Por defecto, 15-20 items por página.

6. **Archivos:** Los endpoints que aceptan archivos usan `multipart/form-data`.

7. **Broadcasting:** El sistema usa Pusher para eventos en tiempo real. Autentica con `/api/broadcasting/auth`.

---

## 🔗 Variables de Entorno

Asegúrate de configurar estas variables en tu `.env`:

```env
APP_URL=http://localhost:8000
SANCTUM_STATEFUL_DOMAINS=localhost:3000,127.0.0.1:8000
```

---

**Última actualización:** Enero 2025

