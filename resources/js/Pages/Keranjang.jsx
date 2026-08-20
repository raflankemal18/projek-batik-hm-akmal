import React, { useState, useEffect } from "react";
import { formatRupiah } from "@/helper/formatRupiah";
import { router, Link, Head } from "@inertiajs/react";
import WelcomeLayout from "@/Layouts/WelcomeLayout";
import { Button } from "@/Components/ui/button";
import { Trash2, Plus, Minus, ArrowRight, ShoppingBag } from "lucide-react";
import { toast } from "sonner";
import ConfirmDialog from "@/Components/ConfirmDialog"; // Import Modal Custom

export default function Keranjang({ keranjangs }) {
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(false);

    // State untuk Modal Konfirmasi Hapus
    const [deleteState, setDeleteState] = useState({
        isOpen: false,
        itemId: null,
        itemName: ""
    });

    useEffect(() => {
        const flattenedItems = keranjangs
            .map((keranjang) =>
                keranjang.details.map((detail) => ({
                    id: detail.id,
                    product_id: detail.product.id,
                    name: detail.product.name,
                    harga: detail.product.harga,
                    image_url: detail.product.images,
                    size: detail.size,
                    quantity: detail.quantity,
                }))
            )
            .flat();
        setItems(flattenedItems);
    }, [keranjangs]);

    // --- UPDATE QUANTITY ---
    const handleUpdateQuantity = (id, newQuantity) => {
        if (newQuantity < 1) return;
        setLoading(true);
        router.post(
            "/cart/update",
            { cart_detail_id: id, quantity: newQuantity },
            {
                preserveScroll: true,
                onFinish: () => setLoading(false),
                onError: () => {
                    setLoading(false);
                    toast.error("Gagal mengubah jumlah barang.");
                }
            }
        );
    };

    // --- BUKA MODAL HAPUS ---
    const openDeleteModal = (id, name) => {
        setDeleteState({
            isOpen: true,
            itemId: id,
            itemName: name
        });
    };

    // --- EKSEKUSI HAPUS (Dipanggil dari Modal) ---
    const executeDelete = () => {
        if (deleteState.itemId) {
            router.post('/cart/remove', { 
                cart_detail_id: deleteState.itemId 
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success("Item berhasil dihapus");
                    setDeleteState({ isOpen: false, itemId: null, itemName: "" });
                },
                onError: () => {
                    toast.error("Gagal menghapus item");
                    setDeleteState({ ...deleteState, isOpen: false });
                }
            });
        }
    };

    // --- CHECKOUT ---
    const handleCheckout = () => {
        const orderData = {
            items: items.map((item) => ({
                product_id: item.product_id,
                size: item.size,
                quantity: item.quantity,
                harga: item.harga,
            })),
            totalHarga: calculateTotal(),
        };

        router.post("/checkout", orderData, {
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
            onError: (error) => {
                console.error("Checkout failed:", error);
                toast.error("Gagal memproses checkout.");
            },
        });
    };

    const calculateTotal = () => items.reduce((sum, item) => sum + item.harga * item.quantity, 0);
    const total = calculateTotal();

    // --- TAMPILAN JIKA KOSONG ---
    if (!items || items.length === 0) {
        return (
            <WelcomeLayout>
                <Head title="Keranjang Belanja - BatikNesia" />
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 min-h-[70vh] flex flex-col justify-center">
                    <div className="text-center py-16 bg-white rounded-2xl shadow-sm border border-batik-200 max-w-2xl mx-auto relative overflow-hidden w-full">
                        <div className="absolute top-0 right-0 w-32 h-32 bg-batik-gold/10 rounded-bl-full -mr-8 -mt-8 pointer-events-none"></div>
                        <div className="absolute bottom-0 left-0 w-24 h-24 bg-batik-900/5 rounded-tr-full -ml-6 -mb-6 pointer-events-none"></div>

                        <div className="relative z-10">
                            <div className="w-24 h-24 bg-batik-50 rounded-full flex items-center justify-center mx-auto mb-6">
                                <ShoppingBag className="w-10 h-10 text-batik-400" />
                            </div>
                            <h2 className="font-serif text-3xl font-bold text-batik-900 mb-3">Keranjang Anda Kosong</h2>
                            <p className="text-gray-500 mb-8 max-w-md mx-auto px-4">
                                Sepertinya Anda belum menemukan batik yang cocok. Mari temukan gaya etnik terbaikmu hari ini.
                            </p>
                            <Link href="/products">
                                <Button className="bg-batik-900 hover:bg-batik-800 text-white px-8 h-12 rounded-full text-base shadow-lg hover:shadow-xl transition-all transform hover:-translate-y-1">
                                    Mulai Belanja
                                </Button>
                            </Link>
                        </div>
                    </div>
                </div>
            </WelcomeLayout>
        );
    }

    // --- TAMPILAN KERANJANG TERISI ---
    return (
        <WelcomeLayout>
            <Head title="Keranjang Belanja - BatikNesia" />
            
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16">
                <div className="flex items-end justify-between border-b border-batik-200 pb-4 mb-8">
                    <h1 className="text-3xl font-serif font-bold text-batik-900">Keranjang Belanja</h1>
                    <span className="text-batik-500 font-medium">{items.length} Item</span>
                </div>

                <div className="grid lg:grid-cols-12 gap-8 lg:gap-12">
                    {/* Items List */}
                    <div className="lg:col-span-8 space-y-6">
                        {items.map((item) => (
                            <div key={item.id} className="flex gap-4 sm:gap-6 p-4 bg-white rounded-xl shadow-sm border border-batik-100 hover:border-batik-300 transition-colors">
                                <div className="w-24 h-32 sm:w-32 sm:h-40 flex-shrink-0 rounded-lg overflow-hidden bg-gray-100 border border-gray-100">
                                    <img 
                                        src={item.image_url && item.image_url.length > 0 ? `/storage/${item.image_url[0].image_path.replace("public/", "")}` : "https://placehold.co/400?text=No+Image"} 
                                        alt={item.name} 
                                        className="w-full h-full object-cover"
                                    />
                                </div>

                                <div className="flex-1 flex flex-col justify-between py-1">
                                    <div>
                                        <div className="flex justify-between items-start">
                                            <h3 className="font-serif text-lg font-medium text-batik-900 leading-snug">{item.name}</h3>
                                            
                                            {/* TOMBOL HAPUS -> Trigger Modal */}
                                            <button 
                                                onClick={() => openDeleteModal(item.id, item.name)} 
                                                className="text-gray-400 hover:text-red-500 transition-colors p-2 rounded-full hover:bg-red-50"
                                                title="Hapus Item"
                                            >
                                                <Trash2 className="w-5 h-5" />
                                            </button>
                                        </div>
                                        <p className="text-sm text-gray-500 mt-1">Ukuran: <span className="font-medium text-batik-900 bg-batik-50 px-2 py-0.5 rounded text-xs">{item.size}</span></p>
                                        <p className="text-lg font-bold text-batik-gold mt-2 font-serif">{formatRupiah(item.harga)}</p>
                                    </div>

                                    <div className="flex items-center gap-4 mt-3">
                                        <div className="flex items-center border border-gray-300 rounded-full bg-gray-50">
                                            <button 
                                                disabled={loading || item.quantity <= 1}
                                                onClick={() => handleUpdateQuantity(item.id, item.quantity - 1)}
                                                className="p-2 hover:bg-white hover:text-batik-gold rounded-full transition-colors disabled:opacity-30"
                                            >
                                                <Minus className="w-4 h-4" />
                                            </button>
                                            <span className="w-10 text-center font-bold text-sm text-batik-900">{item.quantity}</span>
                                            <button 
                                                disabled={loading}
                                                onClick={() => handleUpdateQuantity(item.id, item.quantity + 1)}
                                                className="p-2 hover:bg-white hover:text-batik-gold rounded-full transition-colors disabled:opacity-30"
                                            >
                                                <Plus className="w-4 h-4" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* Summary Sticky */}
                    <div className="lg:col-span-4">
                        <div className="bg-white p-6 rounded-xl shadow-lg border border-batik-100 sticky top-24">
                            <h3 className="text-lg font-serif font-bold text-batik-900 mb-6">Ringkasan Pesanan</h3>
                            <div className="space-y-4 pb-6 border-b border-gray-100 text-sm">
                                <div className="flex justify-between text-gray-600">
                                    <span>Subtotal ({items.length} item)</span>
                                    <span>{formatRupiah(total)}</span>
                                </div>
                                <div className="flex justify-between text-gray-600">
                                    <span>Pengiriman</span>
                                    <span className="text-green-600 font-medium">Dihitung saat Checkout</span>
                                </div>
                            </div>
                            <div className="flex justify-between items-center py-6">
                                <span className="font-bold text-batik-900 text-base">Total Tagihan</span>
                                <span className="font-bold text-2xl text-batik-gold font-serif">{formatRupiah(total)}</span>
                            </div>
                            <Button 
                                onClick={handleCheckout} 
                                disabled={loading}
                                className="w-full h-14 bg-batik-900 hover:bg-batik-800 text-white font-bold rounded-lg text-base flex items-center justify-center gap-2 shadow-lg hover:shadow-xl transition-all"
                            >
                                {loading ? "Memproses..." : <>Checkout Sekarang <ArrowRight className="w-5 h-5" /></>}
                            </Button>
                            <div className="mt-6 flex items-center justify-center gap-2 text-xs text-gray-400 bg-gray-50 p-3 rounded-lg border border-gray-100">
                                <ShieldIcon /> 
                                <span>Jaminan Pembayaran Aman</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* MODAL KONFIRMASI HAPUS */}
            <ConfirmDialog 
                isOpen={deleteState.isOpen}
                onClose={() => setDeleteState({ ...deleteState, isOpen: false })}
                onConfirm={executeDelete}
                title="Hapus dari Keranjang?"
                description={`Apakah Anda yakin ingin menghapus "${deleteState.itemName}"?`}
                confirmText="Ya, Hapus"
                cancelText="Batal"
                variant="danger"
            />
        </WelcomeLayout>
    );
}

const ShieldIcon = () => (
    <svg className="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
);