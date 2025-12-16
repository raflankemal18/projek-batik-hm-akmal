import React from "react";
import AdminLayout from "@/Components/component/admin-page-layout";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/Components/ui/card";
import { formatRupiah } from "@/helper/formatRupiah";
// UPDATE IMPORTS: Gunakan icon yang lebih tegas & Import component baru
import { ShoppingBag, CircleDollarSign, UsersRound, PackageSearch, TrendingUp } from "lucide-react";
import ModernStatsCard from "./components/ModernStatsCard"; // Pastikan path import benar

export default function Dashboard({ usersCount, productCount, orderCount, totalPenjualan, topSelling }) {
    return (
        <AdminLayout>
            <div className="space-y-8 relative">
                {/* Background Decoration (Opsional, agar area dashboard tidak terlalu polos) */}
                <div className="absolute top-0 inset-x-0 h-64 bg-gradient-to-b from-batik-gold/5 to-transparent -z-10 rounded-b-3xl" />

                {/* Page Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-serif font-bold text-gray-900">Ringkasan Bisnis</h1>
                        <p className="text-gray-500 mt-1">Pantau performa toko BatikNesia Anda secara real-time.</p>
                    </div>
                    <div className="text-sm text-batik-gold font-medium bg-batik-gold/10 px-4 py-2 rounded-full border border-batik-gold/20">
                        Update Terakhir: {new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})} WIB
                    </div>
                </div>

                {/* --- MODERN STATS CARDS SECTION --- */}
                <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                    <ModernStatsCard 
                        title="Total Pendapatan" 
                        value={formatRupiah(totalPenjualan)} 
                        icon={<CircleDollarSign />} 
                        desc="+20.1%"
                        trend="up"
                    />
                    <ModernStatsCard 
                        title="Pesanan Baru" 
                        value={orderCount} 
                        icon={<ShoppingBag />} 
                        desc="+12 Order"
                        trend="up"
                    />
                    <ModernStatsCard 
                        title="Total Pelanggan" 
                        value={usersCount} 
                        icon={<UsersRound />} 
                        desc="+5 User"
                        trend="up"
                    />
                    <ModernStatsCard 
                        title="Stok Produk" 
                        value={productCount} 
                        icon={<PackageSearch />} 
                        desc="Item Aktif"
                        trend="neutral" // Bisa disesuaikan logikanya nanti
                    />
                </div>
                {/* ---------------------------------- */}


                {/* Top Selling Table & Target (Sisa kode lama di bawah ini tetap sama) */}
                <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-7 pt-4">
                    {/* ... (Table Produk Terlaris Anda yang lama letakkan di sini) ... */}
                     <Card className="col-span-4 shadow-lg border-0 ring-1 ring-gray-100">
                        <CardHeader className="border-b border-gray-50 bg-gray-50/50 px-6 py-4">
                            <div className="flex items-center gap-2">
                                <TrendingUp className="w-5 h-5 text-batik-gold" />
                                <CardTitle className="font-serif text-gray-900">Produk Paling Laris</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="relative overflow-x-auto">
                                <table className="w-full text-sm text-left text-gray-500">
                                    <thead className="text-xs text-gray-700 uppercase bg-white border-b">
                                        <tr>
                                            <th className="px-6 py-4 font-bold">Nama Produk</th>
                                            <th className="px-6 py-4 text-center font-bold">Terjual</th>
                                            <th className="px-6 py-4 text-right font-bold">Total Nilai</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {Object.entries(topSelling).map(([productName, details], index) => (
                                            <tr key={index} className="bg-white border-b hover:bg-batik-50/50 transition-colors">
                                                <td className="px-6 py-4 font-medium text-gray-900 flex items-center gap-4">
                                                    <div className="w-10 h-10 rounded-lg bg-batik-900 flex items-center justify-center text-batik-gold font-bold text-sm font-serif shadow-sm">
                                                        #{index + 1}
                                                    </div>
                                                    <span className="text-base">{details.product_name}</span>
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    <span className="bg-green-50 text-green-700 font-bold text-sm px-3 py-1 rounded-full border border-green-100">
                                                        {details.total_quantity} Pcs
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 text-right font-bold text-batik-900 text-base font-serif">
                                                    {formatRupiah(details.harga_baju * details.total_quantity)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Target Placeholder (Yang lama, sedikit diperbaiki style-nya) */}
                    <Card className="col-span-3 shadow-lg border-0 bg-gradient-to-br from-batik-900 to-batik-800 text-white overflow-hidden relative">
                         {/* Background pattern */}
                         <div className="absolute inset-0 opacity-10 bg-kawung pointer-events-none"></div>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-3 text-xl font-serif">
                                Target Bulan Ini
                            </CardTitle>
                            <CardDescription className="text-batik-200">Progres pencapaian omzet.</CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col items-center justify-center py-12 relative z-10">
                            <div className="text-6xl font-serif font-bold text-batik-gold mb-3 drop-shadow-md">2%</div>
                            <p className="text-base text-batik-100 mb-6">Rp 200.000.000 lagi menuju target!</p>
                            <div className="w-full bg-black/30 h-4 rounded-full overflow-hidden p-0.5 border border-white/10">
                                <div className="bg-gradient-to-r from-batik-gold to-yellow-400 h-full rounded-full shadow-[0_0_10px_rgba(212,175,55,0.5)]" style={{width: '2%'}}></div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AdminLayout>
    );
}