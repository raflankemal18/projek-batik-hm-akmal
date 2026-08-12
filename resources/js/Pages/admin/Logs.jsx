import React, { useState } from "react";
import { router } from "@inertiajs/react";
import AdminLayout from "@/Components/component/admin-page-layout";
import Pagination from "@/Components/Pagination";
import { Badge } from "@/Components/ui/badge";
import { Search, ChevronDown, ChevronUp } from "lucide-react";

const TYPE_TABS = [
    { value: "", label: "Semua" },
    { value: "user", label: "User" },
    { value: "admin", label: "Admin" },
    { value: "transaction", label: "Transaksi" },
];

const TYPE_BADGE_CLASS = {
    user: "bg-blue-50 text-blue-700 border-blue-200",
    admin: "bg-purple-50 text-purple-700 border-purple-200",
    transaction: "bg-green-50 text-green-700 border-green-200",
};

export default function Logs({ logs, filters }) {
    const list = logs?.data || [];

    const [type, setType] = useState(filters?.type || "");
    const [search, setSearch] = useState(filters?.search || "");
    const [dateFrom, setDateFrom] = useState(filters?.date_from || "");
    const [dateTo, setDateTo] = useState(filters?.date_to || "");
    const [expandedId, setExpandedId] = useState(null);

    const reload = (overrides = {}) => {
        router.get("/admin/logs", {
            type, search, date_from: dateFrom, date_to: dateTo,
            ...overrides,
        }, {
            preserveState: true,
            preserveScroll: true,
            only: ["logs", "filters"],
        });
    };

    const handleTypeChange = (value) => {
        setType(value);
        reload({ type: value });
    };

    const handleFilterSubmit = (e) => {
        e.preventDefault();
        reload();
    };

    return (
        <AdminLayout>
            <div className="space-y-6">
                <div className="flex justify-between items-start flex-col sm:flex-row gap-4">
                    <div>
                        <h1 className="text-3xl font-serif font-bold text-gray-900">Log Aktivitas</h1>
                        <p className="text-gray-500">Riwayat aktivitas user, admin, dan transaksi — append-only.</p>
                    </div>
                    <div className="flex gap-2">
                        <a 
                            href={`/admin/logs/export?format=csv&type=${type}&search=${search}&date_from=${dateFrom}&date_to=${dateTo}`}
                            className="px-4 py-2 bg-white border border-gray-200 text-gray-700 rounded-lg hover:bg-gray-50 text-sm font-medium transition-colors"
                        >
                            Export CSV
                        </a>
                        <a 
                            href={`/admin/logs/export?format=pdf&type=${type}&search=${search}&date_from=${dateFrom}&date_to=${dateTo}`}
                            className="px-4 py-2 bg-red-50 text-red-700 border border-red-200 rounded-lg hover:bg-red-100 text-sm font-medium transition-colors"
                        >
                            Export PDF
                        </a>
                    </div>
                </div>

                <div className="bg-white rounded-xl shadow-sm border border-batik-100 overflow-hidden">
                    {/* Tabs */}
                    <div className="flex overflow-x-auto border-b border-gray-100 bg-gray-50/50 px-4">
                        {TYPE_TABS.map((tab) => (
                            <button
                                key={tab.value}
                                onClick={() => handleTypeChange(tab.value)}
                                className={`px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors ${
                                    type === tab.value
                                        ? "border-batik-gold text-batik-900"
                                        : "border-transparent text-gray-500 hover:text-batik-900"
                                }`}
                            >
                                {tab.label}
                            </button>
                        ))}
                    </div>

                    {/* Filter form */}
                    <form onSubmit={handleFilterSubmit} className="flex flex-col sm:flex-row gap-3 p-4 border-b border-gray-100">
                        <div className="relative flex-1 max-w-sm">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                            <input
                                type="search"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Cari action / deskripsi / email..."
                                className="w-full bg-gray-50 pl-9 pr-4 py-2 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-1 focus:ring-batik-gold"
                            />
                        </div>
                        <input
                            type="date"
                            value={dateFrom}
                            onChange={(e) => setDateFrom(e.target.value)}
                            className="bg-gray-50 px-4 py-2 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-1 focus:ring-batik-gold"
                        />
                        <input
                            type="date"
                            value={dateTo}
                            onChange={(e) => setDateTo(e.target.value)}
                            className="bg-gray-50 px-4 py-2 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-1 focus:ring-batik-gold"
                        />
                        <button
                            type="submit"
                            className="px-5 py-2 rounded-lg bg-batik-900 text-white text-sm font-medium hover:bg-batik-800 transition-colors"
                        >
                            Filter
                        </button>
                    </form>

                    <div className="overflow-x-auto">
                        <table className="w-full text-sm text-left text-gray-500">
                            <thead className="text-xs text-gray-700 uppercase bg-gray-50 border-b">
                                <tr>
                                    <th className="px-6 py-4">Waktu</th>
                                    <th className="px-6 py-4">Tipe</th>
                                    <th className="px-6 py-4">Actor</th>
                                    <th className="px-6 py-4">Action</th>
                                    <th className="px-6 py-4">Subject</th>
                                    <th className="px-6 py-4">Deskripsi</th>
                                    <th className="px-6 py-4"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {list.length === 0 ? (
                                    <tr>
                                        <td colSpan="7" className="px-6 py-8 text-center text-gray-500">
                                            Belum ada log aktivitas.
                                        </td>
                                    </tr>
                                ) : list.map((log) => (
                                    <React.Fragment key={log.id}>
                                        <tr className="hover:bg-gray-50 transition-colors align-top">
                                            <td className="px-6 py-4 whitespace-nowrap text-xs text-gray-500">
                                                {new Date(log.created_at).toLocaleString('id-ID')}
                                            </td>
                                            <td className="px-6 py-4">
                                                <Badge className={TYPE_BADGE_CLASS[log.type] || ""} variant="outline">
                                                    {log.type}
                                                </Badge>
                                            </td>
                                            <td className="px-6 py-4 text-xs">
                                                {log.email || <span className="text-gray-400 italic">sistem</span>}
                                            </td>
                                            <td className="px-6 py-4 font-mono text-xs font-bold text-batik-900">
                                                {log.action}
                                            </td>
                                            <td className="px-6 py-4 text-xs text-gray-500">
                                                {log.subject_type ? `${log.subject_type.split('\\').pop()} #${log.subject_id}` : '-'}
                                            </td>
                                            <td className="px-6 py-4 text-xs max-w-xs">
                                                {log.description || '-'}
                                            </td>
                                            <td className="px-6 py-4">
                                                {log.context && (
                                                    <button
                                                        onClick={() => setExpandedId(expandedId === log.id ? null : log.id)}
                                                        className="text-gray-400 hover:text-batik-900"
                                                    >
                                                        {expandedId === log.id ? <ChevronUp className="w-4 h-4" /> : <ChevronDown className="w-4 h-4" />}
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                        {expandedId === log.id && (
                                            <tr>
                                                <td colSpan="7" className="px-6 py-4 bg-gray-50">
                                                    <pre className="text-xs bg-white border border-gray-200 rounded-lg p-4 overflow-x-auto">
                                                        {JSON.stringify(log.context, null, 2)}
                                                    </pre>
                                                </td>
                                            </tr>
                                        )}
                                    </React.Fragment>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="p-4 border-t border-gray-100">
                        <Pagination links={logs?.links} />
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
