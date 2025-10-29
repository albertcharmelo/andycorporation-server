<?php

namespace Database\Seeders;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🌱 Creando datos de prueba...');

        // 1. Crear usuario cliente
        $client = User::firstOrCreate(
            ['email' => 'cliente@test.com'],
            [
                'name' => 'Cliente de Prueba',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'tel' => '04121234567',
                'cedula_type' => 'v',
                'cedula_ID' => '23456789',
            ]
        );

        if (!$client->hasRole('client')) {
            $client->assignRole('client');
        }

        $this->command->info('✅ Cliente creado: cliente@test.com / password');

        // 2. Crear usuario delivery
        $delivery = User::firstOrCreate(
            ['email' => 'delivery@test.com'],
            [
                'name' => 'Delivery de Prueba',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'tel' => '04143334567',
                'cedula_type' => 'v',
                'cedula_ID' => '34567890',
            ]
        );

        if (!$delivery->hasRole('delivery')) {
            $delivery->assignRole('delivery');
        }

        $this->command->info('✅ Delivery creado: delivery@test.com / password');

        // 3. Crear dirección para el cliente
        $address = UserAddress::firstOrCreate(
            [
                'user_id' => $client->id,
                'postal_code' => '1080',
            ],
            [
                'address_line_1' => 'Av. Francisco de Miranda',
                'address_line_2' => 'Edificio Parque Cristal, Torre Este, Piso 3',
                'name' => 'Casa',
                'referencia' => 'Al lado del Centro Comercial',
                'latitude' => 10.5010,
                'longitude' => -66.8073,
                'is_default' => true,
            ]
        );

        $this->command->info('✅ Dirección creada para el cliente');

        // 4. Obtener algunos productos existentes o crear productos de prueba
        $products = Product::inRandomOrder()->limit(5)->get();

        if ($products->count() === 0) {
            $this->command->warn('⚠️  No hay productos en la base de datos. Creando productos de prueba...');

            for ($i = 1; $i <= 5; $i++) {
                $products[] = Product::create([
                    'woocommerce_id' => 9000 + $i,
                    'name' => "Producto de Prueba {$i}",
                    'slug' => "producto-prueba-{$i}",
                    'price' => rand(10, 100),
                    'regular_price' => rand(100, 200),
                    'sale_price' => rand(10, 100),
                    'description' => "Descripción del producto de prueba {$i}",
                    'short_description' => "Producto {$i} para testing",
                    'sku' => "TEST-{$i}",
                    'stock_quantity' => rand(10, 100),
                    'stock_status' => 'instock',
                ]);
            }

            $products = collect($products);
            $this->command->info('✅ 5 productos de prueba creados');
        }

        // 5. Crear órdenes de prueba
        $statuses = ['pending_payment', 'paid', 'shipped', 'completed'];
        $paymentMethods = ['manual_transfer', 'mobile_payment', 'credit_card'];

        for ($i = 1; $i <= 10; $i++) {
            $status = $statuses[array_rand($statuses)];
            $orderProducts = $products->random(rand(1, 3));

            $subtotal = 0;
            $orderItems = [];

            foreach ($orderProducts as $product) {
                $quantity = rand(1, 3);
                $price = $product->price ?? $product->regular_price;
                $subtotal += $price * $quantity;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'price_at_purchase' => $price,
                ];
            }

            $shippingCost = 5.00;
            $total = $subtotal + $shippingCost;

            $order = Order::create([
                'user_id' => $client->id,
                'address_id' => $address->id,
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'total' => $total,
                'payment_method' => $paymentMethods[array_rand($paymentMethods)],
                'payment_reference' => strtoupper(Str::random(10)),
                'status' => $status,
                'notes' => $i % 2 === 0 ? "Nota de prueba para la orden {$i}" : null,
                'created_at' => now()->subDays(rand(0, 30)),
            ]);

            // Crear items de la orden
            foreach ($orderItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'quantity' => $item['quantity'],
                    'price_at_purchase' => $item['price_at_purchase'],
                ]);
            }

            // Asignar delivery a algunas órdenes
            if (in_array($status, ['shipped', 'completed']) && rand(0, 1)) {
                $order->update([
                    'delivery_id' => $delivery->id,
                    'assigned_at' => now()->subDays(rand(0, 5)),
                ]);

                if ($status === 'completed') {
                    $order->update([
                        'delivered_at' => now()->subDays(rand(0, 3)),
                    ]);
                }
            }
        }

        $this->command->info('✅ 10 órdenes de prueba creadas');

        // 6. Crear algunos items en el carrito del cliente
        $cartProducts = $products->random(2);
        foreach ($cartProducts as $product) {
            CartItem::firstOrCreate(
                [
                    'user_id' => $client->id,
                    'product_id' => $product->id,
                ],
                [
                    'quantity' => rand(1, 3),
                    'price_at_purchase' => $product->price ?? $product->regular_price,
                ]
            );
        }

        $this->command->info('✅ Items agregados al carrito del cliente');

        $this->command->info('');
        $this->command->info('🎉 ¡Datos de prueba creados exitosamente!');
        $this->command->info('');
        $this->command->info('📋 Credenciales:');
        $this->command->info('   Cliente: cliente@test.com / password');
        $this->command->info('   Delivery: delivery@test.com / password');
        $this->command->info('   Admin: admin@andycorporation.com / password');
        $this->command->info('');
        $this->command->info('📦 Se crearon:');
        $this->command->info('   - 1 cliente con dirección');
        $this->command->info('   - 1 delivery');
        $this->command->info('   - 10 órdenes con diferentes estados');
        $this->command->info('   - Items en el carrito');
    }
}
