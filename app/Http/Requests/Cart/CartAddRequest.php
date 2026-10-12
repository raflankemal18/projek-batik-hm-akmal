<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CartAddRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Dua jalur input, keduanya tetap diterima supaya produk lama tidak rusak:
     *   - motif   → product_id + product_variant_id (wajib untuk produk has_variants)
     *   - ukuran  → product_id + size (produk tanpa motif)
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $variantId = $this->input('product_variant_id');

        return [
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => $variantId === null
                ? 'nullable|integer|exists:product_variants,id'
                : [
                    'required',
                    'integer',
                    // Motif harus milik produk yang dipilih DAN masih aktif — yang
                    // nonaktif ditolak di validasi, bukan lewat exception.
                    Rule::exists('product_variants', 'id')
                        ->where(fn ($q) => $q->where('product_id', $this->input('product_id')))
                        ->where(fn ($q) => $q->where('is_active', true)),
                ],
            'size' => $variantId === null ? 'required|string' : 'nullable|string',
            'quantity' => 'required|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'product_variant_id.required' => 'Pilih motif kain terlebih dahulu.',
            'product_variant_id.exists' => 'Motif tersebut tidak tersedia untuk produk ini.',
            'size.required' => 'Pilih motif atau ukuran dulu sebelum menambah ke keranjang.',
        ];
    }
}