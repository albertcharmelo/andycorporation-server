# Setup de Deliveries y Chat - Instrucciones

## 🚀 Pasos para activar las nuevas funcionalidades

### 1. Ejecutar las nuevas migraciones

```bash
php artisan migrate
```

Esto creará:
- Campo `delivery_id`, `assigned_at`, `delivered_at` en la tabla `orders`
- Tabla `messages` para el chat entre admin y clientes

### 2. Ejecutar los seeders de datos de prueba

```bash
php artisan db:seed --class=TestDataSeeder
```

O ejecutar todos los seeders:
```bash
php artisan db:seed
```

Esto creará:
- **1 Usuario Cliente**: `cliente@test.com` / `password`
- **1 Usuario Delivery**: `delivery@test.com` / `password`
- **10 Órdenes de prueba** con diferentes estados
- **Dirección de envío** para el cliente
- **Items en el carrito** del cliente
- **Asignaciones de delivery** a algunas órdenes

## 📋 Nuevas Funcionalidades Implementadas

### ✅ Sistema de Deliveries

**Backend:**
- Modelo `User` actualizado con relación `assignedOrders()`
- Controlador `DeliveryController` con las siguientes funciones:
  - Listar deliveries con estadísticas
  - Crear nuevo delivery
  - Asignar delivery a orden
  - Desasignar delivery
  - Ver órdenes de un delivery
  - Eliminar delivery (solo si no tiene órdenes activas)

**Endpoints API:**
```
GET    /api/admin/deliveries                 - Listar deliveries
POST   /api/admin/deliveries                 - Crear delivery
POST   /api/admin/deliveries/assign/{id}     - Asignar a orden
DELETE /api/admin/deliveries/unassign/{id}   - Desasignar de orden
GET    /api/admin/deliveries/{id}/orders     - Órdenes de un delivery
DELETE /api/admin/deliveries/{id}            - Eliminar delivery
```

### ✅ Sistema de Chat

**Backend:**
- Modelo `Message` para mensajes del chat
- Controlador `ChatController` con:
  - Obtener mensajes de una orden
  - Enviar mensaje
  - Marcar como leído
  - Contar no leídos

**Endpoints API:**
```
GET  /api/admin/chat/{orderId}/messages      - Obtener mensajes
POST /api/admin/chat/{orderId}/messages      - Enviar mensaje
POST /api/admin/chat/{orderId}/read          - Marcar como leído
GET  /api/admin/chat/{orderId}/unread        - Contar no leídos
```

### ✅ Modelo Order Actualizado

Nuevos campos:
- `delivery_id` - ID del delivery asignado
- `assigned_at` - Cuándo se asignó el delivery
- `delivered_at` - Cuándo se marcó como entregada

Nuevas relaciones:
- `delivery()` - Usuario delivery asignado
- `messages()` - Mensajes del chat
- `unreadMessages()` - Mensajes no leídos

Nuevos métodos:
- `assignDelivery($deliveryId)` - Asignar delivery
- `markAsDelivered()` - Marcar como entregada

## 🎯 Cómo Usar

### Crear un Delivery (API)

```bash
POST /api/admin/deliveries
Content-Type: application/json
Authorization: Bearer {token}

{
  "name": "Juan Pérez",
  "email": "juan@delivery.com",
  "password": "password",
  "tel": "04141234567",
  "cedula_type": "v",
  "cedula_ID": "12345678"
}
```

### Asignar Delivery a una Orden

```bash
POST /api/admin/deliveries/assign/1
Content-Type: application/json
Authorization: Bearer {token}

{
  "delivery_id": 2
}
```

### Enviar Mensaje en el Chat

```bash
POST /api/admin/chat/1/messages
Content-Type: application/json
Authorization: Bearer {token}

{
  "message": "Hola, ¿cómo va tu pedido?"
}
```

### Ver Mensajes de una Orden

```bash
GET /api/admin/chat/1/messages
Authorization: Bearer {token}
```

## 🔐 Permisos

Todas las rutas de `/api/admin/*` requieren:
- Autenticación con Sanctum (`auth:sanctum`)
- Rol admin o super_admin (`role:admin,super_admin`)

Los clientes pueden:
- Ver mensajes de sus propias órdenes
- Enviar mensajes en el chat de sus órdenes

## 🧪 Pruebas

### 1. Login como Admin

```bash
POST /api/auth/login

{
  "email": "admin@andycorporation.com",
  "password": "password"
}
```

### 2. Ver lista de deliveries

```bash
GET /api/admin/deliveries
Authorization: Bearer {token}
```

### 3. Ver órdenes

```bash
GET /api/admin/orders
Authorization: Bearer {token}
```

### 4. Asignar delivery a orden #1

```bash
POST /api/admin/deliveries/assign/1
Authorization: Bearer {token}

{
  "delivery_id": 2
}
```

### 5. Enviar mensaje en orden #1

```bash
POST /api/admin/chat/1/messages
Authorization: Bearer {token}

{
  "message": "Tu pedido está en camino"
}
```

## 📊 Datos de Prueba Creados

El seeder crea automáticamente:

**Usuarios:**
- Admin: `admin@andycorporation.com` / `password`
- Cliente: `cliente@test.com` / `password`
- Delivery: `delivery@test.com` / `password`

**Órdenes:**
- 10 órdenes con estados variados
- Algunas con delivery asignado
- Fechas escalonadas en los últimos 30 días

**Productos:**
- Si no existen productos, crea 5 de prueba

## 🔄 Reset de Datos

Para reiniciar todo desde cero:

```bash
# Advertencia: Esto borrará TODOS los datos
php artisan migrate:fresh --seed
```

## 📝 Notas Importantes

1. **Asignación Automática de Estado:**
   - Al asignar un delivery a una orden en estado `pending_payment` o `paid`, automáticamente cambia a `shipped`

2. **Eliminación de Delivery:**
   - Solo se puede eliminar si no tiene órdenes activas (estados `paid` o `shipped`)
   - Las órdenes completadas o canceladas no bloquean la eliminación

3. **Chat:**
   - Los mensajes son por orden
   - Admin puede ver/enviar en cualquier orden
   - Clientes solo en sus propias órdenes
   - Se marca automáticamente quién envió el mensaje

4. **Permisos del Chat:**
   - Admin/Super Admin: acceso total
   - Cliente: solo sus órdenes
   - Delivery: puede implementarse acceso a órdenes asignadas

## 🎨 Frontend (Pendiente)

Para completar la funcionalidad, falta implementar:

1. **Página de Gestión de Deliveries** (`/admin/deliveries`)
   - Lista de deliveries
   - Crear nuevo delivery
   - Ver estadísticas de cada delivery
   - Eliminar delivery

2. **Componente de Chat en OrderDetail**
   - Lista de mensajes
   - Input para enviar mensajes
   - Indicador de mensajes no leídos
   - Auto-scroll al último mensaje

3. **Selector de Delivery en OrderDetail**
   - Dropdown para seleccionar delivery
   - Botón para asignar/desasignar
   - Mostrar delivery actual si existe

## 💡 Próximos Pasos Sugeridos

1. Ejecutar migraciones y seeders
2. Probar endpoints con Postman o Insomnia
3. Implementar frontend Vue para deliveries y chat
4. Agregar notificaciones en tiempo real (opcional)
5. Implementar tracking en vivo para deliveries (opcional)
