<?php

namespace App\Http\Requests\Admin\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $productId = $this->route('product')?->id ?? $this->route('product');

        return [
            'name' => [
                'required', 'string', 'max:60',
                // Nama motif unik per produk; perbandingan mengabaikan huruf besar/kecil
                // supaya "Parang" dan "parang" tidak jadi dua motif yang membingungkan.
                Rule::unique('product_variants', 'name')
                    ->where(fn ($q) => $q->where('product_id', $productId))
                    ->ignore($this->route('variant')?->id),
            ],
            'price' => ['required', 'numeric', 'min:1', 'max:100000000'],
            'stock' => ['required', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Motif dengan nama itu sudah ada di produk ini.',
            'image.max' => 'Ukuran foto motif maksimal 2 MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama motif',
            'price' => 'harga',
            'stock' => 'stok',
            'image' => 'foto motif',
        ];
    }
}
