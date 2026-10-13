import React, { useEffect, useState } from "react";
import { router } from "@inertiajs/react";
import { Plus, Trash2, Pencil, Palette, Save, X, UploadCloud, ImageOff } from "lucide-react";
import { Button } from "@/Components/ui/button";
import { formatRupiah } from "@/lib/format";
import { storageUrl } from "@/lib/image";
import { toast } from "sonner";

const EMPTY_FORM = { name: "", price: "", stock: "" };

function VariantThumb({ variant }) {
    if (!variant.image_path) {
        return (
            <div
                role="img"
                aria-label="Foto motif belum ada"
                className="w-12 h-14 rounded-md bg-batik-50 border border-batik-100 flex items-center justify-center shrink-0"
            >
                <ImageOff className="w-4 h-4 text-batik-300" />
            </div>
        );
    }

    return (
        <img
            src={storageUrl(variant.image_path)}
            alt={variant.name}
            className="w-12 h-14 rounded-md object-cover border border-batik-100 shrink-0"
        />
    );
}

/**
 * Kelola motif kain dari halaman admin.
 *
 * Setiap motif dikirim sebagai FormData terpisah ke endpoint motif
 * (admin/products/{product}/variants), bukan sebagai bagian dari form produk —
 * jadi satu motif yang gagal simpan tidak damaging produknya, dan produk yang
 * belum pernah disimpan (mode tambah) cukup diberi tahu untuk menyimpan dulu.
 */
export default function VariantManager({ product, onChanged }) {
    const [variants, setVariants] = useState(product?.variants || []);
    const [form, setForm] = useState(EMPTY_FORM);
    const [image, setImage] = useState(null);
    const [editingId, setEditingId] = useState(null);
    const [busy, setBusy] = useState(false);

    // Reload dari server setiap props berubah (mis. setelah Inertia visit lain).
    useEffect(() => {
        setVariants(product?.variants || []);
    }, [product?.variants, product?.id]);

    const resetForm = () => {
        setForm(EMPTY_FORM);
        setImage(null);
        setEditingId(null);
    };

    const buildFormData = () => {
        const fd = new FormData();
        fd.append("name", form.name);
        fd.append("price", form.price);
        fd.append("stock", form.stock);
        if (image) {
            fd.append("image", image);
        }

        return fd;
    };

    // Sengaja BUKAN <form onSubmit>: komponen ini berada di dalam <form> modal
    // produk, dan HTML tidak boleh punya form bersarang — browser menutup form
    // dalam sehingga tombolnya justru men-submit form produk.
    const submit = () => {
        if (!form.name.trim() || !form.price || form.stock === "") {
            toast.error("Nama, harga, dan stok motif wajib diisi.");
            return;
        }

        setBusy(true);

        const options = {
            onSuccess: () => toast.success(editingId ? "Motif diperbarui" : "Motif ditambahkan"),
            onError: (errors) => toast.error(
                "Gagal menyimpan motif",
                { description: Object.values(errors)[0] },
            ),
            onFinish: () => {
                setBusy(false);
                resetForm();
                onChanged?.();
            },
        };

        if (editingId) {
            // FormData tidak bisa di-spread: Object.entries(new FormData())
            // kosong, jadi {_method:'PUT', ...fd} akan mengirim request tanpa
            // nama/harga/stok sama sekali dan gagal validasi.
            const fd = buildFormData();
            fd.append("_method", "PUT");

            router.post(
                route("admin.products.variants.update", [product.id, editingId]),
                fd,
                options,
            );
        } else {
            router.post(route("admin.products.variants.store", product.id), buildFormData(), options);
        }
    };

    const remove = (variant) => {
        if (!window.confirm(`Hapus motif "${variant.name}"? Motif yang sudah pernah dibeli akan dinonaktifkan, bukan dihapus.`)) {
            return;
        }

        router.delete(route("admin.products.variants.destroy", [product.id, variant.id]), {
            onSuccess: () => toast.success("Motif dihapus"),
            onError: () => toast.error("Gagal menghapus motif"),
            onFinish: () => onChanged?.(),
        });
    };

    const startEdit = (variant) => {
        setEditingId(variant.id);
        setForm({ name: variant.name, price: String(variant.price), stock: String(variant.stock) });
        setImage(null);
    };

    if (!product) {
        return null;
    }

    return (
        <div className="bg-gray-50 p-4 rounded-lg border border-gray-200" data-testid="variant-manager">
            <div className="flex items-center justify-between mb-3">
                <label className="block text-sm font-bold text-batik-900 flex items-center gap-2">
                    <Palette className="w-4 h-4 text-batik-gold" /> Motif Kain
                    <span className="text-xs font-normal text-gray-500">
                        ({variants.length} motif, {variants.filter((v) => v.is_active).length} aktif)
                    </span>
                </label>
                {variants.length === 0 && (
                    <span className="text-[11px] text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1">
                        Foto motif boleh menyusul — nama saja cukup dulu.
                    </span>
                )}
            </div>

            {variants.length === 0 ? (
                <p className="text-xs text-gray-500 mb-3">
                    Belum ada motif. Tanpa motif, produk ini memakai pilihan ukuran biasa.
                </p>
            ) : (
                <ul className="space-y-2 mb-4">
                    {variants.map((variant) => (
                        <li
                            key={variant.id}
                            data-testid="variant-row"
                            data-variant-name={variant.name}
                            className="flex items-center gap-3 bg-white border border-gray-200 rounded-lg px-3 py-2"
                        >
                            <VariantThumb variant={variant} />

                            <div className="flex-1 min-w-0">
                                <p className="text-sm font-semibold text-gray-900 truncate">
                                    {variant.name}
                                    {!variant.is_active && (
                                        <span className="ml-2 text-[10px] uppercase tracking-wide bg-gray-200 text-gray-600 px-1.5 py-0.5 rounded">
                                            nonaktif
                                        </span>
                                    )}
                                </p>
                                <p className="text-xs text-gray-500">
                                    {formatRupiah(variant.price)} · stok {variant.stock}
                                </p>
                            </div>

                            <button
                                type="button"
                                onClick={() => startEdit(variant)}
                                className="p-2 text-gray-500 hover:text-batik-900 hover:bg-gray-50 rounded"
                                title="Ubah motif"
                            >
                                <Pencil className="w-4 h-4" />
                            </button>
                            <button
                                type="button"
                                onClick={() => remove(variant)}
                                className="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded"
                                title="Hapus motif"
                            >
                                <Trash2 className="w-4 h-4" />
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            <div className="space-y-3">
                <div className="grid grid-cols-3 gap-3">
                    <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1">Nama motif</label>
                        <input
                            data-testid="variant-name"
                            type="text"
                            value={form.name}
                            onChange={(e) => setForm({ ...form, name: e.target.value })}
                            placeholder="Contoh: Parang"
                            className="w-full p-2 border rounded-md text-sm bg-white focus:ring-batik-gold"
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1">Harga (Rp)</label>
                        <input
                            data-testid="variant-price"
                            type="number"
                            value={form.price}
                            onChange={(e) => setForm({ ...form, price: e.target.value })}
                            placeholder="85000"
                            className="w-full p-2 border rounded-md text-sm bg-white focus:ring-batik-gold"
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1">Stok</label>
                        <input
                            data-testid="variant-stock"
                            type="number"
                            value={form.stock}
                            onChange={(e) => setForm({ ...form, stock: e.target.value })}
                            placeholder="10"
                            className="w-full p-2 border rounded-md text-sm bg-white focus:ring-batik-gold"
                        />
                    </div>
                </div>

                <div className="flex flex-col sm:flex-row sm:items-center gap-3">
                    {/* `relative` itu wajib: input file-nya absolute inset-0, jadi
                        tanpa positioned parent ia menutup SELURUH area form dan
                       mengganggu klik ke tombol lain (mis. Ubah/Hapus motif). */}
                    <label className="relative inline-flex items-center gap-2 text-xs text-gray-600 cursor-pointer">
                        <UploadCloud className="w-4 h-4 text-batik-400" />
                        <span className="underline decoration-dotted">
                            {image ? image.name : "Unggah foto motif (opsional)"}
                        </span>
                        <input
                            data-testid="variant-image"
                            type="file"
                            accept="image/*"
                            onChange={(e) => setImage(e.target.files?.[0] || null)}
                            className="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                        />
                    </label>

                    <div className="sm:ml-auto flex items-center gap-2">
                        {editingId && (
                            <Button type="button" variant="ghost" onClick={resetForm} className="text-xs text-gray-500">
                                <X className="w-3 h-3" /> Batal
                            </Button>
                        )}
                        <Button
                            data-testid="variant-save"
                            type="button"
                            onClick={submit}
                            disabled={busy}
                            className="bg-batik-900 hover:bg-batik-800 text-white text-xs px-4 py-2 gap-2"
                        >
                            <Save className="w-3 h-3" /> {editingId ? "Simpan Perubahan" : "Tambah Motif"}
                        </Button>
                    </div>
                </div>

                <p className="text-[11px] text-gray-500">
                    <Plus className="w-3 h-3 inline" /> Harga &amp; stok mengikuti motif ini, bukan produk —
                    itulah yang dipakai saat checkout.
                </p>
            </div>
        </div>
    );
}
