<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;


    protected $fillable = ['user_id', 'total_harga', 'snap_token', 'id'];
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    public function paymentDetails()
    {
        return $this->hasMany(PaymentDetail::class, 'payment_id', 'id');
    }
}
