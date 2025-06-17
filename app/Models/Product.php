<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    // Stock status constants
    public const STOCK_STATUS_INSTOCK = 'instock';
    public const STOCK_STATUS_OUTOFSTOCK = 'outofstock';
    public const STOCK_STATUS_ONBACKORDER = 'onbackorder';


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
    ];

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }
}
