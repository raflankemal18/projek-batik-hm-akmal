import React, { useState } from "react";
import AdminLayout from "@/Components/component/admin-page-layout";
import Pagination from "@/Components/Pagination";
import ConfirmDialog from "@/Components/ConfirmDialog"; // Import komponen modal custom
import TableToolbar from "@/Components/ui/TableToolbar";
import useTableFilters from "@/hooks/useTableFilters";
import { router } from "@inertiajs/react";
import { Trash2, Mail, Shield } from "lucide-react";
import { toast } from "sonner";

export default function User({ users }) {
    // State untuk mengontrol Modal Konfirmasi
    const [deleteModalOpen, setDeleteModalOpen] = useState(false);
    const [userToDelete, setUserToDelete] = useState(null);

    const {
        search, setSearch,
        sort, setSort, direction, setDirection,
    } = useTableFilters("created_at", "desc");

    const sortOptions = [
        { label: "Terbaru", sort: "created_at", direction: "desc" },
        { label: "Terlama", sort: "created_at", direction: "asc" },
        { label: "Nama A-Z", sort: "name", direction: "asc" },
        { label: "Nama Z-A", sort: "name", direction: "desc" },
        { label: "Email A-Z", sort: "email", direction: "asc" },
        { label: "Email Z-A", sort: "email", direction: "desc" },
    ];

    // Fungsi: Buka modal saat tombol hapus diklik
    const confirmDelete = (user) => {
        setUserToDelete(user);
        setDeleteModalOpen(true);
    };

    // Fungsi: Eksekusi hapus ke backend setelah dikonfirmasi di modal
    const executeDelete = () => {
        if (userToDelete) {
            router.delete(route('admin.users.destroy', userToDelete.id), {
                onSuccess: () => {
                    toast.success("User berhasil dihapus");
                    setDeleteModalOpen(false);
                    setUserToDelete(null);
                },
                onError: () => {
                    toast.error("Gagal menghapus user");
                    setDeleteModalOpen(false);
                }
            });
        }
    };

    return (
        <AdminLayout>
            <div className="space-y-6">
                {/* Header Halaman */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h1 className="text-3xl font-serif font-bold text-gray-900">Data Pelanggan</h1>
                        <p className="text-gray-500">Kelola daftar pengguna terdaftar di BatikNesia.</p>
                    </div>
                </div>

                {/* Tabel Data User */}
                <div className="bg-white rounded-xl shadow-sm border border-batik-100 overflow-hidden">
                    <TableToolbar
                        search={search}
                        onSearchChange={setSearch}
                        sort={sort}
                        direction={direction}
                        onSortChange={(s, d) => { setSort(s); setDirection(d); }}
                        sortOptions={sortOptions}
                        searchPlaceholder="Cari nama / email..."
                    />
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm text-left text-gray-500">
                            <thead className="text-xs text-gray-700 uppercase bg-gray-50 border-b border-gray-100">
                                <tr>
                                    <th className="px-6 py-4 w-16 text-center">No</th>
                                    <th className="px-6 py-4">Nama Lengkap</th>
                                    <th className="px-6 py-4">Email</th>
                                    <th className="px-6 py-4">Role</th>
                                    <th className="px-6 py-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {users.data.length > 0 ? (
                                    users.data.map((user, index) => (
                                        <tr key={user.id} className="hover:bg-gray-50 transition-colors">
                                            <td className="px-6 py-4 text-center font-medium text-gray-900">
                                                {index + 1 + (users.current_page - 1) * users.per_page}
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-3">
                                                    <div className="w-9 h-9 rounded-full bg-batik-100 text-batik-900 flex items-center justify-center font-bold border border-batik-200">
                                                        {user.name.charAt(0).toUpperCase()}
                                                    </div>
                                                    <span className="font-medium text-gray-900">{user.name}</span>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-2 text-gray-600">
                                                    <Mail className="w-3.5 h-3.5" /> {user.email}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium border ${
                                                    user.role === 'admin' 
                                                    ? 'bg-purple-50 text-purple-700 border-purple-200' 
                                                    : 'bg-blue-50 text-blue-700 border-blue-200'
                                                }`}>
                                                    <Shield className="w-3 h-3" /> 
                                                    {user.role === 'admin' ? 'Administrator' : 'Customer'}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-center">
                                                {/* Cegah menghapus sesama admin untuk keamanan dasar */}
                                                {user.role !== 'admin' && (
                                                    <button 
                                                        onClick={() => confirmDelete(user)}
                                                        className="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-full transition-all"
                                                        title="Hapus User"
                                                    >
                                                        <Trash2 className="w-4 h-4" />
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan="5" className="px-6 py-8 text-center text-gray-500">
                                            Tidak ada data user ditemukan.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Pagination */}
                <div className="flex justify-end pt-2">
                    <Pagination links={users.links} />
                </div>
            </div>

            {/* Modal Konfirmasi Hapus Custom */}
            <ConfirmDialog 
                isOpen={deleteModalOpen}
                onClose={() => setDeleteModalOpen(false)}
                onConfirm={executeDelete}
                title="Hapus Pengguna?"
                description={`Apakah Anda yakin ingin menghapus user "${userToDelete?.name}"? Tindakan ini akan menghapus akses login dan data terkait secara permanen.`}
                confirmText="Hapus User"
                cancelText="Batal"
                variant="danger"
            />
        </AdminLayout>
    );
}