import React from "react";
import WelcomeLayout from "@/Layouts/WelcomeLayout";
import { Head, Link, usePage } from "@inertiajs/react";
import { ShoppingBag, Heart, Package, UserCircle } from "lucide-react";
import { Card, CardContent } from "@/Components/ui/card";

export default function Dashboard() {
    const { auth } = usePage().props;

    return (
        <WelcomeLayout>
            <Head title="Dashboard - BatikNesia" />
            
            <div className="min-h-screen bg-[#faf9f6] py-12">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-8">
                        <h1 className="text-3xl font-serif font-bold text-batik-900">Dashboard</h1>
                        <p className="text-gray-600 mt-2 text-lg">
                            Selamat datang kembali, <span className="font-semibold text-batik-gold">{auth.user.name}</span>
                        </p>
                    </div>

                    {/* Quick Action Cards */}
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        
                        <Link href="/pembayaran" className="group">
                            <Card className="border-0 shadow-md hover:shadow-xl transition-all duration-300 group-hover:-translate-y-1">
                                <CardContent className="p-6 flex flex-col items-center justify-center text-center gap-4">
                                    <div className="w-16 h-16 rounded-full bg-batik-50 flex items-center justify-center group-hover:bg-batik-gold/10 transition-colors">
                                        <Package className="w-8 h-8 text-batik-900" />
                                    </div>
                                    <div>
                                        <h3 className="font-bold text-gray-900 group-hover:text-batik-gold transition-colors">Pesanan Saya</h3>
                                        <p className="text-sm text-gray-500 mt-1">Lacak status pengiriman</p>
                                    </div>
                                </CardContent>
                            </Card>
                        </Link>

                        <Link href="/wishlist" className="group">
                            <Card className="border-0 shadow-md hover:shadow-xl transition-all duration-300 group-hover:-translate-y-1">
                                <CardContent className="p-6 flex flex-col items-center justify-center text-center gap-4">
                                    <div className="w-16 h-16 rounded-full bg-batik-50 flex items-center justify-center group-hover:bg-batik-gold/10 transition-colors">
                                        <Heart className="w-8 h-8 text-red-500" />
                                    </div>
                                    <div>
                                        <h3 className="font-bold text-gray-900 group-hover:text-batik-gold transition-colors">Wishlist</h3>
                                        <p className="text-sm text-gray-500 mt-1">Produk favorit Anda</p>
                                    </div>
                                </CardContent>
                            </Card>
                        </Link>

                        <Link href="/cart" className="group">
                            <Card className="border-0 shadow-md hover:shadow-xl transition-all duration-300 group-hover:-translate-y-1">
                                <CardContent className="p-6 flex flex-col items-center justify-center text-center gap-4">
                                    <div className="w-16 h-16 rounded-full bg-batik-50 flex items-center justify-center group-hover:bg-batik-gold/10 transition-colors">
                                        <ShoppingBag className="w-8 h-8 text-batik-900" />
                                    </div>
                                    <div>
                                        <h3 className="font-bold text-gray-900 group-hover:text-batik-gold transition-colors">Keranjang</h3>
                                        <p className="text-sm text-gray-500 mt-1">Lanjutkan belanja</p>
                                    </div>
                                </CardContent>
                            </Card>
                        </Link>

                        <Link href={route("profile.edit")} className="group">
                            <Card className="border-0 shadow-md hover:shadow-xl transition-all duration-300 group-hover:-translate-y-1">
                                <CardContent className="p-6 flex flex-col items-center justify-center text-center gap-4">
                                    <div className="w-16 h-16 rounded-full bg-batik-50 flex items-center justify-center group-hover:bg-batik-gold/10 transition-colors">
                                        <UserCircle className="w-8 h-8 text-batik-900" />
                                    </div>
                                    <div>
                                        <h3 className="font-bold text-gray-900 group-hover:text-batik-gold transition-colors">Pengaturan Profil</h3>
                                        <p className="text-sm text-gray-500 mt-1">Ubah data diri Anda</p>
                                    </div>
                                </CardContent>
                            </Card>
                        </Link>

                    </div>
                </div>
            </div>
        </WelcomeLayout>
    );
}
