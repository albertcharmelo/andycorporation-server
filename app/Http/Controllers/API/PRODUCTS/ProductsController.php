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
            dd($products);

            foreach ($products as $wooProduct) {
                if ($wooProduct['stock_quantity'] > 0) {
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

            $product->images()->delete();
            foreach ($wooProduct->images as $image) {
                $product->images()->create([
                    'src' => $image->src,
                    'alt' => $image->alt ?? null,
                ]);
            }

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

        return response()->json(['message' => 'Producto sincronizado correctamente.']);
    }

    ## Get all products
    public function getAllProducts(Request $request)
    {
        $products = Product::with(['images', 'categories'])->get();

        return response()->json($products);
    }

    ## Get product
    public function getProduct(Product $product)
    {
        $product->load(['images', 'categories']);

        return response()->json($product);
    }


    ## Search products
    public function searchProducts(Request $request)
    {
        $query = $request->input('query');

        $products = Product::with(['images', 'categories'])
            ->where('name', 'LIKE', "%{$query}%")
            ->orWhere('description', 'LIKE', "%{$query}%")
            ->orWhere('short_description', 'LIKE', "%{$query}%")
            ->take(10)
            ->get();

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

    ## Get Sale products
    public function getSaleProducts()
    {
        $products = Product::with(['images', 'categories'])
            ->whereNotNull('sale_price')
            ->where('sale_price', '>', 0)
            ->get();

        return response()->json($products);
    }
}
