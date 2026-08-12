import React from "react";
import { Head } from "@inertiajs/react";
import WelcomeLayout from "@/Layouts/WelcomeLayout";
import ProductCards from "@/Components/ProductCards";
import { Heart } from "lucide-react";

export default function Wishlist({ wishlists }) {
    // Extract products from wishlists
    const products = wishlists.map(w => w.product);

    return (
        <WelcomeLayout>
            <Head title="Wishlist Saya - BatikNesia" />
            
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                <div className="flex items-center gap-3 mb-8 border-b border-batik-200 pb-4">
                    <Heart className="w-8 h-8 text-red-500 fill-red-500" />
                    <h1 className="text-3xl font-serif font-bold text-batik-900">
                        Wishlist Saya
                    </h1>
                </div>

                {products.length > 0 ? (
                    <ProductCards products={products} />
                ) : (
                    <div className="text-center py-20 bg-gray-50 rounded-2xl border-2 border-dashed border-gray-200">
                        <Heart className="w-12 h-12 text-gray-300 mx-auto mb-4" />
                        <h3 className="text-xl font-bold text-gray-500 mb-2">Wishlist Kosong</h3>
                        <p className="text-gray-400">Anda belum menyimpan produk apapun ke wishlist.</p>
                    </div>
                )}
            </div>
        </WelcomeLayout>
    );
}
