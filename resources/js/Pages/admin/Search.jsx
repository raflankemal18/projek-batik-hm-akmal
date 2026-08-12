import React from "react";
import AdminLayout from "@/Components/component/admin-page-layout";
import { Link } from "@inertiajs/react";
import { formatRupiah } from "@/lib/format";
import FallbackImage from "@/Components/ui/FallbackImage";
import { Package, Users, ShoppingBag, SearchX, Search as SearchIcon } from "lucide-react";

export default function Search({ q, products, users, payments }) {
    const hasQuery = q.trim().length > 0;

    return (
        <AdminLayout>
            <div className="space-y-8">
                <div>
                    <h1 className="text-3xl font-serif font-bold text-gray-900">Hasil Pencarian</h1>
                    <p className="text-gray-500">
                        {hasQuery
                            ? `Menampilkan hasil untuk "${q}".`
                            : "Ketik kata kunci di kolom pencarian di atas untuk mencari produk, pelanggan, atau pesanan."}
                    </p>
                </div>

                {!hasQuery && (
                    <div className="flex flex-col items-center justify-center py-16 text-center">
                        <div className="w-16 h-16 bg-batik-50 rounded-full flex items-center justify-center mb-4">
                            <SearchIcon className="w-8 h-8 text-batik-gold" />
                        </div>
                        <p className="text-gray-400">Belum ada kata kunci.</p>
                    </div>
                )}

                {hasQuery && products.length === 0 && users.length === 0 && payments.length === 0 && (
                    <div className="flex flex-col items-center justify-center py-16 text-center">
                        <div className="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4">
                            <SearchX className="w-8 h-8 text-gray-400" />
                        </div>
                        <p className="text-gray-400">Tidak ada hasil untuk "{q}".</p>
                    </div>
                )}

                {/* Produk */}
                {hasQuery && (
                    <Section
                        icon={<Package className="w-5 h-5 text-batik-gold" />}
                        title={`Produk (${products.length})`}
                        href="/admin/products"
                        linkLabel="Lihat Semua Produk"
                    >
                        {products.length === 0 ? (
                            <EmptyRow text="Tidak ada produk ditemukan." />
                        ) : (
                            products.map((p) => (
                                <tr key={p.id} className="bg-white border-b hover:bg-batik-50/50 transition-colors">
                                    <td className="px-6 py-4">
                                        <div className="flex items-center gap-4">
                                            <div className="h-14 w-14 shrink-0 overflow-hidden rounded-lg border border-gray-200 bg-gray-100">
                                                <FallbackImage
                                                    src={p.images?.[0]?.image_path}
                                                    alt={p.name}
                                                    className="h-full w-full object-cover"
                                                />
                                            </div>
                                            <div>
                                                <div className="font-bold text-gray-900">{p.name}</div>
                                                <div className="text-xs text-gray-500 line-clamp-1 max-w-xs">
                                                    {p.description || "Tidak ada deskripsi"}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 font-medium text-gray-900">
                                        {formatRupiah(p.harga)}
                                    </td>
                                </tr>
                            ))
                        )}
                    </Section>
                )}

                {/* Pelanggan */}
                {hasQuery && (
                    <Section
                        icon={<Users className="w-5 h-5 text-batik-gold" />}
                        title={`Pelanggan (${users.length})`}
                        href="/admin/users"
                        linkLabel="Lihat Semua Pelanggan"
                    >
                        {users.length === 0 ? (
                            <EmptyRow text="Tidak ada pelanggan ditemukan." />
                        ) : (
                            users.map((u) => (
                                <tr key={u.id} className="bg-white border-b hover:bg-batik-50/50 transition-colors">
                                    <td className="px-6 py-4">
                                        <div className="flex items-center gap-3">
                                            <div className="w-9 h-9 rounded-full bg-batik-100 text-batik-900 flex items-center justify-center font-bold border border-batik-200">
                                                {u.name.charAt(0).toUpperCase()}
                                            </div>
                                            <span className="font-medium text-gray-900">{u.name}</span>
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 text-gray-600">{u.email}</td>
                                    <td className="px-6 py-4">
                                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ${
                                            u.role === 'admin'
                                                ? 'bg-purple-50 text-purple-700 border-purple-200'
                                                : 'bg-blue-50 text-blue-700 border-blue-200'
                                        }`}>
                                            {u.role === 'admin' ? 'Administrator' : 'Customer'}
                                        </span>
                                    </td>
                                </tr>
                            ))
                        )}
                    </Section>
                )}

                {/* Pesanan */}
                {hasQuery && (
                    <Section
                        icon={<ShoppingBag className="w-5 h-5 text-batik-gold" />}
                        title={`Pesanan (${payments.length})`}
                        href="/admin/orders"
                        linkLabel="Lihat Semua Pesanan"
                    >
                        {payments.length === 0 ? (
                            <EmptyRow text="Tidak ada pesanan ditemukan." />
                        ) : (
                            payments.map((payment) => (
                                <tr key={payment.id} className="bg-white border-b hover:bg-batik-50/50 transition-colors">
                                    <td className="px-6 py-4 font-mono text-xs font-bold text-batik-900">
                                        #{payment.id}
                                    </td>
                                    <td className="px-6 py-4">
                                        <span className="font-medium text-gray-900">
                                            {payment.payment_details[0]?.name || payment.user?.name || "-"}
                                        </span>
                                        <div className="text-xs text-gray-500">
                                            {payment.transaction_id}
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 font-bold text-gray-900">
                                        {formatRupiah(payment.total_harga)}
                                    </td>
                                    <td className="px-6 py-4">
                                        <StatusBadge status={payment.status} />
                                    </td>
                                </tr>
                            ))
                        )}
                    </Section>
                )}
            </div>
        </AdminLayout>
    );
}

function Section({ icon, title, href, linkLabel, children }) {
    return (
        <div className="bg-white rounded-xl shadow-sm border border-batik-100 overflow-hidden">
            <div className="flex items-center justify-between px-6 py-4 border-b border-gray-50 bg-gray-50/50">
                <h2 className="flex items-center gap-2 text-lg font-bold text-gray-900">
                    {icon}
                    {title}
                </h2>
                <Link href={href} className="text-xs font-medium text-batik-gold hover:underline">
                    {linkLabel}
                </Link>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full text-sm text-left text-gray-500">
                    <tbody>{children}</tbody>
                </table>
            </div>
        </div>
    );
}

function EmptyRow({ text }) {
    return (
        <tr>
            <td className="px-6 py-8 text-center text-gray-400">{text}</td>
        </tr>
    );
}

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
