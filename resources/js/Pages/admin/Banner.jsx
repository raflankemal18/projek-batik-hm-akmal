import React, { useState, useRef } from "react";
import AdminLayout from "@/Components/component/admin-page-layout";
import { Button } from "@/Components/ui/button";
import { Plus, Pencil, Trash2, Image as ImageIcon } from "lucide-react";
import { router } from "@inertiajs/react";
import { toast } from "sonner";
import ConfirmDialog from "@/Components/ConfirmDialog";

export default function AdminBanner({ banners }) {
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editingBanner, setEditingBanner] = useState(null);
    const [deleteModalOpen, setDeleteModalOpen] = useState(false);
    const [bannerToDelete, setBannerToDelete] = useState(null);

    const [formData, setFormData] = useState({
        title: '',
        image: null,
        is_active: true
    });
    
    const fileInputRef = useRef(null);

    const handleCreate = () => {
        setEditingBanner(null);
        setFormData({ title: '', image: null, is_active: true });
        setIsModalOpen(true);
    };

    const handleEdit = (banner) => {
        setEditingBanner(banner);
        setFormData({ title: banner.title, image: null, is_active: banner.is_active });
        setIsModalOpen(true);
    };

    const confirmDelete = (banner) => {
        setBannerToDelete(banner);
        setDeleteModalOpen(true);
    };

    const executeDelete = () => {
        if (bannerToDelete) {
            router.delete(route('admin.banners.destroy', bannerToDelete.id), {
                onSuccess: () => toast.success("Banner berhasil dihapus"),
                onError: () => toast.error("Gagal menghapus banner")
            });
        }
    };

    const toggleStatus = (banner) => {
        router.patch(route('admin.banners.toggle', banner.id), {}, {
            preserveScroll: true,
            onSuccess: () => toast.success("Status banner diperbarui"),
        });
    };

    const submitForm = (e) => {
        e.preventDefault();
        
        const data = new FormData();
        data.append('title', formData.title);
        data.append('is_active', formData.is_active ? 1 : 0);
        if (formData.image) {
            data.append('image', formData.image);
        }

        if (editingBanner) {
            data.append('_method', 'put');
            router.post(route('admin.banners.update', editingBanner.id), data, {
                onSuccess: () => {
                    toast.success("Banner berhasil diupdate");
                    setIsModalOpen(false);
                }
            });
        } else {
            router.post(route('admin.banners.store'), data, {
                onSuccess: () => {
                    toast.success("Banner berhasil ditambahkan");
                    setIsModalOpen(false);
                }
            });
        }
    };

    return (
        <AdminLayout>
            <div className="space-y-6">
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-3xl font-serif font-bold text-gray-900">Manajemen Banner</h1>
                        <p className="text-gray-500">Kelola banner promo di halaman utama.</p>
                    </div>
                    <Button onClick={handleCreate} className="bg-batik-gold hover:bg-yellow-600 text-batik-900 font-bold gap-2">
                        <Plus className="w-4 h-4" /> Tambah Banner
                    </Button>
                </div>

                <div className="bg-white rounded-xl shadow-sm border border-batik-100 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm text-left text-gray-500">
                            <thead className="text-xs text-gray-700 uppercase bg-gray-50 border-b border-gray-100">
                                <tr>
                                    <th className="px-6 py-4">Image</th>
                                    <th className="px-6 py-4">Title</th>
                                    <th className="px-6 py-4">Status</th>
                                    <th className="px-6 py-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {banners.length === 0 ? (
                                    <tr>
                                        <td colSpan="4" className="px-6 py-8 text-center text-gray-500">Belum ada banner.</td>
                                    </tr>
                                ) : banners.map((banner) => (
                                    <tr key={banner.id} className="hover:bg-gray-50">
                                        <td className="px-6 py-4">
                                            <div className="w-32 h-16 rounded overflow-hidden bg-gray-100">
                                                <img src={banner.image_url} alt={banner.title} className="w-full h-full object-cover" />
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 font-medium text-gray-900">{banner.title}</td>
                                        <td className="px-6 py-4">
                                            <button 
                                                onClick={() => toggleStatus(banner)}
                                                className={`px-3 py-1 rounded-full text-xs font-bold ${banner.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'}`}
                                            >
                                                {banner.is_active ? 'Aktif' : 'Non-aktif'}
                                            </button>
                                        </td>
                                        <td className="px-6 py-4 text-center">
                                            <div className="flex items-center justify-center gap-2">
                                                <button onClick={() => handleEdit(banner)} className="p-2 text-blue-600 hover:bg-blue-50 rounded-full">
                                                    <Pencil className="w-4 h-4" />
                                                </button>
                                                <button onClick={() => confirmDelete(banner)} className="p-2 text-red-600 hover:bg-red-50 rounded-full">
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

            {isModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
                    <div className="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                            <h3 className="font-serif text-xl font-bold text-gray-900">
                                {editingBanner ? 'Edit Banner' : 'Tambah Banner'}
                            </h3>
                            <button onClick={() => setIsModalOpen(false)} className="text-gray-400 hover:text-gray-600">✕</button>
                        </div>
                        <form onSubmit={submitForm} className="p-6 space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Judul Banner</label>
                                <input 
                                    type="text" 
                                    value={formData.title}
                                    onChange={(e) => setFormData({...formData, title: e.target.value})}
                                    required 
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-batik-gold focus:ring-batik-gold sm:text-sm"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Gambar (Rekomendasi rasio lebar)</label>
                                <input 
                                    type="file"
                                    ref={fileInputRef}
                                    onChange={(e) => setFormData({...formData, image: e.target.files[0]})}
                                    accept="image/*"
                                    className="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-batik-50 file:text-batik-700 hover:file:bg-batik-100"
                                />
                            </div>
                            <div className="flex items-center gap-2">
                                <input 
                                    type="checkbox" 
                                    id="is_active"
                                    checked={formData.is_active}
                                    onChange={(e) => setFormData({...formData, is_active: e.target.checked})}
                                    className="rounded border-gray-300 text-batik-gold focus:ring-batik-gold"
                                />
                                <label htmlFor="is_active" className="text-sm text-gray-700">Aktifkan Banner</label>
                            </div>
                            
                            <div className="pt-4 flex justify-end gap-3">
                                <Button type="button" variant="outline" onClick={() => setIsModalOpen(false)}>Batal</Button>
                                <Button type="submit" className="bg-batik-gold hover:bg-yellow-600 text-batik-900 font-bold">Simpan</Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            <ConfirmDialog 
                isOpen={deleteModalOpen}
                onClose={() => setDeleteModalOpen(false)}
                onConfirm={executeDelete}
                title="Hapus Banner?"
                description="Anda akan menghapus banner ini secara permanen."
                confirmText="Hapus"
            />
        </AdminLayout>
    );
}
