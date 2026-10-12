<?php

namespace App\Http\Requests\Admin\Product;

class UpdateVariantRequest extends StoreVariantRequest
{
    // Aturan identik dengan StoreVariantRequest; dipisah supaya intent tiap
    // endpoint jelas dan mudah diubah terpisah nanti (mis. aturan harga).
}
