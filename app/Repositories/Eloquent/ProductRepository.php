<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ProductRepository extends BaseRepository implements ProductRepositoryInterface
{
    protected function modelClass(): string
    {
        return Product::class;
    }

    public function getAllPaginated(int $perPage = 12, ?string $search = null, string $sort = 'created_at', string $direction = 'desc', ?string $category = null)
    {
        $allowedSorts = ['name', 'harga', 'created_at'];
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        $query = $this->query()
            ->with([
                'sizes',
                'images' => fn ($q) => $q->limit(1),
                // Motif aktif ikut dimuat karena kartu katalog perlu menampilkan
                // jumlah motif / harga mulai dari. PENTING: hanya yang aktif —
                // motif nonaktif tidak boleh bocor ke halaman publik. Relasi ini
                // harus eager-load di SETIAP sumber kartu produk, karena
                // Model::preventLazyLoading aktif di luar production.
                'activeVariants',
            ])
            ->select(['id', 'name', 'category', 'harga', 'description', 'created_at', 'updated_at']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($category && $category !== 'all') {
            $query->where('category', $category);
        }

        if (in_array($sort, $allowedSorts, true)) {
            $query->orderBy($sort, $direction);
        }

        return $query->paginate($perPage);
    }

    public function findWithRelations(int $id, array $relations = [])
    {
        return $this->query()->with($relations)->findOrFail($id);
    }

    public function getTopSelling(int $limit = 5)
    {
        return DB::table('payment_details')
            ->selectRaw('payment_details.product_id, products.name, products.harga, SUM(payment_details.quantity) as total_quantity')
            ->join('payments', 'payments.id', '=', 'payment_details.payment_id')
            ->join('products', 'products.id', '=', 'payment_details.product_id')
            ->where('payments.status', 'success')
            ->groupBy('payment_details.product_id', 'products.name', 'products.harga')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get();
    }
}
