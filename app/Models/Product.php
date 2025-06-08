<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
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
        'status'
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
