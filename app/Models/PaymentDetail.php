<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id', 'product_id', 'product_variant_id', 'motif_name',
        'name', 'email', 'phone', 'alamat', 'size', 'quantity', 'harga',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id', 'id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    /**
     * Motif yang dibeli. Sengaja tidak otomatis eager-load dari order detail:
     * motif_name di baris ini sudah berupa snapshot saat transaksi dibuat.
     */
    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id', 'id');
    }
}
