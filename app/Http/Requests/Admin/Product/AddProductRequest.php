<?php

namespace App\Http\Requests\Admin\Product;

use Illuminate\Foundation\Http\FormRequest;

class AddProductRequest extends FormRequest
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
        return [
            'name' => 'required|string|max:25',
            'harga' => 'required|numeric',
            'description' => 'nullable|string',
            'category' => 'nullable|string|in:pria,wanita,sarimbit',
            'fabric_size' => 'nullable|string|max:60',
            'has_variants' => 'nullable|boolean',
            // Ukuran (products_sizes) hanya wajib untuk produk yang TIDAK memakai
            // motif. Produk bermotif mengelola moturnya lewat endpoint terpisah.
            'sizes' => $this->boolean('has_variants') ? 'sometimes|array' : 'required|array',
            'images' => 'sometimes|array',
            'images.*' => 'image|mimes:jpg,jpeg,png,gif|max:2048',
        ];
    }
}
