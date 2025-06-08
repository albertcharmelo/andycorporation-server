<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['woocommerce_id', 'name', 'slug'];

    public function products()
    {
        return $this->belongsToMany(Product::class);
    }
}
