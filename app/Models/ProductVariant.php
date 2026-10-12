<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Motif kain dari satu produk. Harga & stok milik motif, bukan produk
 * (keputusan 2026-08-13 — lihat docs/planning-variasi-motif-batik.md).
 */
class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'price',
        'stock',
        'image_path',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function cartDetails()
    {
        return $this->hasMany(CartDetail::class, 'product_variant_id', 'id');
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class, 'product_variant_id', 'id');
    }

    /** Rincian pembayaran juga menyimpan product_variant_id, jadi ikut dipakai
     *  untuk menentukan apakah sebuah motif boleh dihapus fisik. */
    public function paymentDetails()
    {
        return $this->hasMany(PaymentDetail::class, 'product_variant_id', 'id');
    }

    /** Dipakai untuk eager load variasi yang boleh dibeli (frontend & checkout). */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}