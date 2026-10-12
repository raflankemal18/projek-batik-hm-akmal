<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'category', 'description', 'harga', 'has_variants'];

    protected $casts = [
        'harga' => 'decimal:2',
        'has_variants' => 'boolean',
    ];

    public function sizes()
    {
        return $this->hasMany(ProductSize::class, 'product_id', 'id');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class, 'product_id', 'id');
    }

    /** Motif kain. Hanya relevan untuk produk dengan has_variants = true. */
    public function variants()
    {
        return $this->hasMany(ProductVariant::class, 'product_id', 'id');
    }

    public function activeVariants()
    {
        return $this->variants()->where('is_active', true);
    }

    public function details()
    {
        return $this->hasMany(CartDetail::class, 'product_id', 'id');
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class, 'product_id', 'id');
    }
}
