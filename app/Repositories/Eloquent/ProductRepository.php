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
            // has_variants & fabric_size wajib ikut: kartu/detail produk butuh
            // keduanya untuk membedakan produk bermotif dan menampilkan ukuran kain.
            // Menghilangkannya dari select membuat frontend diam-diam menganggap
            // semua produk sebagai produk tanpa motif.
            ->select(['id', 'name', 'category', 'harga', 'description', 'fabric_size', 'has_variants', 'created_at', 'updated_at']);

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

    /**
     * Daftar produk untuk halaman admin: memuat SEMUA motif (aktif & nonaktif,
     * karena admin perlu melihat yang dinonaktifkan) dan tidak memakai cache
     * katalog publik.
     */
    public function getAllPaginatedForAdmin(int $perPage, ?string $search = null, string $sort = 'created_at', string $direction = 'desc')
    {
        $allowedSorts = ['name', 'harga', 'created_at'];
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        return $this->query()
            ->with(['sizes', 'variants' => fn ($q) => $q->orderBy('id')])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when(in_array($sort, $allowedSorts, true), fn ($q) => $q->orderBy($sort, $direction))
            ->paginate($perPage);
    }

    /** Mengambil produk walau sudah diarsipkan (untuk layar arsip admin). */
    public function findWithTrashed(int $id): Product
    {
        return $this->query()->withTrashed()->where('id', $id)->firstOrFail();
    }

    /** Daftar produk diarsipkan untuk layar "Arsip" di admin. */
    public function getArchivedPaginated(int $perPage = 12, ?string $search = null)
    {
        return $this->query()->withTrashed()->onlyTrashed()
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderByDesc('deleted_at')
            ->paginate($perPage);
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
