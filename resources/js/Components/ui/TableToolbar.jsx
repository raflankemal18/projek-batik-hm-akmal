import React from "react";
import { Search, ArrowUpDown } from "lucide-react";

export default function TableToolbar({
    search,
    onSearchChange,
    sort,
    direction,
    onSortChange,
    sortOptions,
    searchPlaceholder = "Cari...",
}) {
    const sortValue = `${sort}:${direction}`;

    const handleSortChange = (e) => {
        const [s, d] = e.target.value.split(":");
        onSortChange(s, d);
    };

    return (
        <div className="flex flex-col sm:flex-row gap-3 p-4 border-b border-gray-100 bg-white">
            <div className="relative flex-1 max-w-sm">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                <input
                    type="search"
                    value={search}
                    onChange={(e) => onSearchChange(e.target.value)}
                    placeholder={searchPlaceholder}
                    className="w-full bg-gray-50 pl-9 pr-4 py-2 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-1 focus:ring-batik-gold"
                />
            </div>

            <div className="relative w-full sm:w-56">
                <ArrowUpDown className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                <select
                    value={sortValue}
                    onChange={handleSortChange}
                    className="w-full bg-gray-50 pl-9 pr-4 py-2 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-1 focus:ring-batik-gold appearance-none cursor-pointer"
                >
                    {sortOptions.map((opt) => (
                        <option key={opt.label} value={`${opt.sort}:${opt.direction}`}>
                            {opt.label}
                        </option>
                    ))}
                </select>
            </div>
        </div>
    );
}
