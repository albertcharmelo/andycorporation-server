# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a modern full-stack e-commerce application built with Laravel 12 + Vue 3 + Inertia.js. The application features:
- Dual authentication system (session-based for web, token-based for API)
- WooCommerce integration for product catalog synchronization
- Shopping cart and checkout flow with payment proof uploads
- Role-based access control via Spatie Permissions
- Server-side rendering (SSR) support with Inertia.js

## Development Commands

### Running the Development Environment

```bash
# Start all development servers concurrently (Laravel + Queue + Vite)
composer run dev

# Start with SSR support (includes Laravel + Queue + Logs + SSR)
composer run dev:ssr

# Run individual processes
php artisan serve              # Laravel development server (port 8000)
php artisan queue:listen       # Background queue worker
npm run dev                    # Vite development server
php artisan inertia:start-ssr  # Inertia SSR server
```

### Testing

```bash
# Run all tests
composer run test
# Or directly
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# Run specific test file
php artisan test tests/Feature/ExampleTest.php

# Run with coverage
php artisan test --coverage
```

### Code Quality

```bash
# Format frontend code
npm run format              # Format with Prettier
npm run format:check        # Check formatting without writing

# Lint frontend code
npm run lint                # ESLint with auto-fix

# Format backend code (Laravel Pint)
./vendor/bin/pint           # Auto-fix all PHP files
./vendor/bin/pint --test    # Check without fixing
```

### Building for Production

```bash
# Build frontend assets
npm run build               # Standard build

# Build with SSR support
npm run build:ssr           # Builds both client and SSR bundles

# Optimize Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

### Database Operations

```bash
# Run migrations
php artisan migrate

# Rollback migrations
php artisan migrate:rollback

# Fresh database with seeding
php artisan migrate:fresh --seed

# Create new migration
php artisan make:migration create_table_name

# Create model with migration and factory
php artisan make:model ModelName -mf
```

### Common Artisan Commands

```bash
# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Generate application key
php artisan key:generate

# Create symbolic link for storage
php artisan storage:link

# View logs in real-time
php artisan pail
```

## Architecture & Key Patterns

### Dual Route System

The application maintains two separate routing systems:

**Web Routes** (`routes/web.php`, `routes/auth.php`, `routes/settings.php`):
- Session-based authentication with Laravel's default guards
- Returns Inertia.js responses that render Vue components
- Middleware: `auth`, `verified`, `guest`
- Example: Dashboard, Profile, Settings pages

**API Routes** (`routes/api.php`):
- Token-based authentication using Laravel Sanctum
- Returns JSON responses for mobile/external clients
- Middleware: `auth:sanctum`
- RESTful endpoints for products, cart, checkout, addresses

### Authentication System

The app uses a hybrid authentication approach:

1. **Web Authentication**: Laravel session-based auth for browser users
   - Login/Register controllers in `app/Http/Controllers/Auth/`
   - Email verification via signed URLs
   - Password reset with tokens

2. **API Authentication**: Laravel Sanctum for token-based auth
   - API auth endpoints in `app/Http/Controllers/API/AuthController.php`
   - Tokens created on login: `$user->createToken("api-token")->plainTextToken`
   - Bearer token required in Authorization header

3. **Authorization**: Spatie Permissions package for RBAC
   - User model has `HasRoles` trait
   - Default role 'client' assigned on API registration
   - Guards configured for 'api' in User model

### Data Snapshot Pattern

Critical for price integrity in e-commerce:

**CartItem** model stores `price_at_purchase` to capture the product price when added to cart, preventing price changes from affecting items already in the user's cart.

**OrderItem** model stores both `product_name` and `price_at_purchase` separately from the Product model. This ensures order history remains accurate even if products are renamed, repriced, or deleted.

Example flow:
1. Product price is $100
2. User adds to cart → CartItem.price_at_purchase = $100
3. Admin changes product price to $120
4. User checks out → OrderItem.price_at_purchase = $100 (from CartItem)
5. Cart is cleared after order
6. Order shows $100 regardless of current product price

### Transaction Management

All multi-step operations use database transactions to ensure data consistency:

```php
DB::beginTransaction();
try {
    // Multiple database operations
    DB::commit();
} catch (\Exception $e) {
    DB::rollback();
    throw $e;
}
```

Common in:
- Cart operations (add/update/remove)
- Checkout flow (create order + order items + payment proof + clear cart)
- WooCommerce product sync (product + images + categories)
- Address management (setting default address requires updating others)

### WooCommerce Integration

Uses `codexshaper/laravel-woocommerce` package to sync products from an external WooCommerce store.

**Sync Endpoints**:
- Web: `/syncProducts` (Inertia page)
- API: `GET /api/products/syncProducts` (batch sync, paginated 100/page)
- API: `GET /api/products/{wooid}/syncSingleProduct` (single product)

**Sync Process**:
1. Fetch products from WooCommerce API
2. For each product, start DB transaction
3. Update or create Product record (keyed by `woocommerce_id`)
4. Delete existing ProductImages and recreate from WooCommerce data
5. Sync categories (many-to-many relationship)
6. Sync related products (stored as JSON array in `related_products` field)
7. Commit transaction

**Important Notes**:
- Products are stored locally for fast queries
- `woocommerce_id` is unique and used as the sync key
- Stock status is an enum: `instock`, `outofstock`, `onbackorder`
- Price fields handle null values gracefully with `is_numeric()` checks

### Frontend Architecture (Vue + Inertia)

**Component Hierarchy**:
```
app.ts (Inertia initialization)
  └─ AppShell.vue (root wrapper)
      ├─ AppSidebar.vue (navigation)
      │   ├─ NavMain.vue (menu items)
      │   ├─ NavUser.vue (user dropdown)
      │   └─ NavFooter.vue
      └─ AppContent
          ├─ AppHeader.vue (breadcrumbs + user info)
          └─ [Page Component] (Dashboard, Profile, etc.)
```

**Shared Data via HandleInertiaRequests**:
All Inertia pages automatically receive:
- `auth.user`: Currently authenticated user object
- `ziggy`: Route helper for type-safe URL generation in Vue
- `quote`: Random inspiring quote on page load
- `sidebarOpen`: Sidebar state (persisted in cookies)

**Path Aliases**:
- `@/` → `resources/js/`
- `ziggy-js` → `vendor/tightenco/ziggy`

Example usage in Vue:
```vue
<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { route } from 'ziggy-js'

const { auth } = defineProps<{ auth: { user: User } }>()
</script>
```

**Theme System**:
The `useAppearance()` composable manages light/dark/system theme modes:
- Persisted to localStorage + cookie (for SSR)
- System preference detection via `window.matchMedia()`
- Tailwind dark mode toggled via DOM class

### Model Relationships Reference

```
User
├─ hasMany(CartItem)
├─ hasMany(Order)
├─ hasMany(UserAddress)
└─ spatie hasRoles()

Product
├─ belongsToMany(Category) via category_product pivot
├─ hasMany(ProductImage)
├─ hasMany(CartItem)
├─ hasMany(OrderItem)
└─ belongsToMany(Product, 'product_related') self-referential

Order
├─ belongsTo(User)
├─ belongsTo(UserAddress)
├─ hasMany(OrderItem)
└─ hasOne(PaymentProof)

CartItem
├─ belongsTo(User)
└─ belongsTo(Product)

OrderItem
├─ belongsTo(Order)
└─ belongsTo(Product) [soft reference, not enforced FK]

UserAddress
├─ belongsTo(User)
└─ hasMany(Order)

PaymentProof
└─ belongsTo(Order)
```

## File Structure Conventions

### Controllers

**API Controllers** (`app/Http/Controllers/API/`):
- Stateless, token-authenticated endpoints
- Return JSON responses
- Use `$request->validate()` for inline validation
- Example: `AuthController`, `CartController`, `CheckoutController`

**Web Controllers** (`app/Http/Controllers/`):
- Session-authenticated endpoints
- Return Inertia responses: `Inertia::render('PageName', $props)`
- Use Form Request classes for validation
- Example: `ProfileController`, `PasswordController`

### Frontend Structure

**Pages** (`resources/js/pages/`):
- Top-level Inertia page components
- Receive props from controllers
- Use layouts for consistent structure
- Example: `Dashboard.vue`, `auth/Login.vue`, `settings/Profile.vue`

**Layouts** (`resources/js/layouts/`):
- Wrapper components for pages
- `AppLayout.vue`: Main authenticated layout
- `AuthLayout.vue`: Guest pages (login, register)
- Layout-specific sub-layouts in subdirectories

**Components** (`resources/js/components/`):
- Reusable UI components
- `ui/`: shadcn/ui component library (Button, Input, Card, etc.)
- Feature components: `AppSidebar`, `AppHeader`, `Breadcrumbs`

**Composables** (`resources/js/composables/`):
- Reusable Vue composition functions
- `useAppearance()`: Theme management
- `useInitials()`: Generate user initials for avatars

**Types** (`resources/js/types/`):
- TypeScript type definitions
- `index.d.ts`: Core types (User, Auth, PageProps, SharedData)
- `globals.d.ts`: Global type augmentations

### Migrations

Follow this pattern for clear, maintainable migrations:

```php
Schema::create('table_name', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    // ... other columns
    $table->timestamps();
});
```

**Important Constraints**:
- Use `constrained()` for foreign keys with automatic table detection
- Use `cascadeOnDelete()` for related records that should be deleted
- Use `nullOnDelete()` for optional relationships
- Add unique constraints: `$table->unique(['user_id', 'product_id'])`

## API Endpoint Reference

### Product Endpoints (Public)

```
GET /api/products                      - Paginated products (20/page)
GET /api/products/promo                - Sale items (sale_price < regular_price)
GET /api/products/popular              - Sorted by average_rating
GET /api/products/salesProducts        - Sorted by total_sales
GET /api/products/{id}                 - Single product + related products
GET /api/products/searchByName         - Search with filters
    Query params: text, categories[], minPrice, maxPrice
GET /api/categories                    - All categories

GET /api/products/syncProducts         - Sync all from WooCommerce
GET /api/products/{wooid}/syncSingleProduct - Sync single product
```

### Cart Endpoints (Authenticated)

```
POST /api/cart/add                     - Add product (increments if exists)
    Body: { product_id, quantity }
POST /api/cart/update                  - Update quantity (0 removes)
    Body: { product_id, quantity }
POST /api/cart/remove                  - Remove product
    Body: { product_id }
GET /api/cart                          - View cart with totals
```

### Address Endpoints (Authenticated)

```
POST /api/addresses/add                - Create new address
    Body: { address_line_1, address_line_2?, postal_code, name,
            referencia?, latitude, longitude }
GET /api/addresses                     - List all user addresses
GET /api/addresses/{id}                - Get single address
PUT /api/addresses/{id}                - Update address
DELETE /api/addresses/{id}             - Delete address
POST /api/addresses/{id}/set-default   - Mark as default
```

### Checkout Endpoints (Authenticated)

```
POST /api/checkout/create-order        - Create order from cart
    Body: { address_id, payment_method, payment_proof (file), notes? }
    Payment methods: manual_transfer, mobile_payment, credit_card,
                    paypal, binance
    Returns: { message, order_id, payment_reference }

GET /api/checkout/order-summary/{id}   - Get order details
POST /api/checkout/confirm-payment/{id} - Mark order as 'paid'
POST /api/shipping-cost                - Calculate shipping (stub)
```

### Authentication Endpoints

```
POST /api/auth/register                - Create account
    Body: { name, email, password, password_confirmation, tel,
            cedula_type, cedula_ID }
    Returns: { message, token, user }
    Note: Auto-assigns 'client' role

POST /api/auth/login                   - Get API token
    Body: { email, password }
    Returns: { message, token, user }
```

## Environment Configuration

### Required Environment Variables

```env
# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=database_name
DB_USERNAME=root
DB_PASSWORD=

# WooCommerce Integration
WOOCOMMERCE_STORE_URL=https://your-store.com
WOOCOMMERCE_CONSUMER_KEY=ck_xxxxx
WOOCOMMERCE_CONSUMER_SECRET=cs_xxxxx
WOOCOMMERCE_API_VERSION=wc/v3

# Sanctum (API Authentication)
SANCTUM_STATEFUL_DOMAINS=localhost:3000,127.0.0.1:8000

# Mail (for email verification)
MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
```

### First-Time Setup

```bash
# 1. Install PHP dependencies
composer install

# 2. Install Node dependencies
npm install

# 3. Copy environment file
cp .env.example .env

# 4. Generate application key
php artisan key:generate

# 5. Run migrations
php artisan migrate

# 6. Create storage symlink
php artisan storage:link

# 7. Build frontend
npm run build

# 8. Start development servers
composer run dev
```

## Important Patterns & Best Practices

### Adding New API Endpoints

1. Create route in `routes/api.php` with `auth:sanctum` middleware if authenticated
2. Create controller method in appropriate API controller
3. Validate input using `$request->validate()`
4. Wrap multi-step operations in `DB::transaction()`
5. Return JSON response with appropriate status code
6. Handle exceptions and return meaningful error messages

Example:
```php
public function store(Request $request)
{
    $validated = $request->validate([
        'field' => 'required|string|max:255',
    ]);

    DB::beginTransaction();
    try {
        $model = Model::create($validated);
        DB::commit();
        return response()->json(['message' => 'Success', 'data' => $model], 201);
    } catch (\Exception $e) {
        DB::rollback();
        return response()->json(['message' => $e->getMessage()], 500);
    }
}
```

### Adding New Inertia Pages

1. Create Vue component in `resources/js/pages/`
2. Add route in `routes/web.php` with appropriate middleware
3. Create controller method that returns `Inertia::render('PageName', $props)`
4. Use appropriate layout component via `<template>` or `setup()`
5. Define TypeScript props interface for type safety

Example:
```vue
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'

interface Props {
  data: SomeType[]
}

defineProps<Props>()
</script>

<template>
  <AppLayout>
    <!-- Page content -->
  </AppLayout>
</template>
```

### Working with Eloquent Relationships

When adding new relationships:
1. Define relationship methods in model: `hasMany()`, `belongsTo()`, etc.
2. Add foreign key constraint in migration with appropriate cascade behavior
3. Eager load relationships to avoid N+1 queries: `Model::with('relation')->get()`
4. Use relationship methods for creating related records: `$user->orders()->create(...)`

### Validation Rules

Common validation patterns used in this codebase:
- `cedula_type`: `required|in:v,j,e,g,r,p`
- `email`: `required|email|unique:users,email`
- `tel`: `required|string|min:10|max:15|unique:users,tel`
- `latitude`: `required|numeric|between:-90,90`
- `longitude`: `required|numeric|between:-180,180`
- `payment_method`: `required|in:manual_transfer,mobile_payment,credit_card,paypal,binance`
- `status`: `required|in:pending_payment,paid,shipped,completed`

## Testing Conventions

Tests use Pest PHP framework (modern alternative to PHPUnit).

**Feature Tests** (`tests/Feature/`):
- Test complete user workflows
- Interact with HTTP endpoints
- Use `actingAs($user)` for authentication
- Test database state changes

**Unit Tests** (`tests/Unit/`):
- Test individual methods and classes
- Mock dependencies
- Test business logic in isolation

Example test structure:
```php
test('user can add product to cart', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/api/cart/add', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

    $response->assertStatus(200);
    expect($user->cartItems)->toHaveCount(1);
});
```

## Troubleshooting Common Issues

### Queue Jobs Not Processing
- Ensure queue worker is running: `php artisan queue:listen`
- Check queue connection in `.env`: `QUEUE_CONNECTION=database`
- Run migrations to create `jobs` table

### Vite/Asset Issues
- Clear Laravel cache: `php artisan optimize:clear`
- Delete `node_modules` and reinstall: `rm -rf node_modules && npm install`
- Rebuild assets: `npm run build`

### Authentication Issues
- Clear config cache: `php artisan config:clear`
- Regenerate app key: `php artisan key:generate`
- Check Sanctum stateful domains in `.env`

### WooCommerce Sync Failures
- Verify WooCommerce credentials in `.env`
- Check API permissions in WooCommerce admin
- Review logs: `storage/logs/laravel.log`
- Test single product sync first: `/api/products/{wooid}/syncSingleProduct`

### Storage/File Upload Issues
- Ensure storage link exists: `php artisan storage:link`
- Check directory permissions: `storage/` and `bootstrap/cache/` should be writable
- Verify disk configuration in `config/filesystems.php`
