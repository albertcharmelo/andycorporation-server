# Setup del Panel de Administración

## Pasos para ver el enlace de "Gestión de Órdenes" en el navbar

### 1. Ejecutar las migraciones y seeders (si no lo has hecho)

```bash
# Ejecutar migraciones
php artisan migrate

# Ejecutar seeders (crea roles, permisos y usuario admin)
php artisan db:seed
```

**IMPORTANTE:** El comando `php artisan db:seed` creará automáticamente:
- Roles: `client`, `delivery`, `seller`, `admin`, `super_admin`
- Permisos necesarios
- Usuario administrador con las siguientes credenciales:

```
Email: admin@andycorporation.com
Password: password
```

### 2. Opción alternativa: Asignar rol admin a tu usuario actual

Si ya tienes un usuario y no quieres crear uno nuevo, puedes asignar el rol admin manualmente:

```bash
php artisan tinker
```

Dentro de tinker:

```php
$user = \App\Models\User::where('email', 'tu-email@ejemplo.com')->first();
$user->assignRole('admin');
exit
```

### 3. Iniciar sesión

1. Ve a: http://127.0.0.1:8000/login
2. Ingresa las credenciales del usuario admin (o tu usuario con rol admin)
3. Serás redirigido al dashboard

### 4. Verificar el enlace en el navbar

Una vez autenticado como admin, verás:

**En el Navbar Superior:**
- Dashboard
- **Gestión de Órdenes** ← Nuevo enlace (con ícono de clipboard)

**En el Sidebar Izquierdo:**
- Dashboard
- **Gestión de Órdenes** ← Nuevo enlace

### 5. Acceder al panel de órdenes

Haz clic en "Gestión de Órdenes" y serás dirigido a:
```
http://127.0.0.1:8000/admin/orders
```

## Funcionalidades Disponibles

### En /admin/orders (Lista de Órdenes):
- ✅ Ver todas las órdenes del sistema
- ✅ Filtrar por estado (pending_payment, paid, shipped, completed, cancelled, refunded)
- ✅ Filtrar por método de pago
- ✅ Buscar por referencia de pago o nombre/email del cliente
- ✅ Paginación automática (20 órdenes por página)
- ✅ Ver detalles completos de cada orden

### En /admin/orders/{id} (Detalle de Orden):
- ✅ Ver información completa del cliente
- ✅ Ver dirección de envío con coordenadas
- ✅ Ver todos los productos de la orden
- ✅ Ver comprobante de pago (imagen)
- ✅ Actualizar estado de la orden
- ✅ Agregar notas internas con timestamp
- ✅ Ver historial de cambios

## Solución de Problemas

### No veo el enlace "Gestión de Órdenes"

**Causa:** Tu usuario no tiene el rol admin asignado.

**Solución:**
```bash
php artisan tinker
```

```php
$user = \App\Models\User::find(1); // Cambia 1 por tu ID de usuario
$user->assignRole('admin');
$user->refresh();
dd($user->getRoleNames()); // Debe mostrar ['admin']
```

### Error "No tienes permisos para acceder a este recurso"

**Causa:** El middleware está bloqueando el acceso.

**Solución:**
Verifica que los roles existen:
```bash
php artisan tinker
```

```php
\Spatie\Permission\Models\Role::all()->pluck('name');
// Debe mostrar: ['client', 'delivery', 'seller', 'admin', 'super_admin']
```

Si no existen, ejecuta:
```bash
php artisan db:seed --class=RoleAndPermissionsSeeder
```

### Error al cargar la página de órdenes

**Causa:** Puede que no haya órdenes en la base de datos.

**Solución:** La página funcionará aunque no haya órdenes, mostrará un mensaje "No se encontraron órdenes".

Para crear órdenes de prueba, usa la aplicación cliente (móvil o web) para hacer pedidos.

## Comandos Útiles

```bash
# Ver todos los usuarios y sus roles
php artisan tinker
\App\Models\User::with('roles')->get()->map(fn($u) => [$u->email, $u->getRoleNames()]);

# Crear usuario admin manualmente
php artisan tinker
$user = \App\Models\User::create([
    'name' => 'Admin',
    'email' => 'admin@test.com',
    'password' => bcrypt('password'),
    'email_verified_at' => now(),
]);
$user->assignRole('admin');

# Verificar roles de un usuario
php artisan tinker
\App\Models\User::find(1)->getRoleNames();

# Ver todas las órdenes
php artisan tinker
\App\Models\Order::with('user')->get();
```

## Rutas Disponibles

### Web (Inertia):
- `GET /admin/orders` - Lista de órdenes
- `GET /admin/orders/{id}` - Detalle de orden
- `PUT /admin/orders/{id}/status` - Actualizar estado
- `PUT /admin/orders/{id}/notes` - Agregar notas

### API (JSON):
- `GET /api/admin/orders` - Lista de órdenes con filtros
- `GET /api/admin/orders/statistics` - Estadísticas del dashboard
- `GET /api/admin/orders/{id}` - Detalle de orden
- `PUT /api/admin/orders/{id}/status` - Actualizar estado
- `PUT /api/admin/orders/{id}/notes` - Agregar notas
- `GET /api/admin/orders/{id}/payment-proof` - Ver comprobante
- `DELETE /api/admin/orders/{id}` - Eliminar orden
- `GET /api/admin/orders/user/{userId}` - Órdenes de un usuario

Todas las rutas de admin requieren autenticación y rol admin o super_admin.
