import React, { useState } from "react";
import { formatRupiah } from "@/helper/formatRupiah";
import { router } from "@inertiajs/react"; // Ganti import

const DetailPage = ({ products }) => {
    if (!products) return <p>Loading...</p>;

    const [selectedSize, setSelectedSize] = useState(null);
    const [quantity, setQuantity] = useState(1);
    const [comment, setComment] = useState("");
    const [comments, setComments] = useState([]);

    const handleSizeChange = (size) => {
        setSelectedSize(size);
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
    const handleCommentChange = (event) => {
        setComment(event.target.value);
    };
    const handleAddComment = () => {
        if (comment.trim() !== "") {
            setComments([...comments, comment]);
            setComment("");
        }
    };
    return (
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <section>
                <div className="bg-white shadow overflow-hidden sm:rounded-lg mt-10">
                    {products.images.length > 0 ? (
                        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 p-4">
                            {products.images.map((image, index) => (
                                <img
                                    key={index}
                                    src={`/storage/${image.image_path.slice(7)}`}
                                    alt={`Image ${index + 1} of ${products.name}`}
                                    className="w-full h-48 object-center rounded-lg"
                                />
                            ))}
                        </div>
                    ) : (
                        <div className="text-center py-6">
                            <p>No images available</p>
                        </div>
                    )}

                    <div className="p-6">
                        <h2 className="text-lg leading-6 font-medium text-gray-900">
                            {products.name}
                        </h2>
                        <p className="text-sm text-gray-500 mt-1">
                            {products.description}
                        </p>

                        <div className="mt-6">
                            <h3 className="text-sm font-semibold text-gray-700">
                                Price
                            </h3>
                            <p className="text-2xl text-gray-900">
                                {formatRupiah(products.harga)}
                            </p>
                        </div>

                        <div className="mt-4">
                            <h3 className="text-sm font-semibold text-gray-700">
                                Sizes & Stock
                            </h3>
                            <div className="mt-1">
                                {products.sizes.map((size) => (
                                    <div
                                        key={size.id}
                                        className="flex items-center mb-2"
                                    >
                                        <input
                                            type="radio"
                                            id={`size-${size.id}`}
                                            name="size"
                                            value={size.size}
                                            onChange={() =>
                                                handleSizeChange(size)
                                            }
                                            className="accent-blue-500 h-4 w-4"
                                        />
                                        <label
                                            htmlFor={`size-${size.id}`}
                                            className="ml-2 text-gray-800"
                                        >
                                            {size.size}
                                        </label>
                                    </div>
                                ))}
                                {selectedSize && (
                                    <>
                                        <p className="text-gray-600">
                                            Available Stock:{" "}
                                            {selectedSize.stock}
                                        </p>
                                        <div className="mt-2">
                                            <label
                                                htmlFor="quantity"
                                                className="block text-sm font-medium text-gray-700"
                                            >
                                                Quantity
                                            </label>
                                            <input
                                                type="number"
                                                id="quantity"
                                                name="quantity"
                                                min="1"
                                                value={quantity}
                                                onChange={handleQuantityChange}
                                                className="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full pl-7 pr-12 sm:text-sm border-gray-300 rounded-md"
                                            />
                                        </div>
                                    </>
                                )}
                            </div>
                        </div>

                        <button
                            className="mt-6 w-full bg-blue-600 text-white py-2 rounded-md hover:bg-blue-700 transition-colors"
                            onClick={handleAddToCart}
                        >
                            Add to Cart
                        </button>
                    </div>
                </div>
            </section>
        </div>
    );
};

export default DetailPage;