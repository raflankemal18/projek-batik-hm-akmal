<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // products.harga -> decimal(12,2)
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('harga', 12, 2)->change();
        });

        // products_sizes.stock -> unsignedInteger + check(stock >= 0)
        Schema::table('products_sizes', function (Blueprint $table) {
            $table->unsignedInteger('stock')->change();
        });
        $this->addIndexIfMissing('products_sizes', ['product_id'], 'products_sizes_product_id_index');
        DB::statement('ALTER TABLE products_sizes ADD CONSTRAINT products_sizes_stock_non_negative CHECK (stock >= 0)');

        // product_images index
        $this->addIndexIfMissing('product_images', ['product_id'], 'product_images_product_id_index');

        // orders: total_harga decimal, status enum, expires_at
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('total_harga', 12, 2)->change();
            $table->enum('status', ['draft', 'pending_payment', 'paid', 'cancelled'])->default('draft')->after('total_harga');
            $table->timestamp('expires_at')->nullable()->after('status');
        });
        $this->addIndexIfMissing('orders', ['user_id', 'status'], 'orders_user_id_status_index');

        // order_details: harga decimal + product_size_id
        Schema::table('order_details', function (Blueprint $table) {
            $table->decimal('harga', 12, 2)->change();
            $table->foreignId('product_size_id')->nullable()->after('product_id')
                ->constrained('products_sizes')->nullOnDelete();
        });

        // payments: total_harga decimal, order_id, transaction_id unique, paid_at
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('total_harga', 12, 2)->change();
            $table->foreignId('order_id')->nullable()->after('user_id')
                ->constrained('orders')->nullOnDelete();
            $table->string('transaction_id')->nullable()->unique()->after('order_id');
            $table->timestamp('paid_at')->nullable()->after('status');
        });
        $this->addIndexIfMissing('payments', ['user_id', 'status'], 'payments_user_id_status_index');

        // payment_details index
        $this->addIndexIfMissing('payment_details', ['payment_id', 'product_id'], 'payment_details_payment_id_product_id_index');

        // users(role) index
        Schema::table('users', function (Blueprint $table) {
            $table->index('role');
        });

        // Backfill order_details.product_size_id from products_sizes by product_id + size
        DB::statement(
            'UPDATE order_details od
             JOIN products_sizes ps ON ps.product_id = od.product_id AND ps.size = od.size
             SET od.product_size_id = ps.id
             WHERE od.product_size_id IS NULL'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: several indexes added in up() (payment_details, payments,
        // orders, products_sizes, product_images — all on FK columns like
        // user_id/product_id/payment_id) are intentionally NOT dropped here.
        // Those columns' original foreign key constraints were created
        // without their own standalone index (see the original 2024
        // migrations), so by the time up() runs, the index added here ends
        // up being the only one satisfying MySQL's "FK column must have a
        // supporting index" requirement. Dropping it fails with
        // "SQLSTATE[HY000]: 1553 ... needed in a foreign key constraint".
        // Leaving a redundant index behind on rollback is harmless; crashing
        // migrate:rollback is not.

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('paid_at');
            $table->dropColumn('transaction_id');
            $table->dropForeign(['order_id']);
            $table->dropColumn('order_id');
            $table->string('total_harga')->change();
        });

        Schema::table('order_details', function (Blueprint $table) {
            $table->dropForeign(['product_size_id']);
            $table->dropColumn('product_size_id');
            $table->string('harga')->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('expires_at');
            $table->dropColumn('status');
            $table->string('total_harga')->change();
        });

        DB::statement('ALTER TABLE products_sizes DROP CHECK products_sizes_stock_non_negative');
        Schema::table('products_sizes', function (Blueprint $table) {
            $table->integer('stock')->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('harga')->change();
        });
    }

    /**
     * Add an index only if it doesn't already exist — makes up() safe to
     * re-run after a rollback that intentionally leaves these indexes in
     * place (see the note at the top of down()).
     */
    private function addIndexIfMissing(string $table, array $columns, string $indexName): void
    {
        $exists = DB::selectOne(
            'SELECT 1 FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?
             LIMIT 1',
            [$table, $indexName]
        );

        if (! $exists) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName) {
                $blueprint->index($columns, $indexName);
            });
        }
    }
};
