import React, { useState } from "react";
import AdminLayout from "@/Components/component/admin-page-layout";
import ProductModal from "./form/modal/Modal"; 
import ConfirmDialog from "@/Components/ConfirmDialog"; // Import Modal Baru
import TableToolbar from "@/Components/ui/TableToolbar";
import Pagination from "@/Components/Pagination";
import useTableFilters from "@/hooks/useTableFilters";
import { formatRupiah } from "@/lib/format";
import FallbackImage from "@/Components/ui/FallbackImage";
import { Package, Plus, Pencil, Trash2, Archive, RotateCcw } from "lucide-react";
import { Button } from "@/Components/ui/button";
import { Link, router } from "@inertiajs/react";
import { toast } from "sonner";

export default function AdminProduct({ products, archived = false }) {
    const [isModalOpen, setIsModalOpen] = useState(false);
    // Simpan ID (bukan objek produk) supaya modal ikut memakai data terbaru:
    // setelah motif disimpan, list di-refresh dan objek di state akan basi —
    // itu yang membuat daftar motif di dalam modal tidak ikut ter-update.
    const [editingProductId, setEditingProductId] = useState(null);
    
    const [deleteModalOpen, setDeleteModalOpen] = useState(false);
    const [productToDelete, setProductToDelete] = useState(null);

    const [selectedIds, setSelectedIds] = useState([]);

    const list = products?.data || products || [];
    const {
        search, setSearch,
        sort, setSort, direction, setDirection,
    } = useTableFilters("created_at", "desc");

    const sortOptions = [
        { label: "Terbaru", sort: "created_at", direction: "desc" },
        { label: "Terlama", sort: "created_at", direction: "asc" },
        { label: "Nama A-Z", sort: "name", direction: "asc" },
        { label: "Nama Z-A", sort: "name", direction: "desc" },
        { label: "Harga Terendah", sort: "harga", direction: "asc" },
        { label: "Harga Tertinggi", sort: "harga", direction: "desc" },
    ];

    // Buka Modal Tambah
    const handleCreate = () => {
        setEditingProductId(null);
        setIsModalOpen(true);
    };

    // Buka Modal Edit
    const handleEdit = (product) => {
        setEditingProductId(product.id);
        setIsModalOpen(true);
    };

    // Objek produk selalu diambil ulang dari list terbaru.
    const editingProduct = list.find((p) => p.id === editingProductId) || null;

    // Buka Modal Konfirmasi Hapus (GANTI LOGIKA LAMA)
    const confirmDelete = (product) => {
        setProductToDelete(product);
        setDeleteModalOpen(true);
    };

    // Eksekusi Hapus (Dipanggil jika user klik "Ya, Hapus" di modal)
    const executeDelete = () => {
        if (productToDelete) {
            router.delete(route('admin.products.destroy', productToDelete.id), {
                onSuccess: () => toast.success("Produk berhasil dihapus"),
                onError: () => toast.error("Gagal menghapus produk")
            });
        }
    };

    const handleSelectAll = (e) => {
        if (e.target.checked) {
            setSelectedIds(list.map(p => p.id));
        } else {
            setSelectedIds([]);
        }
    };

    const handleSelectOne = (e, id) => {
        if (e.target.checked) {
            setSelectedIds([...selectedIds, id]);
        } else {
            setSelectedIds(selectedIds.filter(selectedId => selectedId !== id));
        }
    };

    const handleBulkDelete = () => {
        if (confirm("Apakah Anda yakin ingin menghapus produk terpilih?")) {
            router.delete('/admin/products/bulk', {
                data: { ids: selectedIds },
                onSuccess: () => {
                    setSelectedIds([]);
                    toast.success("Produk terpilih berhasil dihapus");
                },
                onError: () => toast.error("Gagal menghapus produk")
            });
        }
    };

    return (
        <AdminLayout>
            <div className="space-y-6">
                
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h1 className="text-3xl font-serif font-bold text-gray-900">
                            {archived ? 'Arsip Produk' : 'Manajemen Produk'}
                        </h1>
                        <p className="text-gray-500">
                            {archived
                                ? 'Produk yang diarsipkan tidak tampil di katalog, tetapi riwayat pembelanjaan & invoice-nya tetap utuh. Bisa dipulihkan kapan saja.'
                                : 'Kelola stok, harga, dan varian batik.'}
                        </p>
                    </div>
                    
                    {archived ? (
                        <Link
                            href={route('admin.products')}
                            data-testid="admin-product-exit-archive"
                            className="px-6 py-3 rounded-full bg-batik-900 text-white text-sm font-bold uppercase tracking-wide hover:bg-batik-800 transition-colors inline-flex items-center gap-2"
                        >
                            <Package className="w-4 h-4" /> Kembali ke Daftar Produk
                        </Link>
                    ) : (
                        <div className="flex items-center gap-2">
                            <Link
                                href={route('admin.products.archived')}
                                data-testid="admin-product-archived-link"
                                title="Produk yang diarsipkan"
                                className="px-4 py-3 rounded-full border border-batik-300 text-batik-900 hover:bg-batik-50 transition-colors inline-flex items-center gap-2"
                            >
                                <Archive className="w-4 h-4" />
                            </Link>
                            <Button data-testid="admin-product-create" onClick={handleCreate} className="bg-batik-gold hover:bg-yellow-600 text-batik-900 font-bold gap-2">
                                <Plus className="w-4 h-4" /> Tambah Produk
                            </Button>
                        </div>
                    )}
                </div>

                <div className="bg-white rounded-xl shadow-sm border border-batik-100 overflow-hidden">
                    {!archived && (
                    <TableToolbar
                        search={search}
                        onSearchChange={setSearch}
                        sort={sort}
                        direction={direction}
                        onSortChange={(s, d) => { setSort(s); setDirection(d); }}
                        sortOptions={sortOptions}
                        searchPlaceholder={archived ? 'Cari di arsip...' : 'Cari produk...'}
                    />
                    )}
                    {!archived && selectedIds.length > 0 && (
                        <div className="flex items-center gap-4 p-4 border-b border-gray-100 bg-gray-50/50">
                            <span className="text-sm font-medium text-gray-700">
                                {selectedIds.length} produk terpilih
                            </span>
                            <div className="flex items-center gap-2 ml-auto">
                                <button 
                                    onClick={handleBulkDelete}
                                    className="px-4 py-1.5 bg-red-50 hover:bg-red-100 text-red-600 rounded-md text-sm font-medium transition-colors border border-red-200"
                                >
                                    Hapus Terpilih
                                </button>
                            </div>
                        </div>
                    )}
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm text-left text-gray-500">
                            <thead className="text-xs text-gray-700 uppercase bg-gray-50 border-b border-gray-100">
                                <tr>
                                    <th className="px-6 py-4 w-4">
                                        <input 
                                            type="checkbox"
                                            className="rounded border-gray-300 text-batik-600 shadow-sm focus:border-batik-300 focus:ring focus:ring-batik-200 focus:ring-opacity-50"
                                            checked={list.length > 0 && selectedIds.length === list.length}
                                            onChange={handleSelectAll}
                                        />
                                    </th>
                                    <th className="px-6 py-4">Produk</th>
                                    <th className="px-6 py-4">Harga</th>
                                    <th className="px-6 py-4">Varian & Stok</th>
                                    <th className="px-6 py-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {list.length === 0 ? (
                                    <tr>
                                        <td colSpan="5" className="px-6 py-8 text-center text-gray-500">
                                            Tidak ada produk ditemukan.
                                        </td>
                                    </tr>
                                ) : list.map((product) => (
                                    <tr key={product.id} data-testid="admin-product-row" data-product-name={product.name} className="hover:bg-gray-50 transition-colors">
                                        <td className={`px-6 py-4 ${archived ? 'hidden' : ''}`}>
                                            <input 
                                                type="checkbox"
                                                className="rounded border-gray-300 text-batik-600 shadow-sm focus:border-batik-300 focus:ring focus:ring-batik-200 focus:ring-opacity-50"
                                                checked={selectedIds.includes(product.id)}
                                                onChange={(e) => handleSelectOne(e, product.id)}
                                            />
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="flex items-center gap-4">
                                                <div className="h-16 w-16 flex-shrink-0 overflow-hidden rounded-lg border border-gray-200 bg-gray-100">
                                                    <FallbackImage
                                                        src={product.images?.[0]?.image_path}
                                                        alt={product.name}
                                                        className="h-full w-full object-cover object-center"
                                                    />
                                                </div>
                                                <div>
                                                    <div className="font-bold text-gray-900 text-base">{product.name}</div>
                                                    <div className="text-xs text-gray-500 line-clamp-1 max-w-xs">
                                                        {product.description || "Tidak ada deskripsi"}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 font-medium text-gray-900">
                                            {formatRupiah(product.harga)}
                                        </td>
                                        <td className="px-6 py-4">
                                            {/* Produk bermotif menampilkan motif (nama + stok),
                                                produk lama tetap menampilkan ukuran S/M/L. */}
                                            <div className="flex flex-wrap gap-2">
                                                {product.has_variants
                                                    ? (product.variants || []).map((variant) => (
                                                        <span
                                                            key={variant.id}
                                                            data-testid="admin-variant-badge"
                                                            className={`inline-flex items-center px-2 py-1 rounded text-xs font-medium border ${
                                                                variant.stock > 0
                                                                    ? 'bg-batik-50 text-batik-800 border-batik-200'
                                                                    : 'bg-red-50 text-red-700 border-red-200'
                                                            } ${variant.is_active ? '' : 'opacity-50 line-through'}`}
                                                        >
                                                            {variant.name}: {variant.stock}
                                                        </span>
                                                    ))
                                                    : product.sizes.map((size) => (
                                                        <span key={size.id} className={`inline-flex items-center px-2 py-1 rounded text-xs font-medium border ${
                                                            size.stock > 0 
                                                            ? 'bg-blue-50 text-blue-700 border-blue-200' 
                                                            : 'bg-red-50 text-red-700 border-red-200'
                                                        }`}>
                                                            {size.size}: {size.stock}
                                                        </span>
                                                    ))}
                                                {product.has_variants && (product.variants || []).length === 0 && (
                                                    <span className="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1">
                                                        Belum ada motif
                                                    </span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 text-center">
                                            <div className="flex items-center justify-center gap-2">
                                                {archived && (
                                                    <button
                                                        type="button"
                                                        data-testid="admin-product-restore"
                                                        onClick={() => router.post(route('admin.products.restore', product.id), {}, {
                                                            onSuccess: () => toast.success('Produk dipulihkan'),
                                                            onError: () => toast.error('Gagal memulihkan produk'),
                                                        })}
                                                        title="Pulihkan produk"
                                                        className="p-2 text-batik-900 hover:bg-batik-50 rounded"
                                                    >
                                                        <RotateCcw className="w-4 h-4" />
                                                    </button>
                                                )}
                                                <button 
                                                    onClick={() => handleEdit(product)}
                                                    className="p-2 text-blue-600 hover:bg-blue-50 rounded-full transition-colors"
                                                    title="Edit"
                                                >
                                                    <Pencil className="w-4 h-4" />
                                                </button>
                                                <button 
                                                    // Panggil confirmDelete, bukan window.confirm
                                                    onClick={() => confirmDelete(product)}
                                                    className="p-2 text-red-600 hover:bg-red-50 rounded-full transition-colors"
                                                    title="Hapus"
                                                >
                                                    <Trash2 className="w-4 h-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {products.links && (
                        <div className="flex justify-end pt-4 pb-2 px-6 border-t border-gray-100">
                            <Pagination links={products.links} />
                        </div>
                    )}
                </div>
            </div>

            {/* Modal Input Produk */}
            <ProductModal 
                isOpen={isModalOpen} 
                onClose={() => setIsModalOpen(false)} 
                product={editingProduct} 
            />

            {/* Modal Konfirmasi Hapus */}
            <ConfirmDialog 
                isOpen={deleteModalOpen}
                onClose={() => setDeleteModalOpen(false)}
                onConfirm={executeDelete}
                title="Hapus Produk?"
                description={`Anda akan menghapus "${productToDelete?.name}". Produk yang sudah dihapus tidak dapat dikembalikan.`}
                confirmText="Hapus Permanen"
            />
        </AdminLayout>
    );
}