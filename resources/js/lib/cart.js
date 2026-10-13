export function flattenCartItems(keranjangs) {
    return keranjangs
        .flatMap(k => k.details.map(d => ({
            id: d.id,
            product_id: d.product.id,
            name: d.product.name,
            // Harga & label mengikuti motif bila baris ini punya variasi —
            // motif itulah yang dibayar, bukan harga produk.
            harga: d.variant ? Number(d.variant.price) : Number(d.product.harga),
            image_url: d.variant?.image_path
                ? [{ image_path: d.variant.image_path }]
                : d.product.images,
            motif_name: d.variant?.name || null,
            is_variant: !!d.variant,
            size: d.size,
            quantity: d.quantity,
        })));
}
