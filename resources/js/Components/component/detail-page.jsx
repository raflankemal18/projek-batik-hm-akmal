import { useState } from "react";
import { router } from "@inertiajs/react"; // Ganti import
import { Label } from "@/Components/ui/label";
import { RadioGroupItem, RadioGroup } from "@/Components/ui/radio-group";
import { Button } from "@/Components/ui/button";
import { formatRupiah } from "@/lib/format";
import FallbackImage from "@/Components/ui/FallbackImage";

export function DetailPage({ products }) {
    const [selectedSize, setSelectedSize] = useState(null);
    const [quantity, setQuantity] = useState(1);

    const handleSizeChange = (sizeValue) => {
        const sizeObj = products.sizes.find((size) => size.size === sizeValue);
        setSelectedSize(sizeObj);
        setQuantity(1);
    };

    const handleQuantityChange = (event) => {
        setQuantity(Math.max(1, parseInt(event.target.value, 10)));
    };

    const handleAddToCart = () => {
        if (!selectedSize || quantity < 1) {
            alert("Please select a size and quantity.");
            return;
        }

        // Ganti Inertia.post menjadi router.post
        router.post("/cart/add", {
            product_id: products.id,
            size: selectedSize.size,
            quantity: quantity,
        });
    };

    return (
        <div className="grid md:grid-cols-2 gap-6 lg:gap-12 items-start max-w-6xl px-4 mx-auto py-6">
            <div className="grid gap-4 md:gap-8">
                <div className="grid md:grid-cols-5 gap-3 items-start">
                    <div className="hidden md:flex flex-col gap-3 items-start">
                        {products.images && products.images.length > 0 ? (
                            products.images.map((image, index) => (
                                <button
                                    key={index}
                                    className="border hover:border-gray-900 rounded-lg overflow-hidden transition-colors dark:hover:border-gray-50"
                                >
                                    <FallbackImage
                                        alt={`Preview thumbnail ${index + 1}`}
                                        className="aspect-[5/6] object-cover"
                                        height="120"
                                        width="100"
                                        src={image.image_path || image.url}
                                    />
                                    <span className="sr-only">{`View Image ${
                                        index + 1
                                    }`}</span>
                                </button>
                            ))
                        ) : (
                            <div className="text-gray-500">
                                No images available.
                            </div>
                        )}
                    </div>
                    <div className="md:col-span-4">
                        <FallbackImage
                            alt="Product Image"
                            className="aspect-[2/3] object-cover border border-slate-200 border-gray-200 w-full rounded-lg overflow-hidden dark:border-gray-800 dark:border-slate-800"
                            height="900"
                            src={products.images[0]?.image_path}
                            width="600"
                        />
                    </div>
                </div>
            </div>
            <div className="grid gap-4 md:gap-10 items-start">
                <div className="grid gap-4">
                    <h1 className="font-bold text-3xl lg:text-4xl">
                        {products.name}
                    </h1>
                    <div>
                        <p>{products.description}</p>
                    </div>
                    <div className="flex items-center gap-4">
                        <div className="text-4xl font-bold">
                            {formatRupiah(products.harga)}
                        </div>
                    </div>
                </div>
                <form className="grid gap-4 md:gap-10">
                    <div className="grid gap-2">
                        <Label className="text-base" htmlFor="size">
                            Ukuran
                        </Label>
                        <RadioGroup className="flex items-center gap-2">
                            {products.sizes.map((size) => (
                                <Label
                                    key={size.id}
                                    className="border cursor-pointer rounded-md p-2 flex items-center gap-2 [&:has(:checked)]:bg-dark dark:[&:has(:checked)]:bg-gray-800"
                                >
                                    <input
                                        type="radio"
                                        id={`size-${size.size}`}
                                        name="size"
                                        value={size.size}
                                        checked={selectedSize === size.size}
                                        onChange={() =>
                                            handleSizeChange(size.size)
                                        }
                                    />
                                    {size.size.toUpperCase()}
                                </Label>
                            ))}
                        </RadioGroup>
                    </div>
                    <div className="grid gap-2">
                        <Label className="text-base" htmlFor="quantity">
                            Jumlah
                        </Label>
                        {selectedSize && (
                            <>
                                <p className="text-gray-600">
                                    Available Stock: {selectedSize.stock}
                                </p>
                                <input
                                    type="number"
                                    id="quantity"
                                    name="quantity"
                                    min="1"
                                    value={quantity}
                                    onChange={handleQuantityChange}
                                    className="border cursor-pointer rounded-md p-2 flex items-center gap-2 [&:has(:checked)]:bg-gray-100 dark:[&:has(:checked)]:bg-gray-800"
                                />
                            </>
                        )}
                    </div>
                    <Button size="lg" onClick={handleAddToCart}>
                        Tambah ke Keranjang
                    </Button>
                </form>
            </div>
        </div>
    );
}