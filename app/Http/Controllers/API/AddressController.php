<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    /**
     * Guardar dirección: Recibir los datos de la dirección de entrega del usuario y almacenarlos.
     */
    public function store(Request $request)
    {
        $request->validate([
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'postal_code'    => 'nullable|string|max:20',
            'is_default'     => 'boolean',
            'latitude'       => 'nullable|numeric|between:-90,90',   // Validación para latitud
            'longitude'      => 'nullable|numeric|between:-180,180', // Validación para longitud
        ]);

        $user = $request->user();

        try {
            DB::beginTransaction();

            if ($request->boolean('is_default')) {
                $user->addresses()->update(['is_default' => false]);
            }

            $address = $user->addresses()->create($request->all());

            DB::commit();
            return response()->json(['message' => 'Dirección guardada exitosamente.', 'address' => $address], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al guardar la dirección: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Mostrar todas las direcciones guardadas previamente por el usuario.
     */
    public function index(Request $request)
    {
        $user      = $request->user();
        $addresses = $user->addresses()->orderBy('is_default', 'desc')->get();

        return response()->json(['addresses' => $addresses]);
    }

    /**
     * Seleccionar/Mostrar una dirección específica por ID.
     */
    public function show(Request $request, $addressId)
    {
        $user = $request->user();

        $address = $user->addresses()->find($addressId);

        if (! $address) {
            return response()->json(['message' => 'Dirección no encontrada.'], 404);
        }

        return response()->json(['address' => $address]);
    }

    /**
     * Actualizar los datos de una dirección existente.
     */
    public function update(Request $request, $addressId)
    {
        $request->validate([
            'address_line_1' => 'sometimes|required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'postal_code'    => 'nullable|string|max:20',
            'is_default'     => 'boolean',
            'latitude'       => 'nullable|numeric|between:-90,90',   // Validación para latitud
            'longitude'      => 'nullable|numeric|between:-180,180', // Validación para longitud
        ]);

        $user = $request->user();

        $address = $user->addresses()->find($addressId);

        if (! $address) {
            return response()->json(['message' => 'Dirección no encontrada.'], 404);
        }

        try {
            DB::beginTransaction();

            if ($request->has('is_default') && $request->boolean('is_default')) {
                $user->addresses()->where('id', '!=', $addressId)->update(['is_default' => false]);
            }

            $address->update($request->all());

            DB::commit();
            return response()->json(['message' => 'Dirección actualizada exitosamente.', 'address' => $address]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al actualizar la dirección: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Eliminar una dirección guardada.
     */
    public function destroy(Request $request, $addressId)
    {
        $user = $request->user();

        $deleted = $user->addresses()->where('id', $addressId)->delete();

        if ($deleted) {
            return response()->json(['message' => 'Dirección eliminada exitosamente.']);
        } else {
            return response()->json(['message' => 'Dirección no encontrada o no pertenece al usuario.'], 404);
        }
    }

    /**
     * Establecer una dirección como predeterminada.
     */
    public function setDefault(Request $request, $addressId)
    {
        $user = $request->user();

        try {
            DB::beginTransaction();

            $user->addresses()->update(['is_default' => false]);

            $address = $user->addresses()->find($addressId);

            if (! $address) {
                DB::rollBack();
                return response()->json(['message' => 'Dirección no encontrada.'], 404);
            }

            $address->is_default = true;
            $address->save();

            DB::commit();
            return response()->json(['message' => 'Dirección establecida como predeterminada exitosamente.', 'address' => $address]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al establecer la dirección predeterminada: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Calcular costo de envío: Basado en las coordenadas de la dirección de entrega.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function calculateShippingCost(Request $request)
    {
        $request->validate([
            'address_id' => 'required|exists:user_addresses,id',
            // Opcional: Si el cálculo depende del carrito
            // 'cart_items' => 'required|array',
            // 'cart_items.*.product_id' => 'required|exists:products,id',
            // 'cart_items.*.quantity' => 'required|integer|min:1',
        ]);

        $user      = $request->user();
        $addressId = $request->address_id;

        $deliveryAddress = $user->addresses()->find($addressId);

        if (! $deliveryAddress) {
            return response()->json(['message' => 'Dirección de envío no encontrada o no pertenece al usuario.'], 404);
        }

        // --- Coordenadas de la dirección de entrega ---
        $deliveryLat = $deliveryAddress->latitude;
        $deliveryLon = $deliveryAddress->longitude;

                               // --- Coordenadas del punto de origen (tu almacén/tienda) ---
                               // DEBES definir la latitud y longitud de tu punto de envío fijo.
                               // Puedes guardarlas en el archivo .env, en la configuración, o en una tabla de base de datos.
                               // Por ejemplo, de Maracay, Aragua, Venezuela.
        $originLat = 10.2458;  // Latitud de tu punto de origen
        $originLon = -67.5955; // Longitud de tu punto de origen

        // Validar que las coordenadas de la dirección de entrega existan
        if (is_null($deliveryLat) || is_null($deliveryLon)) {
            return response()->json(['message' => 'La dirección seleccionada no tiene coordenadas GPS válidas.'], 400);
        }

        // --- Lógica de cálculo del costo de envío basada en la distancia ---
        // Esta es una **implementación de ejemplo y simplificada** usando la fórmula de Haversine.
        // En un escenario real, esto sería mucho más complejo y podría implicar:
        // 1. Integración con APIs de mapas (Google Maps API, OpenStreetMap, etc.) para cálculo de rutas y tiempos.
        // 2. Cálculo basado en zonas geográficas, peso total y volumen de los productos en el carrito.
        // 3. Tipos de envío (estándar, express, etc.).
        // 4. Reglas de negocio específicas (envío gratis a partir de cierto monto, etc.).

        $distance = $this->calculateDistance($originLat, $originLon, $deliveryLat, $deliveryLon); // Distancia en kilómetros

        $shippingCost = 0;
        $currency     = 'USD'; // O tu moneda local

                           // Ejemplo de cálculo de costo basado en distancia:
        $costPerKm = 0.50; // Costo por kilómetro
        $baseCost  = 3.00; // Costo base por envío

        $shippingCost = $baseCost + ($distance * $costPerKm);

        // Puedes añadir aquí lógica para ajustar el costo por peso del carrito, si lo pasas
        /*
        $cartItems = $request->input('cart_items');
        $totalWeight = 0;
        foreach ($cartItems as $item) {
            $product = Product::find($item['product_id']);
            if ($product && $product->weight) { // Asume que Product tiene un campo 'weight'
                $totalWeight += $product->weight * $item['quantity'];
            }
        }
        if ($totalWeight > 5) { // Ejemplo: si el peso es más de 5kg, se suma un costo adicional
            $shippingCost += ($totalWeight - 5) * 0.20; // 0.20 USD por kg adicional
        }
        */

        return response()->json([
            'message'              => 'Costo de envío calculado exitosamente.',
            'shipping_cost'        => round($shippingCost, 2),
            'currency'             => $currency,
            'distance_km'          => round($distance, 2),
            'origin_coordinates'   => ['latitude' => $originLat, 'longitude' => $originLon],
            'delivery_coordinates' => ['latitude' => $deliveryLat, 'longitude' => $deliveryLon],
            'address_info'         => [ // Información de la dirección para confirmación
                'address_line_1' => $deliveryAddress->address_line_1,
                'city'           => $deliveryAddress->city,
                'country'        => $deliveryAddress->country,
            ],
        ]);
    }

    /**
     * Helper function para calcular la distancia entre dos puntos GPS usando la fórmula de Haversine.
     * Retorna la distancia en kilómetros.
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // Radio de la Tierra en kilómetros

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        sin($dLon / 2) * sin($dLon / 2);
        $c        = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $earthRadius * $c; // Distancia en km

        return $distance;
    }
}
