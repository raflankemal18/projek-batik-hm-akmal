<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Products extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'description', 'harga'];

    public function sizes()
    {
        return $this->hasMany(ProductsSize::class, 'product_id', 'id');
    }
    public function images()
    {
        return $this->hasMany(product_image::class, 'product_id', 'id'); // Pastikan kunci asing dan kunci lokal didefinisikan dengan benar
    }

    public function details()
    {
        return $this->hasMany(CartDetail::class, 'product_id', 'id'); // Pastikan kunci asing dan kunci lokal didefinisikan dengan benar
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class, 'product_id', 'id'); // Pastikan kunci asing dan kunci lokal didefinisikan dengan benar
    }
}
