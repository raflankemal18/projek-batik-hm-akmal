import React, { useState, useEffect } from "react";
import { router } from "@inertiajs/react";
import { X, Plus, Trash2, UploadCloud, Save } from "lucide-react";
import { Button } from "@/Components/ui/button";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/Components/ui/dialog";
import VariantManager from "../VariantManager";
import { toast } from "sonner";

export default function ProductModal({ isOpen, onClose, product = null }) {
    const [isLoading, setIsLoading] = useState(false);
    
    // Form State
    const [data, setData] = useState({
        name: "",
        harga: "",
        description: "",
        category: "",
        fabric_size: "",
        has_variants: false,
        sizes: [{ size: "S", stock: 10 }],
        images: []
    });

    // Reset atau Isi Data saat Modal Dibuka/Ganti Product
    useEffect(() => {
        if (product) {
            // Mode Edit: Isi form dengan data produk
            setData({
                name: product.name || "",
                harga: product.harga || "",
                description: product.description || "",
                category: product.category || "",
                fabric_size: product.fabric_size || "",
                has_variants: !!product.has_variants,
                sizes: product.sizes.length > 0 ? product.sizes : [{ size: "S", stock: 0 }],
                images: [] // Reset gambar baru
            });
        } else {
            // Mode Tambah: Kosongkan form
            setData({
                name: "",
                harga: "",
                description: "",
                category: "",
                fabric_size: "",
                has_variants: false,
                sizes: [{ size: "S", stock: 10 }],
                images: []
            });
        }
    }, [product, isOpen]);

    const handleChange = (e) => setData({ ...data, [e.target.id]: e.target.value });

    const handleSizeChange = (index, field, value) => {
        const newSizes = [...data.sizes];
        newSizes[index][field] = value;
        setData({ ...data, sizes: newSizes });
    };
    
    const addSizeRow = () => setData({ ...data, sizes: [...data.sizes, { size: "", stock: 0 }] });
    
    const removeSizeRow = (index) => {
        const newSizes = data.sizes.filter((_, i) => i !== index);
        setData({ ...data, sizes: newSizes });
    };

    const handleImageChange = (e) => {
        setData({ ...data, images: [...e.target.files] });
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        setIsLoading(true);

        const formData = new FormData();
        formData.append("name", data.name);
        formData.append("harga", data.harga);
        formData.append("description", data.description);
        formData.append("category", data.category);
        formData.append("fabric_size", data.fabric_size || "");
        // Penting: produk bermotif tidak butuh sizes[] (backend memverifikasi ulang),
        // dan has_variants harus dikirim sebagai "1"/"" supaya checkbox terkirim.
        formData.append("has_variants", data.has_variants ? "1" : "");
        
        // Stringify sizes array agar bisa dikirim lewat FormData
        data.sizes.forEach((s) => formData.append("sizes[]", JSON.stringify(s)));
        
        // Append Images
        Array.from(data.images).forEach((file, index) => {
            formData.append(`images[${index}]`, file);
        });

        if (product) {
            // --- LOGIKA UPDATE ---
            // Kita pakai post dengan _method: PUT untuk mengakali limitasi FormData file upload
            // atau langsung post ke route update khusus
            router.post(route('admin.products.update', product.id), formData, {
                onSuccess: () => {
                    onClose();
                    toast.success("Produk berhasil diperbarui!");
                },
                onError: () => toast.error("Gagal update produk."),
                onFinish: () => setIsLoading(false)
            });
        } else {
            // --- LOGIKA CREATE ---
            router.post(route('admin.products.store'), formData, {
                onSuccess: () => {
                    onClose();
                    toast.success("Produk berhasil ditambahkan!");
                },
                onError: () => toast.error("Gagal tambah produk."),
                onFinish: () => setIsLoading(false)
            });
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="font-serif text-2xl text-batik-900">
                        {product ? "Edit Produk" : "Tambah Produk Baru"}
                    </DialogTitle>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-6 mt-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div className="col-span-2 md:col-span-1">
                            <label className="block text-sm font-medium mb-1">Nama Produk</label>
                            <input data-testid="admin-product-name" type="text" id="name" value={data.name} onChange={handleChange} required className="w-full p-2 border rounded-md focus:ring-batik-gold" />
                        </div>
                        <div className="col-span-2 md:col-span-1">
                            <label className="block text-sm font-medium mb-1">Harga (Rp)</label>
                            <input data-testid="admin-product-price" type="number" id="harga" value={data.harga} onChange={handleChange} required className="w-full p-2 border rounded-md focus:ring-batik-gold" />
                        </div>
                        <div className="col-span-2">
                            <label className="block text-sm font-medium mb-1">Deskripsi</label>
                            <textarea data-testid="admin-product-description" id="description" value={data.description} onChange={handleChange} rows="3" className="w-full p-2 border rounded-md focus:ring-batik-gold" />
                        </div>
                        <div className="col-span-2">
                            <label className="block text-sm font-medium mb-1">Kategori</label>
                            <select data-testid="admin-product-category" id="category" value={data.category} onChange={handleChange} required className="w-full p-2 border rounded-md focus:ring-batik-gold bg-white">
                                <option value="">( — )</option>
                                <option value="pria">pria</option>
                                <option value="wanita">wanita</option>
                                <option value="sarimbit">sarimbit</option>
                            </select>
                        </div>
                        <div className="col-span-2 md:col-span-1">
                            <label className="block text-sm font-medium mb-1">Ukuran Kain</label>
                            <input
                                data-testid="admin-product-fabric-size"
                                type="text"
                                id="fabric_size"
                                value={data.fabric_size}
                                onChange={handleChange}
                                placeholder="Contoh: 2 m × 1,1 m"
                                className="w-full p-2 border rounded-md focus:ring-batik-gold"
                            />
                            <p className="text-[11px] text-gray-500 mt-1">Informasi produk, bukan pilihan pembeli.</p>
                        </div>
                        <div className="col-span-2 md:col-span-1 flex items-end">
                            <label className="flex items-center gap-2 text-sm text-gray-700 cursor-pointer select-none">
                                <input
                                    data-testid="admin-product-has-variants"
                                    type="checkbox"
                                    checked={data.has_variants}
                                    onChange={(e) => setData({ ...data, has_variants: e.target.checked })}
                                    className="rounded border-gray-300 text-batik-600"
                                />
                                <span>Produk memakai pilihan motif</span>
                            </label>
                        </div>
                    </div>

                    {/* Motif kain (hanya untuk produk bermotif) */}
                    {product ? (
                        <VariantManager
                            product={product}
                            onChanged={() => router.reload({ only: ['products'] })}
                        />
                    ) : (
                        <div className="bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-800">
                            Simpan produk dulu, baru tambahkan motifnya. Foto motif boleh menyusul —
                            cukup mengisi nama motif, harga, dan stok.
                        </div>
                    )}

                    {/* Varian Ukuran */}
                    {!data.has_variants && (
                    <div className="bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <label className="block text-sm font-bold mb-3 text-batik-900">Stok & Ukuran</label>
                        {data.sizes.map((size, index) => (
                            <div key={index} className="flex gap-3 mb-2 items-center">
                                <input data-testid="admin-product-size" type="text" placeholder="Size" value={size.size} onChange={(e) => handleSizeChange(index, "size", e.target.value)} className="w-1/3 p-2 border rounded text-sm uppercase" required />
                                <input data-testid="admin-product-stock" type="number" placeholder="Stok" value={size.stock} onChange={(e) => handleSizeChange(index, "stock", e.target.value)} className="w-1/3 p-2 border rounded text-sm" required />
                                <button type="button" onClick={() => removeSizeRow(index)} className="text-red-500 hover:bg-red-50 p-2 rounded"><Trash2 className="w-4 h-4" /></button>
                            </div>
                        ))}
                        <button type="button" onClick={addSizeRow} className="text-xs text-batik-gold font-bold flex items-center gap-1 mt-2 hover:underline">
                            <Plus className="w-3 h-3" /> Tambah Ukuran
                        </button>
                    </div>
                    )}

                    {/* Image Upload */}
                    <div className="border-2 border-dashed border-gray-300 p-6 rounded-lg text-center hover:bg-gray-50 transition cursor-pointer relative">
                        <input type="file" multiple accept="image/*" onChange={handleImageChange} className="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
                        <UploadCloud className="w-8 h-8 mx-auto text-gray-400 mb-2" />
                        <p className="text-sm text-gray-500">
                            {product ? "Upload gambar baru untuk menambah/mengganti" : "Upload foto produk"}
                        </p>
                        {data.images.length > 0 && (
                            <p className="text-xs text-green-600 font-bold mt-2">{data.images.length} file baru dipilih</p>
                        )}
                    </div>

                    <Button data-testid="admin-product-submit" type="submit" disabled={isLoading} className="w-full bg-batik-900 hover:bg-batik-800 text-white gap-2">
                        <Save className="w-4 h-4" /> {isLoading ? "Menyimpan..." : "Simpan Perubahan"}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}