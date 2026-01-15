<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    /**
     * 1. Agregar producto: Recibir un producto con su cantidad y guardarlo en el carrito de compras.
     */
    public function addProduct(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'required|integer|min:1',
        ]);
        $user = $request->user(); // El usuario autenticado

        $productId = $request->product_id;
        $quantity  = $request->quantity;

        try {
            DB::beginTransaction();

            $product = Product::find($productId);

            // Validar si el producto tiene suficiente stock (opcional pero recomendado)
            // if ($product->stock_quantity !== null && $product->stock_quantity < $quantity) {
            //     DB::rollBack();
            //     return response()->json(['message' => 'No hay suficiente stock disponible para este producto.'], 400);
            // }

            // Buscar si el producto ya está en el carrito del usuario
            $cartItem = CartItem::where('user_id', $user->id)
                ->where('product_id', $productId)
                ->first();

            if ($cartItem) {
                // Si el producto ya existe, actualiza la cantidad
                $cartItem->quantity += $quantity;
                $cartItem->save();
                $message = 'Cantidad del producto actualizada en el carrito.';
            } else {
                // Si el producto no existe, añádelo como un nuevo ítem
                CartItem::create([
                    'user_id'           => $user->id,
                    'product_id'        => $productId,
                    'quantity'          => $quantity,
                    'price_at_purchase' => $product->price ?? $product->regular_price, // Usa el precio actual del producto
                ]);
                $message = 'Producto añadido al carrito.';
            }

            DB::commit();
            return response()->json(['message' => $message]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al añadir producto al carrito: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 2. Actualizar cantidad: Modificar la cantidad de un producto que ya se encuentra en el carrito.
     */
    public function updateQuantity(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'required|integer|min:0', // Permite 0 para "eliminar" si la cantidad llega a cero
        ]);

        $user      = $request->user();
        $quantity  = $request->quantity;
        $productId = $request->product_id;

        try {
            DB::beginTransaction();

            $cartItem = CartItem::where('user_id', $user->id)
                ->where('product_id', $productId)
                ->first();

            if (! $cartItem) {
                DB::rollBack();
                return response()->json(['message' => 'Producto no encontrado en el carrito.'], 404);
            }

            // Validar si el producto tiene suficiente stock para la nueva cantidad (opcional)
            $product = $cartItem->product;
            if ($quantity > 0 && $product->stock_quantity !== null && $product->stock_quantity < $quantity) {
                DB::rollBack();
                return response()->json(['message' => 'No hay suficiente stock disponible para la cantidad solicitada.'], 400);
            }

            if ($quantity <= 0) {
                // Si la cantidad es 0 o menos, elimina el producto del carrito
                $cartItem->delete();
                $message = 'Producto eliminado del carrito.';
            } else {
                // Actualiza la cantidad
                $cartItem->quantity = $quantity;
                $cartItem->save();
                $message = 'Cantidad del producto actualizada en el carrito.';
            }

            DB::commit();
            return response()->json(['message' => $message]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al actualizar la cantidad del producto: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 3. Eliminar producto: Quitar un producto específico del carrito.
     */
    public function removeProduct(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);
        $user      = $request->user();
        $productId = $request->product_id;

        try {
            $deleted = CartItem::where('user_id', $user->id)
                ->where('product_id', $productId)
                ->delete();

            if ($deleted) {
                return response()->json(['message' => 'Producto eliminado del carrito exitosamente.']);
            } else {
                return response()->json(['message' => 'Producto no encontrado en el carrito.'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar producto del carrito: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 4. Mostrar carrito: Devolver la lista de productos que el usuario ha añadido, incluyendo el subtotal y el total de la compra.
     */
    public function showCart(Request $request)
    {
        $user = $request->user();

        // Carga los ítems del carrito con la información del producto relacionada
        $cartItems = $user->cartItems()->with('product')->get();

        $subtotal = 0;
        $items    = [];

        foreach ($cartItems as $item) {
            $productPrice = $item->price_at_purchase;
            $itemTotal    = $productPrice * $item->quantity;
            $subtotal += $itemTotal;

            $items[] = [
                'cart_item_id'  => $item->id,
                'product_id'    => $item->product->id,
                'product_name'  => $item->product->name,
                'product_price' => $productPrice,
                'quantity'      => $item->quantity,
                'item_total'    => round($itemTotal, 2),
                'product_image' => $item->product->images->first()->src ?? null, // Asume que tienes una relación 'images' en el modelo Product
            ];
        }

        // Aquí puedes añadir lógica para impuestos, descuentos, envío para calcular el "total" final.
        // Por simplicidad, por ahora el "total" será igual al "subtotal".
        $total = $subtotal;

        return response()->json([
            'items'    => $items,
            'subtotal' => round($subtotal, 2),
            'total'    => round($total, 2),
            'currency' => 'USD', // O tu moneda local
        ]);
    }
}
