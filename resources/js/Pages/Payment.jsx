import React, { useEffect } from "react";
import { Head, router } from "@inertiajs/react";
import WelcomeLayout from "@/Layouts/WelcomeLayout";
import { formatRupiah } from "@/lib/format";
import { Button } from "@/Components/ui/button";
import { CheckCircle, Clock, AlertTriangle, XCircle, History, Package } from "lucide-react";
import { toast } from "sonner";
import Pagination from "@/Components/Pagination";

export default function Payment({ payments }) {
    const paymentsList = payments?.data || payments || [];
    
    // Cari transaksi yang statusnya pending (Belum dibayar)
    const pendingPayment = paymentsList.find(p => p.status === 'pending');
    
    // Filter riwayat (Success & Failed)
    const historyPayments = paymentsList.filter(p => p.status !== 'pending');

    useEffect(() => {
        // Setup Midtrans Snap (Ambil Client Key dari ENV)
        const clientKey = import.meta.env.VITE_MIDTRANS_CLIENT_KEY;
        const snapSrcUrl = import.meta.env.VITE_MIDTRANS_SNAP_URL || "https://app.sandbox.midtrans.com/snap/snap.js";

        if(!document.querySelector(`script[src="${snapSrcUrl}"]`)){
            const script = document.createElement("script");
            script.src = snapSrcUrl;
            script.setAttribute("data-client-key", clientKey);
            script.async = true;
            document.body.appendChild(script);
        }
    }, []);

    const handlePay = (snapToken) => {
        if (window.snap) {
            window.snap.pay(snapToken, {
                onSuccess: () => {
                    toast.success("Pembayaran Berhasil!");
                    router.reload(); // reload dari server, status dari webhook
                },
                onPending: () => {
                    toast.info("Menunggu pembayaran...");
                    router.reload();
                },
                onError: () => {
                    toast.error("Pembayaran Gagal.");
                    router.reload();
                },
                onClose: () => {
                    toast.warning("Pop-up ditutup.");
                }
            });
        }
    };

    return (
        <WelcomeLayout>
            <Head title="Riwayat Pesanan - BatikNesia" />
            
            <div className="max-w-4xl mx-auto px-4 py-12">
                <h1 className="text-3xl font-serif font-bold text-batik-900 mb-8 border-b border-batik-200 pb-4">
                    Pesanan Saya
                </h1>

                {/* --- 1. SEKSI TAGIHAN PENDING (Hanya muncul jika ada) --- */}
                {pendingPayment && (
                    <div className="mb-12 animate-in fade-in slide-in-from-top-4 duration-700">
                        <div className="bg-white rounded-2xl shadow-xl border-l-4 border-l-yellow-400 overflow-hidden relative">
                            <div className="p-6 md:p-8">
                                <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                                    <div className="flex items-center gap-3">
                                        <div className="p-3 bg-yellow-100 rounded-full text-yellow-600 animate-pulse">
                                            <Clock className="w-6 h-6" />
                                        </div>
                                        <div>
                                            <h2 className="text-xl font-bold text-gray-900">Menunggu Pembayaran</h2>
                                            <p className="text-sm text-gray-500">Invoice #{pendingPayment.id}</p>
                                        </div>
                                    </div>
                                    <div className="text-left md:text-right">
                                        <p className="text-sm text-gray-500">Total Tagihan</p>
                                        <p className="text-3xl font-serif font-bold text-batik-gold">{formatRupiah(pendingPayment.total_harga)}</p>
                                    </div>
                                </div>

                                {/* Rincian Item Singkat */}
                                <div className="bg-gray-50 rounded-lg p-4 mb-6 border border-gray-100">
                                    <p className="text-sm font-bold text-gray-700 mb-2">Item:</p>
                                    <ul className="space-y-1">
                                        {pendingPayment.payment_details.map((item, idx) => (
                                            <li key={idx} className="text-sm text-gray-600 flex justify-between">
                                                <span>{item.quantity}x {item.product?.name || "Produk Batik"} ({item.size})</span>
                                                <span className="font-medium">{formatRupiah(item.harga * item.quantity)}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>

                                <div className="flex flex-col sm:flex-row gap-3">
                                    <Button 
                                        onClick={() => handlePay(pendingPayment.snap_token)}
                                        className="flex-1 bg-batik-gold hover:bg-yellow-600 text-batik-900 font-bold h-12 text-base shadow-md"
                                    >
                                        Bayar Sekarang
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* --- 2. SEKSI RIWAYAT TRANSAKSI (Selalu Muncul) --- */}
                <div className="space-y-6">
                    <div className="flex items-center gap-2 mb-4">
                        <History className="w-5 h-5 text-batik-900" />
                        <h2 className="text-xl font-bold text-gray-900">Riwayat Transaksi</h2>
                    </div>

                    {historyPayments.length > 0 ? (
                        <div className="space-y-4">
                            {historyPayments.map((p) => (
                                <div key={p.id} className="bg-white p-5 rounded-xl border border-batik-100 shadow-sm hover:shadow-md transition-shadow flex flex-col md:flex-row justify-between md:items-center gap-4">
                                    <div className="flex items-start gap-4">
                                        <div className={`p-3 rounded-full ${p.status === 'success' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'}`}>
                                            {p.status === 'success' ? <CheckCircle className="w-5 h-5" /> : <XCircle className="w-5 h-5" />}
                                        </div>
                                        <div>
                                            <p className="font-bold text-gray-900">Order #{p.id}</p>
                                            <p className="text-xs text-gray-500 mb-1">{new Date(p.created_at).toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</p>
                                            <div className="flex items-center gap-2 text-sm text-gray-600">
                                                <Package className="w-4 h-4" />
                                                <span>{p.payment_details.length} Barang</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div className="flex flex-row md:flex-col justify-between items-center md:items-end border-t md:border-t-0 pt-3 md:pt-0 mt-2 md:mt-0">
                                        <p className="font-serif font-bold text-batik-900 text-lg">{formatRupiah(p.total_harga)}</p>
                                        <div className="flex flex-col items-end gap-2">
                                            <span className={`px-3 py-1 rounded-full text-xs font-bold uppercase ${
                                                p.status === 'success' 
                                                ? 'bg-green-100 text-green-700' 
                                                : 'bg-red-100 text-red-700'
                                            }`}>
                                                {p.status === 'success' ? 'LUNAS / DIPROSES' : 'BATAL / GAGAL'}
                                            </span>
                                            {p.status === 'success' && p.order?.fulfillment_status && (
                                                <div className="text-xs font-bold text-batik-900 bg-batik-100 px-2 py-1 rounded-md mt-1 border border-batik-200">
                                                    Status: {p.order.fulfillment_status === 'processing' ? 'Diproses' : p.order.fulfillment_status === 'shipped' ? 'Dikirim' : 'Diterima'}
                                                </div>
                                            )}
                                            {p.status === 'success' && (
                                                <a href={`/payments/${p.id}/invoice`} target="_blank" rel="noopener noreferrer" className="text-xs text-batik-900 font-bold hover:underline">
                                                    Cetak Invoice
                                                </a>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="text-center py-12 bg-gray-50 rounded-xl border-dashed border-2 border-gray-200">
                            <p className="text-gray-400">Belum ada riwayat transaksi.</p>
                        </div>
                    )}
                    
                    {payments.links && payments.links.length > 3 && (
                        <div className="flex justify-center mt-6">
                            <Pagination links={payments.links} />
                        </div>
                    )}
                </div>
            </div>
        </WelcomeLayout>
    );
}