import React from "react";
import { router } from "@inertiajs/react";
import AdminLayout from "@/Components/component/admin-page-layout";
import TableToolbar from "@/Components/ui/TableToolbar";
import Pagination from "@/Components/Pagination";
import useTableFilters from "@/hooks/useTableFilters";
import { formatRupiah } from "@/lib/format";
import { Badge } from "@/Components/ui/badge";
import { Calendar, User, Phone, MapPin, Truck, Download } from "lucide-react";
import { toast } from "sonner";

export default function Order({ payments }) {
    const list = payments?.data || payments || [];

    const {
        search, setSearch,
        sort, setSort, direction, setDirection,
    } = useTableFilters("created_at", "desc");

    const sortOptions = [
        { label: "Terbaru", sort: "created_at", direction: "desc" },
        { label: "Terlama", sort: "created_at", direction: "asc" },
        { label: "Total Tertinggi", sort: "total_harga", direction: "desc" },
        { label: "Total Terendah", sort: "total_harga", direction: "asc" },
        { label: "Status A-Z", sort: "status", direction: "asc" },
        { label: "Status Z-A", sort: "status", direction: "desc" },
    ];

    return (
        <AdminLayout>
            <div className="space-y-6">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h1 className="text-3xl font-serif font-bold text-gray-900">Pesanan Masuk</h1>
                        <p className="text-gray-500">Kelola transaksi dan status pembayaran pelanggan.</p>
                    </div>
                    <a
                        href="/admin/orders/export"
                        className="inline-flex items-center gap-2 px-4 py-2 bg-batik-900 hover:bg-batik-800 text-white rounded-lg font-medium transition-colors shadow-sm"
                    >
                        <Download className="w-4 h-4" />
                        Export CSV
                    </a>
                </div>

                <div className="bg-white rounded-xl shadow-sm border border-batik-100 overflow-hidden">
                    <TableToolbar
                        search={search}
                        onSearchChange={setSearch}
                        sort={sort}
                        direction={direction}
                        onSortChange={(s, d) => { setSort(s); setDirection(d); }}
                        sortOptions={sortOptions}
                        searchPlaceholder="Cari ID / nama / phone / alamat..."
                    />
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
                                {list.length === 0 ? (
                                    <tr>
                                        <td colSpan="5" className="px-6 py-8 text-center text-gray-500">
                                            Belum ada pesanan masuk.
                                        </td>
                                    </tr>
                                ) : list.map((payment) => (
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
                                                {payment.status === 'success' && payment.order && (
                                                    <FulfillmentSelect order={payment.order} />
                                                )}
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

                    {payments.links && (
                        <div className="flex justify-end pt-4 pb-2 px-6 border-t border-gray-100">
                            <Pagination links={payments.links} />
                        </div>
                    )}
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

function FulfillmentSelect({ order }) {
    if (!order) return null;
    
    const handleUpdate = (e) => {
        const val = e.target.value;
        if (!val) return;
        
        router.patch(`/admin/orders/${order.id}/fulfillment`, {
            fulfillment_status: val
        }, {
            preserveScroll: true,
            onSuccess: () => toast.success("Status pengiriman diperbarui")
        });
    };

    return (
        <div className="flex items-center gap-2 mt-2">
            <Truck className="w-4 h-4 text-gray-500" />
            <select 
                className="text-xs border-gray-200 rounded py-1 pl-2 pr-6 bg-gray-50 text-gray-700 w-full cursor-pointer focus:ring-batik-gold focus:border-batik-gold"
                value={order.fulfillment_status || ""}
                onChange={handleUpdate}
            >
                <option value="" disabled>Pilih Status...</option>
                <option value="processing">Diproses</option>
                <option value="shipped">Dikirim</option>
                <option value="delivered">Diterima</option>
            </select>
        </div>
    );
}