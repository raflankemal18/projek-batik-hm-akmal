import React, { useState, useEffect } from "react";
import AdminLayout from "@/Components/component/admin-page-layout";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/Components/ui/card";
import { formatRupiah } from "@/lib/format";
// UPDATE IMPORTS: Gunakan icon yang lebih tegas & Import component baru
import { ShoppingBag, CircleDollarSign, UsersRound, PackageSearch, TrendingUp, BarChart2 } from "lucide-react";
import ModernStatsCard from "./components/ModernStatsCard"; 
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, BarChart, Bar, Legend } from 'recharts';

export default function Dashboard({ 
    usersCount, usersDelta, 
    productCount, productDelta, 
    orderCount, ordersDelta, 
    totalPenjualan, salesDelta, 
    topSelling 
}) {
    const [analytics, setAnalytics] = useState(null);

    useEffect(() => {
        fetch('/admin/analytics')
            .then(res => res.json())
            .then(data => setAnalytics(data))
            .catch(err => console.error("Failed to fetch analytics", err));
    }, []);

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
                        desc={salesDelta > 0 ? `+${salesDelta}%` : `${salesDelta}%`}
                        trend={salesDelta >= 0 ? "up" : "down"}
                    />
                    <ModernStatsCard 
                        title="Pesanan Baru" 
                        value={orderCount} 
                        icon={<ShoppingBag />} 
                        desc={ordersDelta > 0 ? `+${ordersDelta}%` : `${ordersDelta}%`}
                        trend={ordersDelta >= 0 ? "up" : "down"}
                    />
                    <ModernStatsCard 
                        title="Total Pelanggan" 
                        value={usersCount} 
                        icon={<UsersRound />} 
                        desc={usersDelta > 0 ? `+${usersDelta}%` : `${usersDelta}%`}
                        trend={usersDelta >= 0 ? "up" : "down"}
                    />
                    <ModernStatsCard 
                        title="Produk Baru" 
                        value={productCount} 
                        icon={<PackageSearch />} 
                        desc={productDelta > 0 ? `+${productDelta}%` : `${productDelta}%`}
                        trend={productDelta >= 0 ? "up" : "down"}
                    />
                </div>
                {/* ---------------------------------- */}

                {/* Analytics Charts */}
                {analytics && (
                    <div className="grid gap-6 lg:grid-cols-2 pt-4">
                        <Card className="shadow-lg border-0 ring-1 ring-gray-100">
                            <CardHeader className="border-b border-gray-50 bg-gray-50/50 px-6 py-4">
                                <div className="flex items-center gap-2">
                                    <TrendingUp className="w-5 h-5 text-batik-gold" />
                                    <CardTitle className="font-serif text-gray-900">Penjualan 7 Hari Terakhir</CardTitle>
                                </div>
                            </CardHeader>
                            <CardContent className="p-6">
                                <div className="h-[300px] w-full">
                                    <ResponsiveContainer width="100%" height="100%">
                                        <LineChart data={analytics.sales} margin={{ top: 5, right: 20, bottom: 5, left: 0 }}>
                                            <CartesianGrid strokeDasharray="3 3" vertical={false} />
                                            <XAxis dataKey="date" tick={{fontSize: 12}} />
                                            <YAxis tickFormatter={(val) => `Rp ${val / 1000}k`} tick={{fontSize: 12}} />
                                            <Tooltip formatter={(value) => formatRupiah(value)} />
                                            <Line type="monotone" dataKey="total" stroke="#D4AF37" strokeWidth={3} dot={{ r: 4 }} activeDot={{ r: 6 }} />
                                        </LineChart>
                                    </ResponsiveContainer>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="shadow-lg border-0 ring-1 ring-gray-100">
                            <CardHeader className="border-b border-gray-50 bg-gray-50/50 px-6 py-4">
                                <div className="flex items-center gap-2">
                                    <BarChart2 className="w-5 h-5 text-batik-gold" />
                                    <CardTitle className="font-serif text-gray-900">Top Kategori Produk</CardTitle>
                                </div>
                            </CardHeader>
                            <CardContent className="p-6">
                                <div className="h-[300px] w-full">
                                    <ResponsiveContainer width="100%" height="100%">
                                        <BarChart data={analytics.categories} layout="vertical" margin={{ top: 5, right: 30, left: 20, bottom: 5 }}>
                                            <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                                            <XAxis type="number" />
                                            <YAxis dataKey="category" type="category" width={100} tick={{fontSize: 12}} />
                                            <Tooltip />
                                            <Bar dataKey="total_sold" name="Terjual" fill="#1e3a8a" radius={[0, 4, 4, 0]} barSize={30} />
                                        </BarChart>
                                    </ResponsiveContainer>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                )}

                {/* Top Selling Table & Target (Sisa kode lama di bawah ini tetap sama) */}
                <div className="pt-4">
                     <Card className="shadow-lg border-0 ring-1 ring-gray-100">
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
                                        {topSelling.length === 0 ? (
                                            <tr>
                                                <td colSpan="3" className="px-6 py-8 text-center text-gray-500">
                                                    Belum ada produk terjual.
                                                </td>
                                            </tr>
                                        ) : topSelling.map((item, index) => (
                                            <tr key={index} className="bg-white border-b hover:bg-batik-50/50 transition-colors">
                                                <td className="px-6 py-4 font-medium text-gray-900 flex items-center gap-4">
                                                    <div className="w-10 h-10 rounded-lg bg-batik-900 flex items-center justify-center text-batik-gold font-bold text-sm font-serif shadow-sm">
                                                        #{index + 1}
                                                    </div>
                                                    <span className="text-base">{item.name}</span>
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    <span className="bg-green-50 text-green-700 font-bold text-sm px-3 py-1 rounded-full border border-green-100">
                                                        {item.total_quantity} Pcs
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 text-right font-bold text-batik-900 text-base font-serif">
                                                    {formatRupiah(item.harga * item.total_quantity)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AdminLayout>
    );
}