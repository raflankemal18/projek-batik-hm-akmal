<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index('name');
            $table->index('harga');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('name');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('transaction_id');
            $table->index('status');
        });

        Schema::table('payment_details', function (Blueprint $table) {
            $table->index('name');
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['name']);
            $table->dropIndex(['harga']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['name']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['transaction_id']);
            $table->dropIndex(['status']);
        });

        Schema::table('payment_details', function (Blueprint $table) {
            $table->dropIndex(['name']);
            $table->dropIndex(['phone']);
        });
    }
};
