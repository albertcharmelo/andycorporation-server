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
        // Aumentar el tiempo de ejecución para conexiones lentas a producción
        set_time_limit(300); // 5 minutos
        ini_set('max_execution_time', 300);

        $page = 1;
        $perPage = 50; // Reducir el tamaño de página para evitar sobrecarga
        $totalProcessed = 0;
        $errors = [];

        try {
            do {
                $products = WooProduct::all([
                    'per_page' => $perPage,
                    'page' => $page,
                ]);

                if (empty($products)) {
                    break;
                }

                // Procesar productos en lotes para optimizar transacciones
                DB::beginTransaction();
                try {
                    foreach ($products as $wooProduct) {
                        try {
                            $product = Product::updateOrCreate(
                                ['woocommerce_id' => $wooProduct->id],
                                [
                                    'name' => $wooProduct->name ?? '',
                                    'slug' => $wooProduct->slug ?? '',
                                    'description' => $wooProduct->description ?? null,
                                    'short_description' => $wooProduct->short_description ?? null,
                                    'price' => is_numeric($wooProduct->price) ? $wooProduct->price : null,
                                    'regular_price' => is_numeric($wooProduct->regular_price) ? $wooProduct->regular_price : null,
                                    'sale_price' => is_numeric($wooProduct->sale_price) ? $wooProduct->sale_price : null,
                                    'sku' => $wooProduct->sku ?? null,
                                    'status' => $wooProduct->status ?? 'publish',
                                    // 'stock_quantity' => $wooProduct->stock_quantity ?? 0, // Comentado temporalmente
                                    'stock_status' => $wooProduct->stock_status ?? 'instock',
                                ]
                            );

                            // 🔁 Relacionar productos relacionados (solo si existen en DB)
                            if (is_array($wooProduct->related_ids) && count($wooProduct->related_ids) > 0) {
                                $existingWooIds = Product::whereIn('woocommerce_id', $wooProduct->related_ids)
                                    ->pluck('woocommerce_id')
                                    ->toArray();
                                $product->relatedProducts()->sync($existingWooIds);
                            } else {
                                $product->relatedProducts()->sync([]);
                            }

                            // Sincronizar imágenes (solo si existen)
                            if (isset($wooProduct->images) && is_array($wooProduct->images)) {
                                $product->images()->delete();
                                $imageData = [];
                                foreach ($wooProduct->images as $image) {
                                    if (isset($image->src)) {
                                        $imageData[] = [
                                            'src' => $image->src,
                                            'alt' => $image->alt ?? null,
                                            'product_id' => $product->id,
                                        ];
                                    }
                                }
                                if (!empty($imageData)) {
                                    // Insertar imágenes en lote
                                    DB::table('product_images')->insert($imageData);
                                }
                            }

                            // Sincronizar categorías
                            $categoryIds = [];
                            if (isset($wooProduct->categories) && is_array($wooProduct->categories)) {
                                foreach ($wooProduct->categories as $wooCategory) {
                                    if (isset($wooCategory->id)) {
                                        $category = Category::firstOrCreate(
                                            ['woocommerce_id' => $wooCategory->id],
                                            [
                                                'name' => $wooCategory->name ?? 'Sin categoría',
                                                'slug' => $wooCategory->slug ?? 'sin-categoria'
                                            ]
                                        );
                                        $categoryIds[] = $category->id;
                                    }
                                }
                            }
                            $product->categories()->sync($categoryIds);

                            $totalProcessed++;
                        } catch (\Exception $e) {
                            // Registrar error pero continuar con el siguiente producto
                            $errors[] = [
                                'woocommerce_id' => $wooProduct->id ?? 'unknown',
                                'error' => $e->getMessage()
                            ];
                            continue;
                        }
                    }

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }

                $hasMore = count($products) === $perPage;
                $page++;

                // Pequeña pausa para no sobrecargar la conexión
                if ($hasMore) {
                    usleep(100000); // 0.1 segundos
                }
            } while ($hasMore);

            return response()->json([
                'message' => 'Productos sincronizados correctamente.',
                'total_processed' => $totalProcessed,
                'errors_count' => count($errors),
                'errors' => $errors
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error al sincronizar productos: ' . $th->getMessage(),
                'total_processed' => $totalProcessed,
                'errors' => $errors
            ], 500);
        }
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
                        // 'stock_quantity' => $wooProduct->stock_quantity, // Comentado temporalmente
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
        $product->load([
            'images',
            'categories',
            'relatedProducts',
            'relatedProducts.images',
            'relatedProducts.categories'
        ]);


        return response()->json($product);
    }


    ## Search products
    public function searchProducts(Request $request)
    {
        $request->validate([
            'query' => 'nullable|string|max:255',
            'page' => 'sometimes|integer|min:1',
            'filters' => 'nullable|array',
            'filters.price.min' => 'nullable|numeric|min:0',
            'filters.price.max' => 'nullable|numeric|min:0',
            'filters.orderBy' => 'nullable|string|in:relevancia,menor_precio,mayor_precio',
        ]);

        ## Obtener los parámetros de búsqueda y filtros
        $queryString = $request->input('query', '');
        $filters = $request->input('filters', []);

        $baseQuery = Product::with(['images', 'categories'])
            ->where('stock_status', Product::STOCK_STATUS_INSTOCK);

        ## Si hay un término de búsqueda, aplicarlo
        if ($queryString !== '') {
            $baseQuery->where(function ($q) use ($queryString) {
                $q->where('name', 'LIKE', "%{$queryString}%")
                    ->orWhere('sku', 'LIKE', "%{$queryString}%");
            });
        } else {
            $baseQuery->inRandomOrder();
        }

        ## Filtros
        $baseQuery
            ->when(isset($filters['price']['min']) || isset($filters['price']['max']), function ($q) use ($filters) { ## Filtrar por precio
                ## Si se especifica un rango de precios, aplicar el filtro
                $min = isset($filters['price']['min']) && $filters['price']['min'] !== ''
                    ? (float) $filters['price']['min']
                    : null;
                $max = isset($filters['price']['max']) && $filters['price']['max'] !== ''
                    ? (float) $filters['price']['max']
                    : null;

                ## Aplicar el filtro de precio
                if (!is_null($min) && !is_null($max)) {
                    $q->whereBetween('price', [$min, $max]);
                } elseif (!is_null($min)) {
                    $q->where('price', '>=', $min);
                } elseif (!is_null($max)) {
                    $q->where('price', '<=', $max);
                }
            })
            ## Ordenar productos
            ->when(isset($filters['orderBy']), function ($q) use ($filters) {
                switch ($filters['orderBy']) {
                    case 'menor_precio':
                        $q->orderBy('price', 'asc');
                        break;
                    case 'mayor_precio':
                        $q->orderBy('price', 'desc');
                        break;
                    default:
                        $q->orderBy('woocommerce_id', 'desc');
                }
            });

        $products = $baseQuery->paginate(20);

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

    ## Promotional products
    public function getPromotionalProducts()
    {
        $products = Product::with(['images', 'categories'])
            ->where('stock_status', Product::STOCK_STATUS_INSTOCK)
            ->whereNotNull('sale_price')
            ->whereColumn('sale_price', '<', 'regular_price')
            ->inRandomOrder() // para obtener productos aleatorios
            ->take(20) // puedes ajustar la cantidad
            ->get();

        return response()->json([
            'products' => $products,
        ])->setStatusCode(200, 'OK');
    }
    ## Get Popular products
    public function getPopularProducts()
    {
        $products = Product::with(['images', 'categories'])
            ->orderBy('average_rating', 'desc')
            ->inRandomOrder() // para obtener productos aleatorios
            ->take(20)
            ->get();
        return response()->json([
            'products' => $products
        ])->setStatusCode(200, 'OK');
    }

    ## Get Sales products
    public function getSalesProducts()
    {
        $products = Product::with(['images', 'categories'])
            ->orderBy('total_sales', 'desc')
            ->inRandomOrder() // para obtener productos aleatorios
            ->take(20)
            ->get();

        return response()->json([
            'products' => $products,
        ])->setStatusCode(200, 'OK');
    }
}
