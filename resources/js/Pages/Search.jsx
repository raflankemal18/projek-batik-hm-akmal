import React from "react";
import WelcomeLayout from "@/Layouts/WelcomeLayout";
import ProductCards from "@/Components/ProductCards";
import { Head, Link } from "@inertiajs/react";
import { Search as SearchIcon } from "lucide-react";

export default function Search({ products, query }) {
    return (
        <WelcomeLayout>
            <Head title={`Pencarian: ${query} - BatikNesia`} />
            
            <section className="min-h-screen bg-[#faf9f6]">
                {/* Header Title */}
                <div className="bg-white border-b border-batik-100">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 text-center">
                        <span className="text-batik-gold font-bold tracking-[0.2em] text-xs uppercase mb-2 block">
                            Hasil Pencarian
                        </span>
                        <h1 className="font-serif text-3xl md:text-4xl text-batik-900 mb-4">
                            Menampilkan hasil untuk: "{query}"
                        </h1>
                        <p className="text-gray-500 max-w-2xl mx-auto font-light">
                            {products.length} produk ditemukan
                        </p>
                    </div>
                </div>

                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                    {products.length > 0 ? (
                        <div className="animate-in fade-in slide-in-from-bottom-4 duration-700">
                            <ProductCards products={products} />
                        </div>
                    ) : (
                        <div className="text-center py-20">
                            <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <SearchIcon className="w-8 h-8 text-gray-400" />
                            </div>
                            <h3 className="text-lg font-serif font-medium text-gray-900">Produk tidak ditemukan</h3>
                            <p className="text-gray-500 text-sm mt-1">Coba gunakan kata kunci lain untuk menemukan produk yang Anda inginkan.</p>
                            <Link 
                                href="/products"
                                className="inline-block mt-6 px-6 py-2 bg-batik-900 text-white rounded-full hover:bg-batik-gold transition-colors font-medium text-sm tracking-wider uppercase"
                            >
                                Lihat Semua Koleksi
                            </Link>
                        </div>
                    )}
                </div>
            </section>
        </WelcomeLayout>
    );
}
