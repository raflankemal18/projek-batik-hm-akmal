import React, { useState } from "react";
import { Head, usePage, router, Link } from "@inertiajs/react";
import WelcomeLayout from "@/Layouts/WelcomeLayout";
import { formatRupiah } from "@/lib/format";
import InputGroup from "@/Components/ui/Field";
import FallbackImage from "@/Components/ui/FallbackImage";
import { Button } from "@/Components/ui/button";
import { MapPin, Phone, Mail, User, CreditCard, ShieldCheck, ArrowLeft, ShoppingBag } from "lucide-react";
import { toast } from "sonner";

export default function Checkout({ orders }) {
    const { auth } = usePage().props;
    
    const [values, setValues] = useState({
        name: auth.user?.name || "",
        email: auth.user?.email || "",
        phone: "",
        alamat: "",
        message: ""
    });

    const [loading, setLoading] = useState(false);

    const totalHarga = orders?.reduce(
        (sum, order) => sum + parseFloat(order.total_harga || 0), 0
    ) || 0;

    const handleChange = (e) => {
        setValues({ ...values, [e.target.id]: e.target.value });
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        setLoading(true);

        if (!values.alamat || !values.phone) {
            toast.error("Mohon lengkapi alamat dan nomor telepon.", {
                description: "Data ini diperlukan untuk pengiriman."
            });
            setLoading(false);
            return;
        }

        router.post('/checkout', {
            name: values.name,
            email: values.email,
            phone: values.phone,
            alamat: values.alamat,
            message: values.message,
        }, {
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
            onSuccess: () => {
                toast.success("Pesanan berhasil dibuat!");
            },
            onError: (errors) => {
                // Kontrak error D3/A.2: InsufficientStockException & EmptyCartException
                // dibalikkan handler terpusat di bootstrap/app.php sebagai errors.stock
                // (bukan errors.message). Kalau hanya baca errors.message, pesan
                // "Stok tidak cukup untuk ..." tidak pernah sampai ke user.
                toast.error(errors?.stock ? "Stok tidak mencukupi." : "Gagal memproses checkout.", {
                    description: errors?.stock || errors?.message,
                });
            },
        });
    };

    if (!orders || orders.length === 0) {
        return (
            <WelcomeLayout>
                <div className="flex flex-col items-center justify-center min-h-[70vh] text-center px-4">
                    <div className="w-24 h-24 bg-batik-50 rounded-full flex items-center justify-center mb-6">
                        <ShoppingBag className="w-10 h-10 text-batik-400" />
                    </div>
                    <h2 className="text-3xl font-serif font-bold text-batik-900 mb-2">Checkout Kosong</h2>
                    <p className="text-gray-500 mb-8 max-w-md">Tidak ada barang yang siap untuk dibayar. Pesanan lama mungkin sudah kedaluwarsa.</p>
                    <Link href="/products">
                        <Button className="bg-batik-900 text-white px-8 h-12 rounded-full hover:bg-batik-800 transition-colors">
                            Mulai Belanja Lagi
                        </Button>
                    </Link>
                </div>
            </WelcomeLayout>
        );
    }

    return (
        <WelcomeLayout>
            <Head title="Checkout - BatikNesia" />
            
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16">
                <div className="flex flex-col md:flex-row md:items-center justify-between border-b border-batik-200 pb-6 mb-8 gap-4">
                    <h1 className="text-3xl font-serif font-bold text-batik-900 flex items-center gap-3">
                        Pengiriman & Pembayaran
                    </h1>
                    <div className="text-sm text-gray-500 font-medium bg-batik-50 px-4 py-2 rounded-lg border border-batik-100">
                        Langkah 2 dari 3
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="grid lg:grid-cols-12 gap-8 lg:gap-12">
                    
                    {/* KOLOM KIRI: FORMULIR */}
                    <div className="lg:col-span-7 space-y-8 animate-in fade-in slide-in-from-left-4 duration-700">
                        
                        {/* Section Data Diri */}
                        <div className="bg-white p-6 md:p-8 rounded-xl border border-batik-100 shadow-sm hover:shadow-md transition-shadow">
                            <h3 className="text-lg font-bold text-batik-900 mb-6 flex items-center gap-2 pb-2 border-b border-gray-100">
                                <User className="w-5 h-5 text-batik-gold" /> Informasi Kontak
                            </h3>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <InputGroup label="Nama Lengkap" id="name" value={values.name} onChange={handleChange} icon={<User size={16}/>} />
                                <InputGroup label="Email" id="email" type="email" value={values.email} onChange={handleChange} icon={<Mail size={16}/>} />
                                <div className="md:col-span-2">
                                    <InputGroup label="Nomor Telepon (WhatsApp)" id="phone" testId="checkout-phone" type="tel" value={values.phone} onChange={handleChange} icon={<Phone size={16}/>} placeholder="0812..." />
                                </div>
                            </div>
                        </div>

                        {/* Section Alamat */}
                        <div className="bg-white p-6 md:p-8 rounded-xl border border-batik-100 shadow-sm hover:shadow-md transition-shadow">
                            <h3 className="text-lg font-bold text-batik-900 mb-6 flex items-center gap-2 pb-2 border-b border-gray-100">
                                <MapPin className="w-5 h-5 text-batik-gold" /> Alamat Pengiriman
                            </h3>
                            <div className="space-y-5">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Alamat Lengkap</label>
                                    <textarea
                                        id="alamat"
                                        data-testid="checkout-address"
                                        rows="3"
                                        className="w-full rounded-lg border-gray-300 focus:border-batik-gold focus:ring-1 focus:ring-batik-gold p-3 text-sm transition-all shadow-sm resize-none"
                                        placeholder="Nama Jalan, No Rumah, RT/RW, Kelurahan, Kecamatan, Kota, Kode Pos"
                                        value={values.alamat}
                                        onChange={handleChange}
                                    ></textarea>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Catatan Pesanan (Opsional)</label>
                                    <textarea
                                        id="message"
                                        data-testid="checkout-message"
                                        rows="2"
                                        className="w-full rounded-lg border-gray-300 focus:border-batik-gold focus:ring-1 focus:ring-batik-gold p-3 text-sm transition-all shadow-sm resize-none"
                                        placeholder="Contoh: Titip di pos satpam, atau bungkus kado"
                                        value={values.message}
                                        onChange={handleChange}
                                    ></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* KOLOM KANAN: RINGKASAN ORDER (STICKY) */}
                    <div className="lg:col-span-5 relative">
                        <div className="bg-white p-6 md:p-8 rounded-xl border border-batik-100 shadow-lg sticky top-24 animate-in fade-in slide-in-from-bottom-4 duration-700 delay-150">
                            <h3 className="font-serif font-bold text-gray-900 text-lg flex items-center justify-between border-b border-gray-100 pb-4 mb-4">
                                <span>Ringkasan Pesanan</span>
                                <span className="text-xs font-sans font-normal text-gray-500 bg-gray-100 px-2 py-1 rounded">
                                    {orders?.reduce((acc, o) => acc + (o.order_details?.length || 0), 0) || 0} Item
                                </span>
                            </h3>
                            
                            {/* List Barang */}
                            <div className="space-y-4 mb-6 max-h-80 overflow-y-auto pr-2 custom-scrollbar">
                                {orders?.map((order) => (
                                    <div key={order.id}>
                                        {order.order_details?.map((detail) => (
                                            <div key={detail.id} className="flex gap-4 mb-4 pb-4 border-b border-gray-100 last:border-0 last:mb-0 last:pb-0 group">
                                                <div className="w-16 h-20 bg-gray-100 rounded-md overflow-hidden shrink-0 border border-gray-200">
                                                    <FallbackImage 
                                                        src={detail.product?.images?.[0]?.image_path} 
                                                        className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                                        alt={detail.product?.name}
                                                    />
                                                </div>
                                                <div className="flex-1">
                                                    <h4 className="text-sm font-bold text-batik-900 line-clamp-1 font-serif">{detail.product?.name}</h4>
                                                    <p className="text-xs text-gray-500 mt-1">Size: <span className="font-medium text-batik-700">{detail.size}</span> | Qty: {detail.quantity}</p>
                                                    <p className="text-sm font-bold text-batik-gold mt-1">{formatRupiah(detail.harga)}</p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ))}
                            </div>

                            {/* Total Kalkulasi */}
                            <div className="space-y-3 pt-6 border-t-2 border-dashed border-gray-200">
                                <div className="flex justify-between text-sm text-gray-600">
                                    <span>Subtotal Produk</span>
                                    <span className="font-medium">{formatRupiah(totalHarga)}</span>
                                </div>
                                <div className="flex justify-between text-sm text-gray-600">
                                    <span>Biaya Layanan</span>
                                    <span className="font-medium">Rp 2.000</span>
                                </div>
                                <div className="flex justify-between items-center pt-4 mt-2 border-t border-gray-100">
                                    <span className="font-bold text-batik-900 text-lg">Total Bayar</span>
                                    <span className="font-bold text-2xl text-batik-gold font-serif">{formatRupiah(totalHarga + 2000)}</span>
                                </div>
                            </div>

                            <Button
                                data-testid="checkout-submit"
                                type="submit"
                                disabled={loading}
                                className="w-full mt-8 bg-batik-900 hover:bg-batik-800 text-white font-bold h-14 rounded-lg text-base shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition-all flex items-center justify-center gap-2"
                            >
                                {loading ? "Memproses..." : <><CreditCard className="w-5 h-5"/> Bayar Sekarang</>}
                            </Button>

                            <Link href="/products">
                                <Button 
                                    variant="ghost" 
                                    type="button"
                                    className="w-full mt-3 text-gray-500 hover:text-batik-900 hover:bg-gray-50"
                                >
                                    <ArrowLeft className="w-4 h-4 mr-2" /> Batal / Belanja Lagi
                                </Button>
                            </Link>

                            <div className="mt-6 flex items-center justify-center gap-2 text-[10px] uppercase tracking-wider text-gray-400 bg-batik-50 p-2 rounded text-center">
                                <ShieldCheck className="w-4 h-4 text-green-600" />
                                <span>Pembayaran Terenkripsi & Aman</span>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </WelcomeLayout>
    );
}
