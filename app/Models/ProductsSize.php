<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductsSize extends Model
{
    use HasFactory;
    use HasFactory;

    protected $fillable = ['product_id', 'size', 'stock'];
    protected $table = 'products_sizes';


    public function product()
    {
        return $this->belongsTo(Products::class, 'product_id', 'id');
    }
}
