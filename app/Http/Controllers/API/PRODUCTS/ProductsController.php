<?php

namespace App\Http\Controllers\API\PRODUCTS;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Codexshaper\WooCommerce\Facades\Product as WooProduct;
use Codexshaper\WooCommerce\Facades\WooCommerce;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductsController extends Controller
{
    ## Get products from Woocommerce and sync with local database
    public function syncProducts()
    {
        $page = 1;
        $perPage = 100;

        do {
            $products = WooProduct::all([
                'per_page' => $perPage,
                'page' => $page,
            ]);

            foreach ($products as $wooProduct) {

                DB::transaction(function () use ($wooProduct) {
                    $product = Product::updateOrCreate(
                        ['woocommerce_id' => $wooProduct->id],
                        [
                            'name' => $wooProduct->name,
                            'slug' => $wooProduct->slug,
                            'description' => $wooProduct->description,
                            'short_description' => $wooProduct->short_description,
                            'price' => is_numeric($wooProduct->price) ? $wooProduct->price : null,
                            'regular_price' => is_numeric($wooProduct->regular_price) ? $wooProduct->regular_price : null,
                            'sale_price' => is_numeric($wooProduct->sale_price) ? $wooProduct->sale_price : null,
                            'sku' => $wooProduct->sku,
                            'status' => $wooProduct->status,
                            'stock_quantity' => $wooProduct->stock_quantity,
                            'stock_status' => $wooProduct->stock_status,

                        ]
                    );

                    // 🔁 Relacionar productos relacionados (si existen en WooCommerce)
                    if (is_array($wooProduct->related_ids) && count($wooProduct->related_ids) > 0) {
                        $product->relatedProducts()->sync($wooProduct->related_ids);
                    } else {
                        $product->relatedProducts()->sync([]); // Limpia relaciones si no hay
                    }

                    // Sincronizar imágenes
                    $product->images()->delete();
                    foreach ($wooProduct->images as $image) {
                        $product->images()->create([
                            'src' => $image->src,
                            'alt' => $image->alt ?? null,
                        ]);
                    }

                    // Sincronizar categorías
                    $categoryIds = [];
                    foreach ($wooProduct->categories as $wooCategory) {
                        $category = Category::firstOrCreate(
                            ['woocommerce_id' => $wooCategory->id],
                            ['name' => $wooCategory->name, 'slug' => $wooCategory->slug]
                        );
                        $categoryIds[] = $category->id;
                    }
                    $product->categories()->sync($categoryIds);
                });
            }

            $hasMore = count($products) === $perPage;
            $page++;
        } while ($hasMore);

        return response()->json(['message' => 'Productos sincronizados correctamente.']);
    }

    ## Get products by ids
    public function syncSingleProduct($wooId)
    {
        $wooProduct = WooProduct::find($wooId);

        if (!$wooProduct) {
            return response()->json(['message' => 'Producto no encontrado en WooCommerce.'], 404);
        }

        try {
            DB::transaction(function () use ($wooProduct) {
                // Crear o actualizar producto principal
                $product = Product::updateOrCreate(
                    ['woocommerce_id' => $wooProduct->id],
                    [
                        'name' => $wooProduct->name,
                        'slug' => $wooProduct->slug,
                        'description' => $wooProduct->description,
                        'short_description' => $wooProduct->short_description,
                        'price' => is_numeric($wooProduct->price) ? $wooProduct->price : null,
                        'regular_price' => is_numeric($wooProduct->regular_price) ? $wooProduct->regular_price : null,
                        'sale_price' => is_numeric($wooProduct->sale_price) ? $wooProduct->sale_price : null,
                        'sku' => $wooProduct->sku,
                        'status' => $wooProduct->status,
                        'stock_quantity' => $wooProduct->stock_quantity,
                        'stock_status' => $wooProduct->stock_status,
                        'total_sales' => $wooProduct->total_sales ?? 0,
                        'rating_count' => $wooProduct->rating_count ?? 0,
                        'average_rating' => $wooProduct->average_rating ?? '0.0',
                    ]
                );

                // Sincronizar imágenes
                $product->images()->delete();
                foreach ($wooProduct->images as $image) {
                    $product->images()->create([
                        'src' => $image->src,
                        'alt' => $image->alt ?? null,
                    ]);
                }

                // Sincronizar categorías
                $categoryIds = [];
                foreach ($wooProduct->categories as $wooCategory) {
                    $category = Category::firstOrCreate(
                        ['woocommerce_id' => $wooCategory->id],
                        ['name' => $wooCategory->name, 'slug' => $wooCategory->slug]
                    );
                    $categoryIds[] = $category->id;
                }
                $product->categories()->sync($categoryIds);

                // 🔁 Sincronizar productos relacionados (solo si existen en DB)
                if (is_array($wooProduct->related_ids) && count($wooProduct->related_ids) > 0) {
                    $existingWooIds = Product::whereIn('woocommerce_id', $wooProduct->related_ids)
                        ->pluck('woocommerce_id')
                        ->toArray();

                    $product->relatedProducts()->sync($existingWooIds);
                } else {
                    $product->relatedProducts()->sync([]);
                }
            });

            return response()->json(['message' => 'Producto sincronizado correctamente.']);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Error al sincronizar el producto: ' . $th->getMessage()], 500);
        }
    }

    ## Get all products
    public function getAllProducts(Request $request)
    {



        $products = Product::with(['images', 'categories'])
            ->where('stock_status', Product::STOCK_STATUS_INSTOCK)
            ->orderBy('woocommerce_id', 'desc')
            ->paginate(20);

        return response()->json(
            [
                'products' => $products,
                'message' => 'Productos obtenidos correctamente.',
            ]
        );
    }

    ## Get product
    public function getProduct(Product $product)
    {
        if (!$product) {
            return response()->json(['message' => 'Producto no encontrado.'], 404);
        }
        $product->load(['images', 'categories']);


        return response()->json($product);
    }


    ## Search products
    public function searchProducts(Request $request)
    {
        $request->validate([
            'query' => 'nullable|string|max:255', // << permite que sea null
            'page' => 'sometimes|integer|min:1',
        ]);
        $queryString = $request->input('query');

        $baseQuery = Product::with(['images', 'categories'])
            ->where('stock_status', Product::STOCK_STATUS_INSTOCK);

        if (!empty($queryString)) {
            $baseQuery->where(function ($q) use ($queryString) {
                $q->where('name', 'LIKE', "%{$queryString}%")
                    ->orWhere('sku', 'LIKE', "%{$queryString}%")
                    ->orWhere('short_description', 'LIKE', "%{$queryString}%");
            });
        } else {
            $baseQuery->inRandomOrder();
        }
        $products = $baseQuery->paginate(15);

        return response()->json($products);
    }

    ## Filter products by price
    public function filterByPrice(Request $request)
    {
        $min = $request->input('min', 0);
        $max = $request->input('max', 99999);

        $products = Product::with(['images', 'categories'])
            ->whereBetween('price', [$min, $max])
            ->get();

        return response()->json($products);
    }

    ## Get products by category
    public function getProductsByCategory($slug)
    {
        $category = Category::where('slug', $slug)->firstOrFail();
        $products = $category->products()->with(['images', 'categories'])->get();
        return response()->json($products);
    }

    ## Get Popular products
    public function getPopularProducts()
    {
        $products = Product::with(['images', 'categories'])
            ->orderBy('average_rating', 'desc')
            ->take(10)
            ->get();
        return response()->json([
            'products' => $products,
            'message' => 'Productos más vendidos obtenidos correctamente.'
        ])->setStatusCode(200, 'OK');
    }

    ## Get Sales products
    public function getSalesProducts()
    {
        $products = Product::with(['images', 'categories'])
            ->orderBy('total_sales', 'desc')
            ->take(10)
            ->get();

        return response()->json([
            'products' => $products,
            'message' => 'Productos en oferta obtenidos correctamente.'
        ])->setStatusCode(200, 'OK');
    }
}
