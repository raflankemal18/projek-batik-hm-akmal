export function flattenCartItems(keranjangs) {
    return keranjangs
        .flatMap(k => k.details.map(d => ({
            id: d.id,
            product_id: d.product.id,
            name: d.product.name,
            harga: d.product.harga,
            image_url: d.product.images,
            size: d.size,
            quantity: d.quantity,
        })));
}
