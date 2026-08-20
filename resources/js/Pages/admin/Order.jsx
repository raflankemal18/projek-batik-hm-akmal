import React from "react";
import AdminLayout from "@/Components/component/admin-page-layout";
import { formatRupiah } from "@/helper/formatRupiah";
import { Badge } from "@/Components/ui/badge";
import { Calendar, User, Phone, MapPin } from "lucide-react";

export default function Order({ payments }) {
    return (
        <AdminLayout>
            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-serif font-bold text-gray-900">Pesanan Masuk</h1>
                    <p className="text-gray-500">Kelola transaksi dan status pembayaran pelanggan.</p>
                </div>

                <div className="bg-white rounded-xl shadow-sm border border-batik-100 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm text-left text-gray-500">
                            <thead className="text-xs text-gray-700 uppercase bg-gray-50 border-b">
                                <tr>
                                    <th className="px-6 py-4">ID Order</th>
                                    <th className="px-6 py-4">Pelanggan</th>
                                    <th className="px-6 py-4">Detail Item</th>
                                    <th className="px-6 py-4">Total & Status</th>
                                    <th className="px-6 py-4">Tanggal</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {payments.map((payment) => (
                                    <tr key={payment.id} className="hover:bg-gray-50 transition-colors align-top">
                                        <td className="px-6 py-4 font-mono text-xs font-bold text-batik-900">
                                            #{payment.id}
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="space-y-1">
                                                <div className="flex items-center gap-2 font-bold text-gray-900">
                                                    <User className="w-3 h-3 text-batik-gold" /> 
                                                    {payment.payment_details[0]?.name}
                                                </div>
                                                <div className="flex items-center gap-2 text-xs">
                                                    <Phone className="w-3 h-3" /> 
                                                    {payment.payment_details[0]?.phone}
                                                </div>
                                                <div className="flex items-start gap-2 text-xs italic text-gray-400 max-w-[200px]">
                                                    <MapPin className="w-3 h-3 shrink-0 mt-0.5" /> 
                                                    {payment.payment_details[0]?.alamat}
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="space-y-2">
                                                {payment.payment_details.map((detail, idx) => (
                                                    <div key={idx} className="flex items-center gap-3 text-xs bg-gray-50 p-2 rounded border border-gray-100">
                                                        <div className="font-medium text-gray-900">{detail.product?.name}</div>
                                                        <div className="text-gray-500 border-l pl-2 ml-auto shrink-0">
                                                            Size: {detail.size} x {detail.quantity}
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="flex flex-col gap-2">
                                                <div className="font-bold text-gray-900">{formatRupiah(payment.total_harga)}</div>
                                                <StatusBadge status={payment.status} />
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 text-xs text-gray-500">
                                            <div className="flex items-center gap-2">
                                                <Calendar className="w-3 h-3" />
                                                {new Date(payment.created_at).toLocaleDateString("id-ID", {
                                                    day: 'numeric', month: 'short', year: 'numeric'
                                                })}
                                            </div>
                                            <div className="text-[10px] mt-1 text-gray-400">
                                                {new Date(payment.created_at).toLocaleTimeString("id-ID")}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}

// Component Badge Status Warna-warni
function StatusBadge({ status }) {
    const styles = {
        pending: "bg-yellow-100 text-yellow-800 border-yellow-200",
        success: "bg-green-100 text-green-800 border-green-200",
        failed: "bg-red-100 text-red-800 border-red-200",
    };

    const labels = {
        pending: "Menunggu Bayar",
        success: "Lunas / Diproses",
        failed: "Gagal / Batal",
    };

    return (
        <span className={`px-2.5 py-0.5 rounded-full text-xs font-medium border ${styles[status] || styles.pending} text-center w-fit`}>
            {labels[status] || status}
        </span>
    );
}