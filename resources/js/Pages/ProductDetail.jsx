import React from "react";
import WelcomeLayout from "@/Layouts/WelcomeLayout";
import ModernDetail from "@/Components/ModernDetail";
import ProductCards from "@/Components/ProductCards";
import { Head, Link } from "@inertiajs/react";

export default function ProductDetail({ products, relatedProducts }) {
    return (
        <WelcomeLayout>
            <Head title={`${products?.name ?? 'Detail'} - Koleksi BatikNesia`} />

            <ModernDetail product={products} />

            {/* Produk Lainnya / Rekomendasi */}
            {relatedProducts && relatedProducts.length > 0 && (
                <section className="bg-[#faf9f6] py-20">
                    <div className="max-w-screen-xl mx-auto px-4 lg:px-8">
                        <div className="text-center space-y-4 mb-12">
                            <span className="text-batik-gold uppercase tracking-[0.2em] text-sm font-semibold">
                                Lengkapi Koleksimu
                            </span>
                            <h2 className="font-serif text-3xl md:text-4xl text-gray-900">
                                Produk Lainnya
                            </h2>
                            <div className="w-24 h-1 bg-batik-gold mx-auto" />
                        </div>

                        <ProductCards products={relatedProducts} />

                        <div className="text-center pt-10">
                            <Link href="/products" className="inline-block border-b border-black pb-1 text-sm font-medium hover:text-batik-gold hover:border-batik-gold transition-colors uppercase tracking-widest">
                                Lihat Semua Koleksi
                            </Link>
                        </div>
                    </div>
                </section>
            )}
        </WelcomeLayout>
    );
}
