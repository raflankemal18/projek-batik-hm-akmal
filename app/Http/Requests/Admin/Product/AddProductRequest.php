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
            'sizes' => 'required|array',
            'images' => 'sometimes|array',
            'images.*' => 'image|mimes:jpg,jpeg,png,gif|max:2048',
        ];
    }
}
