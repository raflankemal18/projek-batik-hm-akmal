import React, { useState } from "react";
import AdminLayout from "@/Components/component/admin-page-layout";
import ProductModal from "./form/modal/Modal"; 
import ConfirmDialog from "@/Components/ConfirmDialog"; // Import Modal Baru
import { formatRupiah } from "@/helper/formatRupiah";
import { Package, Plus, Pencil, Trash2 } from "lucide-react";
import { Button } from "@/Components/ui/button";
import { router } from "@inertiajs/react";
import { toast } from "sonner";

export default function AdminProduct({ products }) {
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editingProduct, setEditingProduct] = useState(null);
    
    // State untuk Konfirmasi Hapus
    const [deleteModalOpen, setDeleteModalOpen] = useState(false);
    const [productToDelete, setProductToDelete] = useState(null);

    // Buka Modal Tambah
    const handleCreate = () => {
        setEditingProduct(null);
        setIsModalOpen(true);
    };

    // Buka Modal Edit
    const handleEdit = (product) => {
        setEditingProduct(product);
        setIsModalOpen(true);
    };

    // Buka Modal Konfirmasi Hapus (GANTI LOGIKA LAMA)
    const confirmDelete = (product) => {
        setProductToDelete(product);
        setDeleteModalOpen(true);
    };

    // Eksekusi Hapus (Dipanggil jika user klik "Ya, Hapus" di modal)
    const executeDelete = () => {
        if (productToDelete) {
            router.delete(route('admin.product.delete', productToDelete.id), {
                onSuccess: () => toast.success("Produk berhasil dihapus"),
                onError: () => toast.error("Gagal menghapus produk")
            });
        }
    };

    return (
        <AdminLayout>
            <div className="space-y-6">
                
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h1 className="text-3xl font-serif font-bold text-gray-900">Manajemen Produk</h1>
                        <p className="text-gray-500">Kelola stok, harga, dan varian batik.</p>
                    </div>
                    
                    <Button onClick={handleCreate} className="bg-batik-gold hover:bg-yellow-600 text-batik-900 font-bold gap-2">
                        <Plus className="w-4 h-4" /> Tambah Produk
                    </Button>
                </div>

                <div className="bg-white rounded-xl shadow-sm border border-batik-100 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm text-left text-gray-500">
                            <thead className="text-xs text-gray-700 uppercase bg-gray-50 border-b border-gray-100">
                                <tr>
                                    <th className="px-6 py-4">Produk</th>
                                    <th className="px-6 py-4">Harga</th>
                                    <th className="px-6 py-4">Varian & Stok</th>
                                    <th className="px-6 py-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {products.map((product) => (
                                    <tr key={product.id} className="hover:bg-gray-50 transition-colors">
                                        <td className="px-6 py-4">
                                            <div className="flex items-center gap-4">
                                                <div className="h-16 w-16 flex-shrink-0 overflow-hidden rounded-lg border border-gray-200 bg-gray-100">
                                                    <img
                                                        src={product.images?.[0] ? `/storage/${product.images[0].image_path.replace("public/", "")}` : "https://placehold.co/100"}
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
                                            <div className="flex flex-wrap gap-2">
                                                {product.sizes.map((size) => (
                                                    <span key={size.id} className={`inline-flex items-center px-2 py-1 rounded text-xs font-medium border ${
                                                        size.stock > 0 
                                                        ? 'bg-blue-50 text-blue-700 border-blue-200' 
                                                        : 'bg-red-50 text-red-700 border-red-200'
                                                    }`}>
                                                        {size.size}: {size.stock}
                                                    </span>
                                                ))}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 text-center">
                                            <div className="flex items-center justify-center gap-2">
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