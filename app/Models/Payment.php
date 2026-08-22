<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'order_id', 'transaction_id', 'total_harga', 'snap_token', 'status', 'paid_at', 'id'];

    protected $casts = [
        'total_harga' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function paymentDetails()
    {
        return $this->hasMany(PaymentDetail::class, 'payment_id', 'id');
    }

    // alias snake_case untuk kompatibilitas view/controller lama yang pakai payment_details
    public function payment_details()
    {
        return $this->paymentDetails();
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }
}
