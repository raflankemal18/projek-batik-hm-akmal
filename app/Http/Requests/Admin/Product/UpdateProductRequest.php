<?php

namespace App\Http\Requests\Admin\Product;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return ['has_variants' => 'mode motif'];
    }

    public function messages(): array
    {
        return [
            'has_variants.prohibited' => 'Produk ini masih punya motif. Nonaktifkan atau hapus motifnya dulu sebelum mengubah mode motif.',
        ];
    }

    /**
     * Menonaktifkan mode motif pada produk yang masih punya variasi akan
     * memindahkan katalog & pembelian kembali ke jalur ukuran (products_sizes)
     * sementara variasi — termasuk stoknya — masih hidup. Itu membuka lagi dua
     * sumber stok yang sebelumnya sudah ditutup, jadi perubahannya dicegah di
     * sini dan pesannya dikembalikan ke form admin.
     *
     * Rule `prohibited` tidak dipakai karena isinya mengizinkan nilai kosong —
     * sedangkan justru nilai kosong itulah yang harus ditolak.
     */
    public function after(): array
    {
        return [
            function ($validator) {
                $product = $this->route('product');

                if ($product && $product->variants()->exists() && ! $this->boolean('has_variants')) {
                    $validator->errors()->add(
                        'has_variants',
                        'Produk ini masih punya motif. Hapus motifnya dulu sebelum mengubah mode motif.'
                    );
                }
            },
        ];
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'harga' => 'required|numeric',
            'description' => 'nullable|string',
            'category' => 'nullable|string|in:pria,wanita,sarimbit',
            'fabric_size' => 'nullable|string|max:60',
            'has_variants' => 'nullable|boolean',
            // Ukuran (products_sizes) hanya wajib untuk produk yang TIDAK memakai
            // motif. Produk bermotif mengelola moturnya lewat endpoint terpisah.
            'sizes' => $this->boolean('has_variants') ? 'sometimes|array' : 'required|array',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpg,jpeg,png,gif|max:2048',
        ];
    }
}
