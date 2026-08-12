import React, { useState, useEffect } from "react";
import ProductCards from "@/Components/ProductCards";
import { router } from "@inertiajs/react";
import { Search, SlidersHorizontal, ArrowUpDown } from "lucide-react";

export default function ProductCollection({ products, activeCategory = "all" }) {
    const [filteredProducts, setFilteredProducts] = useState(products);
    const [searchQuery, setSearchQuery] = useState("");
    const [sortBy, setSortBy] = useState("newest");
    const [selectedCategory, setSelectedCategory] = useState(activeCategory);

    // Dummy Categories (Bisa disesuaikan dengan data real nanti)
    const categories = [
        { id: "all", name: "Semua Koleksi" },
        { id: "pria", name: "Batik Pria" },
        { id: "wanita", name: "Batik Wanita" },
        { id: "sarimbit", name: "Sarimbit (Couple)" },
    ];

    // Logic Filtering & Sorting (search & sort client-side; kategori dari server)
    useEffect(() => {
        let result = [...products];

        // 1. Filter by Search
        if (searchQuery) {
            result = result.filter((p) =>
                p.name.toLowerCase().includes(searchQuery.toLowerCase())
            );
        }

        // 2. Sorting
        switch (sortBy) {
            case "price_low":
                result.sort((a, b) => parseFloat(a.harga) - parseFloat(b.harga));
                break;
            case "price_high":
                result.sort((a, b) => parseFloat(b.harga) - parseFloat(a.harga));
                break;
            case "name_asc":
                result.sort((a, b) => a.name.localeCompare(b.name));
                break;
            default: // newest (default ID)
                result.sort((a, b) => b.id - a.id);
        }

        setFilteredProducts(result);
    }, [products, searchQuery, sortBy]);

    // Ganti kategori -> request ulang ke server (langsung memfilter produk)
    const handleCategoryChange = (id) => {
        setSelectedCategory(id);
        router.get("/products", { category: id }, {
            preserveState: true,
            preserveScroll: true,
            only: ["products", "activeCategory"],
        });
    };

    // Sinkronkan tab aktif jika navigasi datang dari luar (mis. link /products)
    useEffect(() => {
        setSelectedCategory(activeCategory);
    }, [activeCategory]);

    return (
        <section className="min-h-screen bg-[#faf9f6]">
            {/* Header Title */}
            <div className="bg-white border-b border-batik-100">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 text-center">
                    <span className="text-batik-gold font-bold tracking-[0.2em] text-xs uppercase mb-2 block">
                        Katalog Eksklusif
                    </span>
                    <h1 className="font-serif text-3xl md:text-4xl lg:text-5xl text-batik-900 mb-4">
                        Koleksi Batik Nusantara
                    </h1>
                    <p className="text-gray-500 max-w-2xl mx-auto font-light">
                        Temukan motif yang mencerminkan karakter Anda. Dari klasik hingga kontemporer.
                    </p>
                </div>
            </div>

            {/* Filter & Toolbar Sticky */}
            <div className="sticky top-20 z-30 bg-white/80 backdrop-blur-md border-b border-batik-100 shadow-sm">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                    <div className="flex flex-col md:flex-row gap-4 justify-between items-center">
                        
                        {/* Kiri: Tab Kategori */}
                        <div className="flex overflow-x-auto gap-2 w-full md:w-auto pb-2 md:pb-0 no-scrollbar">
                            {categories.map((cat) => (
                                <button
                                    key={cat.id}
                                    onClick={() => handleCategoryChange(cat.id)}
                                    className={`px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap transition-all ${
                                        selectedCategory === cat.id
                                            ? "bg-batik-900 text-white border border-batik-900"
                                            : "bg-transparent text-gray-600 border border-gray-200 hover:border-batik-gold hover:text-batik-gold"
                                    }`}
                                >
                                    {cat.name}
                                </button>
                            ))}
                        </div>

                        {/* Kanan: Search & Sort */}
                        <div className="flex gap-3 w-full md:w-auto">
                            {/* Search Input */}
                            <div className="relative flex-1 md:w-64">
                                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                <input
                                    type="text"
                                    placeholder="Cari motif..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-200 bg-gray-50 focus:bg-white focus:border-batik-gold focus:ring-1 focus:ring-batik-gold outline-none text-sm transition-all"
                                />
                            </div>

                            {/* Sort Dropdown */}
                            <div className="relative">
                                <select
                                    value={sortBy}
                                    onChange={(e) => setSortBy(e.target.value)}
                                    className="appearance-none pl-4 pr-10 py-2 rounded-lg border border-gray-200 bg-white text-sm focus:border-batik-gold focus:ring-1 focus:ring-batik-gold outline-none cursor-pointer"
                                >
                                    <option value="newest">Terbaru</option>
                                    <option value="price_low">Harga Terendah</option>
                                    <option value="price_high">Harga Tertinggi</option>
                                    <option value="name_asc">Nama (A-Z)</option>
                                </select>
                                <ArrowUpDown className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Product Grid Area */}
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                {filteredProducts.length > 0 ? (
                    <div className="animate-in fade-in slide-in-from-bottom-4 duration-700">
                        <ProductCards products={filteredProducts} />
                    </div>
                ) : (
                    // Empty State jika pencarian tidak ditemukan
                    <div className="text-center py-20">
                        <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <Search className="w-8 h-8 text-gray-400" />
                        </div>
                        <h3 className="text-lg font-serif font-medium text-gray-900">Produk tidak ditemukan</h3>
                        <p className="text-gray-500 text-sm mt-1">Coba gunakan kata kunci lain atau ubah filter.</p>
                        <button 
                            onClick={() => {setSearchQuery(""); setSelectedCategory("all")}}
                            className="mt-4 text-batik-gold hover:underline text-sm font-medium"
                        >
                            Reset Filter
                        </button>
                    </div>
                )}
            </div>
        </section>
    );
}