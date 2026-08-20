import React, { useState } from "react";
import { router, Link, usePage } from "@inertiajs/react"; // Tambahkan usePage
import { formatRupiah } from "@/helper/formatRupiah";
import { Star, Truck, ShieldCheck, Ruler, Minus, Plus, ShoppingBag } from "lucide-react";
import { Button } from "@/Components/ui/button";
import { toast } from "sonner";

export default function ModernDetail({ product }) {
    // 1. AMBIL DATA USER DARI USEPAGE
    const { auth } = usePage().props;
    // 2. DEFINISIKAN isAdmin
    const isAdmin = auth?.user?.role === 'admin';

    const [selectedSize, setSelectedSize] = useState(null);
    const [quantity, setQuantity] = useState(1);
    const [activeImage, setActiveImage] = useState(0);

    // Fallback jika tidak ada gambar
    const images = product.images && product.images.length > 0 
        ? product.images.map(img => `/storage/${img.image_path.replace("public/", "")}`)
        : ["https://placehold.co/600x800?text=Batik+Image"];

    const handleQuantity = (type) => {
        if (type === "inc") setQuantity(q => q + 1);
        if (type === "dec") setQuantity(q => Math.max(1, q - 1));
    };

    const handleAddToCart = () => {
        if (!selectedSize) {
            toast.error("Silakan pilih ukuran batik terlebih dahulu.");
            return;
        }

        router.post("/cart/add", {
            product_id: product.id,
            size: selectedSize.size,
            quantity: quantity,
        }, {
            preserveScroll: true,
            onSuccess: () => toast.success("Berhasil ditambahkan ke keranjang!")
        });
    };

    return (
        <div className="bg-white min-h-screen pb-20">
            {/* Breadcrumb Sederhana */}
            <div className="container mx-auto px-4 py-4 text-sm text-gray-500 mb-4">
                <Link href="/" className="hover:text-batik-gold">Beranda</Link>
                <span className="mx-2">/</span>
                <Link href="/products" className="hover:text-batik-gold">Koleksi</Link>
                <span className="mx-2">/</span>
                <span className="text-batik-900 font-medium">{product.name}</span>
            </div>

            <div className="container mx-auto px-4 md:px-6">
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16">
                    
                    {/* BAGIAN KIRI: GALERI FOTO */}
                    <div className="space-y-4">
                        <div className="relative aspect-[3/4] lg:aspect-square overflow-hidden rounded-xl bg-gray-100 shadow-sm border border-gray-200">
                            <img 
                                src={images[activeImage]} 
                                alt={product.name} 
                                className="object-cover w-full h-full transition-all duration-500 hover:scale-105"
                            />
                            <div className="absolute top-4 left-4">
                                <span className="bg-white/90 backdrop-blur text-xs font-bold px-3 py-1 rounded text-batik-900 uppercase tracking-widest border border-batik-gold/30">
                                    Original
                                </span>
                            </div>
                        </div>
                        
                        {/* Thumbnail Images */}
                        {images.length > 1 && (
                            <div className="flex gap-4 overflow-x-auto pb-2">
                                {images.map((img, idx) => (
                                    <button 
                                        key={idx}
                                        onClick={() => setActiveImage(idx)}
                                        className={`relative w-20 h-24 flex-shrink-0 rounded-lg overflow-hidden border-2 transition-all ${activeImage === idx ? 'border-batik-gold' : 'border-transparent hover:border-gray-300'}`}
                                    >
                                        <img src={img} alt="thumb" className="w-full h-full object-cover" />
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* BAGIAN KANAN: INFORMASI PRODUK */}
                    <div className="flex flex-col h-full">
                        <div className="mb-6 border-b border-gray-100 pb-6">
                            <h1 className="font-serif text-3xl md:text-4xl lg:text-5xl text-batik-900 mb-4 leading-tight">
                                {product.name}
                            </h1>
                            
                            <div className="flex items-center gap-4 mb-6">
                                <span className="text-2xl md:text-3xl font-bold text-batik-gold font-serif">
                                    {formatRupiah(product.harga)}
                                </span>
                                <div className="flex items-center gap-1 text-yellow-500 text-sm bg-yellow-50 px-2 py-1 rounded-full">
                                    <Star className="w-4 h-4 fill-current" />
                                    <span className="font-medium text-batik-900">4.9 (Review)</span>
                                </div>
                            </div>

                            <p className="text-gray-600 leading-relaxed text-base md:text-lg font-light">
                                {product.description || "Batik tulis asli dengan motif elegan yang melambangkan kewibawaan. Dibuat menggunakan bahan katun primisima yang halus dan nyaman dipakai seharian."}
                            </p>
                        </div>

                        {/* Pilihan Ukuran */}
                        <div className="mb-8">
                            <div className="flex justify-between items-center mb-3">
                                <span className="text-sm font-bold text-batik-900 uppercase tracking-widest">Pilih Ukuran</span>
                                <button className="text-xs text-batik-gold underline flex items-center gap-1">
                                    <Ruler className="w-3 h-3" /> Panduan Ukuran
                                </button>
                            </div>
                            <div className="flex flex-wrap gap-3">
                                {product.sizes && product.sizes.map((size) => (
                                    <button
                                        key={size.id}
                                        onClick={() => setSelectedSize(size)}
                                        disabled={size.stock <= 0}
                                        className={`w-14 h-14 rounded-full border transition-all flex items-center justify-center font-medium ${
                                            selectedSize?.id === size.id
                                                ? "bg-batik-900 text-white border-batik-900 ring-2 ring-batik-gold ring-offset-2"
                                                : "bg-white text-gray-900 border-gray-200 hover:border-batik-gold"
                                        } ${size.stock <= 0 ? "opacity-50 cursor-not-allowed bg-gray-50 text-gray-400" : ""}`}
                                    >
                                        {size.size}
                                    </button>
                                ))}
                            </div>
                            {selectedSize && (
                                <p className="text-xs text-gray-500 mt-2 animate-in fade-in slide-in-from-left-2">
                                    Stok tersedia: <span className="font-bold text-batik-900">{selectedSize.stock}</span> pcs
                                </p>
                            )}
                        </div>

                        {/* Quantity & Action */}
                        <div className="flex flex-col sm:flex-row gap-4 mb-8">
                            {/* HANYA TAMPILKAN QUANTITY JIKA BUKAN ADMIN */}
                            {!isAdmin && (
                                <div className="flex items-center border border-gray-300 rounded-full w-fit">
                                    <button onClick={() => handleQuantity("dec")} className="p-3 hover:text-batik-gold transition-colors">
                                        <Minus className="w-4 h-4" />
                                    </button>
                                    <span className="w-12 text-center font-bold text-batik-900">{quantity}</span>
                                    <button onClick={() => handleQuantity("inc")} className="p-3 hover:text-batik-gold transition-colors">
                                        <Plus className="w-4 h-4" />
                                    </button>
                                </div>
                            )}

                            {/* Tombol Action (Beda Admin & User) */}
                            {isAdmin ? (
                                <Button size="lg" disabled className="flex-1 rounded-full bg-gray-200 text-gray-500 cursor-not-allowed h-12">
                                    Mode Admin (View Only)
                                </Button>
                            ) : (
                                <Button 
                                    onClick={handleAddToCart}
                                    size="lg" 
                                    className="flex-1 rounded-full bg-batik-gold hover:bg-yellow-600 text-black font-bold text-lg h-12"
                                >
                                    <ShoppingBag className="w-5 h-5 mr-2" /> Masukkan Keranjang
                                </Button>
                            )}
                        </div>

                        {/* Value Props */}
                        <div className="grid grid-cols-2 gap-4 text-xs font-medium text-gray-500 bg-batik-50 p-4 rounded-xl border border-batik-100">
                            <div className="flex items-center gap-2">
                                <ShieldCheck className="w-5 h-5 text-batik-gold" />
                                100% Batik Asli
                            </div>
                            <div className="flex items-center gap-2">
                                <Truck className="w-5 h-5 text-batik-gold" />
                                Pengiriman Cepat
                            </div>
                            <div className="col-span-2 text-center pt-2 border-t border-batik-200 mt-2 text-batik-600">
                                "Setiap motif memiliki filosofi dan doa bagi pemakainya."
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}