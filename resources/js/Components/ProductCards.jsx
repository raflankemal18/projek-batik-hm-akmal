import React from "react";
import { Link, router, usePage } from "@inertiajs/react"; // Tambahkan usePage
import { formatRupiah } from "@/lib/format";
import FallbackImage from "@/Components/ui/FallbackImage";
import { motion } from "framer-motion";
import { ShoppingCart, Eye } from "lucide-react";
import { Button } from "@/Components/ui/button";
import { Badge } from "@/Components/ui/badge";
import { toast } from "sonner";

export default function ProductCards({ products }) {
    // 1. AMBIL DATA AUTH UNTUK CEK ADMIN
    const { auth } = usePage().props;
    const isAdmin = auth?.user?.role === 'admin';
    
    const addToCart = (e, product) => {
        e.preventDefault(); 
        
        router.post("/cart/add", {
            product_id: product.id,
            size: product.sizes && product.sizes.length > 0 ? product.sizes[0].size : "M", 
            quantity: 1,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`${product.name} berhasil ditambahkan ke keranjang!`);
            },
            onError: () => {
                toast.error("Gagal menambahkan ke keranjang. Silakan login sebagai user.");
            }
        });
    };

    return (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 md:gap-8">
            {products.map((product, index) => (
                <motion.div
                    key={product.id}
                    initial={{ opacity: 0, y: 20 }}
                    whileInView={{ opacity: 1, y: 0 }}
                    viewport={{ once: true, margin: "-50px" }}
                    transition={{ delay: index * 0.05, duration: 0.4 }}
                    className="group relative bg-white dark:bg-zinc-900 rounded-2xl overflow-hidden border border-gray-100 dark:border-zinc-800 hover:shadow-xl hover:shadow-gray-200/50 dark:hover:shadow-zinc-900/50 transition-all duration-300"
                >
                    {/* Image Section */}
                    <div className="relative aspect-[4/5] overflow-hidden bg-gray-100 dark:bg-zinc-800">
                        <Link href={`/products/${product.id}`}>
                            <FallbackImage
                                src={product.images?.[0]?.image_path}
                                alt={product.name}
                                className="object-cover w-full h-full group-hover:scale-105 transition-transform duration-500 ease-out"
                            />
                        </Link>
                        
                        {/* Badges */}
                        <div className="absolute top-3 left-3 flex flex-col gap-2">
                            <Badge className="bg-white/90 text-black backdrop-blur-sm shadow-sm hover:bg-white">
                                New
                            </Badge>
                        </div>

                        {/* Floating Action Buttons (Hover Only) */}
                        <div className="absolute inset-x-0 bottom-4 flex justify-center gap-2 opacity-0 translate-y-4 group-hover:opacity-100 group-hover:translate-y-0 transition-all duration-300 ease-out px-4">
                            
                            {/* LOGIKA: Sembunyikan Tombol Cart Jika Admin */}
                            {!isAdmin && (
                                <Button 
                                    size="icon" 
                                    variant="secondary" 
                                    className="rounded-full w-10 h-10 bg-white/90 hover:bg-white text-black shadow-lg"
                                    onClick={(e) => addToCart(e, product)}
                                    title="Add to Cart"
                                >
                                    <ShoppingCart className="w-4 h-4" />
                                </Button>
                            )}

                            <Link href={`/products/${product.id}`}>
                                <Button 
                                    size="icon" 
                                    variant="secondary" 
                                    className="rounded-full w-10 h-10 bg-white/90 hover:bg-white text-black shadow-lg"
                                    title="View Detail"
                                >
                                    <Eye className="w-4 h-4" />
                                </Button>
                            </Link>
                        </div>
                    </div>

                    {/* Content Section */}
                    <div className="p-4">
                        <Link href={`/products/${product.id}`}>
                            <h3 className="font-semibold text-gray-900 dark:text-gray-100 truncate hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                                {product.name}
                            </h3>
                        </Link>
                        
                        <div className="flex items-center justify-between mt-2">
                            <div className="flex flex-col">
                                <span className="text-lg font-bold text-gray-900 dark:text-white">
                                    {formatRupiah(product.harga)}
                                </span>
                            </div>
                            <div className="text-xs text-gray-500 dark:text-gray-400 font-medium bg-gray-100 dark:bg-zinc-800 px-2 py-1 rounded-md">
                                {product.sizes ? product.sizes.length : 0} Sizes
                            </div>
                        </div>
                    </div>
                </motion.div>
            ))}
        </div>
    );
}