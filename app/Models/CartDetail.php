<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartDetail extends Model
{
    protected $fillable = ['cart_id', 'product_id', 'product_variant_id', 'quantity', 'size'];

    public function cart()
    {
        return $this->belongsTo(Cart::class, 'cart_id', 'id');
    }

    /** withTrashed(): produk terarsip masih harus terbaca di keranjang supaya
     *  halamannya tidak blank; pembelanjaannya sendiri dicegah di checkout. */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id')->withTrashed();
    }

    /** Motif yang dipilih. Null untuk produk lama yang masih memakai kolom size. */
    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id', 'id');
    }
}
