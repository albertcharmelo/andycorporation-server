<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    // Stock status constants
    public const STOCK_STATUS_INSTOCK = 'instock';
    public const STOCK_STATUS_OUTOFSTOCK = 'outofstock';
    public const STOCK_STATUS_ONBACKORDER = 'onbackorder';


    protected $casts = [
        'related_ids' => 'array',
    ];

    protected $fillable = [
        'woocommerce_id',
        'name',
        'slug',
        'description',
        'short_description',
        'price',
        'regular_price',
        'sale_price',
        'sku',
        'status',
        'stock_quantity',
        'stock_status',
        'total_sales',
        'average_rating',
        'rating_count',
        'related_ids'
    ];

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }


    // Productos relacionados con este producto
    public function relatedProducts()
    {
        return $this->belongsToMany(
            Product::class,
            'product_related',
            'product_id',
            'related_product_id'
        );
    }

    // Productos que tienen a este producto como relacionado (opcional)
    public function relatedTo()
    {
        return $this->belongsToMany(
            Product::class,
            'product_related',
            'related_product_id',
            'product_id'
        );
    }
}
