<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DeliveryOrdersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚚 Creando órdenes asignadas a delivery...');

        // 1. Obtener o crear delivery
        $delivery = User::whereHas('roles', function ($query) {
            $query->where('name', 'delivery');
        })->first();

        if (!$delivery) {
            $delivery = User::firstOrCreate(
                ['email' => 'delivery@test.com'],
                [
                    'name' => 'Juan Delivery',
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'tel' => '04143334567',
                    'cedula_type' => 'v',
                    'cedula_ID' => '34567890',
                ]
            );
            $delivery->assignRole('delivery');
            $this->command->info('✅ Delivery creado: delivery@test.com / password');
        } else {
            $this->command->info('✅ Usando delivery existente: ' . $delivery->email);
        }

        // 2. Obtener o crear productos
        $products = Product::inRandomOrder()->limit(10)->get();

        if ($products->count() === 0) {
            $this->command->warn('⚠️  No hay productos. Creando productos de prueba...');
            for ($i = 1; $i <= 5; $i++) {
                Product::create([
                    'woocommerce_id' => 10000 + $i,
                    'name' => "Producto {$i}",
                    'slug' => "producto-{$i}",
                    'price' => rand(5000, 50000),
                    'regular_price' => rand(10000, 60000),
                    'sale_price' => rand(5000, 50000),
                    'description' => "Descripción del producto {$i}",
                    'short_description' => "Producto {$i}",
                    'sku' => "PROD-{$i}",
                    'stock_quantity' => rand(10, 100),
                    'stock_status' => 'instock',
                ]);
            }
            $products = Product::all();
            $this->command->info('✅ Productos creados');
        }

        // 3. Crear 3 clientes diferentes con direcciones
        $clients = [];
        for ($i = 1; $i <= 3; $i++) {
            $client = User::firstOrCreate(
                ['email' => "cliente{$i}@test.com"],
                [
                    'name' => "Cliente {$i}",
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'tel' => '0412' . rand(1000000, 9999999),
                    'cedula_type' => 'v',
                    'cedula_ID' => (string)(20000000 + $i),
                ]
            );

            if (!$client->hasRole('client')) {
                $client->assignRole('client');
            }

            // Crear dirección para el cliente
            $address = UserAddress::firstOrCreate(
                [
                    'user_id' => $client->id,
                    'postal_code' => '1000' . $i,
                ],
                [
                    'address_line_1' => "Calle Principal #{$i}00",
                    'address_line_2' => "Edificio {$i}, Apartamento {$i}0{$i}",
                    'name' => 'Casa',
                    'referencia' => "Cerca del lugar {$i}",
                    'latitude' => 10.5000 + ($i * 0.01),
                    'longitude' => -66.9000 - ($i * 0.01),
                    'is_default' => true,
                ]
            );

            $clients[] = [
                'user' => $client,
                'address' => $address,
            ];
        }

        $this->command->info('✅ 3 clientes con direcciones creados');

        // 4. Crear 3 órdenes con diferentes estados
        $ordersData = [
            [
                'status' => 'in_agency',
                'description' => 'Orden en agencia, lista para recoger',
            ],
            [
                'status' => 'on_the_way',
                'description' => 'Orden en camino al cliente',
            ],
            [
                'status' => 'delivered',
                'description' => 'Orden entregada',
            ],
        ];

        foreach ($ordersData as $index => $orderData) {
            $clientData = $clients[$index];
            $client = $clientData['user'];
            $address = $clientData['address'];

            // Seleccionar productos aleatorios
            $selectedProducts = $products->random(rand(1, 3));
            $subtotal = 0;
            $orderItems = [];

            foreach ($selectedProducts as $product) {
                $quantity = rand(1, 3);
                $price = $product->price ?? $product->regular_price ?? rand(5000, 30000);
                $subtotal += $price * $quantity;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'price_at_purchase' => $price,
                ];
            }

            $shippingCost = rand(2000, 5000);
            $total = $subtotal + $shippingCost;

            // Crear orden
            $order = Order::create([
                'user_id' => $client->id,
                'address_id' => $address->id,
                'delivery_id' => $delivery->id,
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'total' => $total,
                'payment_method' => 'manual_transfer',
                'payment_reference' => 'REF-' . strtoupper(Str::random(10)),
                'status' => $orderData['status'],
                'notes' => $orderData['description'],
                'assigned_at' => now()->subDays(rand(1, 7)),
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

            // Agregar datos específicos según el estado
            if ($orderData['status'] === 'on_the_way') {
                $order->update([
                    'current_latitude' => 10.5010 + (rand(-100, 100) / 10000),
                    'current_longitude' => -66.9000 + (rand(-100, 100) / 10000),
                    'location_updated_at' => now()->subMinutes(rand(5, 60)),
                ]);
            }

            if ($orderData['status'] === 'delivered') {
                $order->update([
                    'delivered_at' => now()->subDays(rand(1, 3)),
                ]);
            }

            $this->command->info("✅ Orden #{$order->id} creada - Estado: {$orderData['status']} - Cliente: {$client->name}");
        }

        $this->command->info('');
        $this->command->info('🎉 ¡3 órdenes asignadas al delivery creadas exitosamente!');
        $this->command->info('');
        $this->command->info('📋 Resumen:');
        $this->command->info("   - Delivery: {$delivery->name} ({$delivery->email})");
        $this->command->info('   - 3 órdenes con diferentes estados:');
        $this->command->info('     • Orden 1: in_agency (En agencia)');
        $this->command->info('     • Orden 2: on_the_way (En camino)');
        $this->command->info('     • Orden 3: delivered (Entregada)');
        $this->command->info('');
    }
}
