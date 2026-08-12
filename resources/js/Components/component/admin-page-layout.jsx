import React, { useState } from "react";
import { Link, usePage, router } from "@inertiajs/react";
import { Button } from "@/Components/ui/button";
import {
    DropdownMenu,
    DropdownMenuTrigger,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from "@/Components/ui/dropdown-menu";
import { Sheet, SheetTrigger, SheetContent } from "@/Components/ui/sheet";
import { 
    LayoutDashboard, 
    ShoppingBag, 
    Package, 
    Users, 
    Menu, 
    Bell, 
    LogOut,
    Search,
    ScrollText 
} from "lucide-react";

export default function AdminLayout({ children }) {
    const { auth } = usePage().props;
    const [query, setQuery] = useState("");

    const handleSearch = (e) => {
        e.preventDefault();
        const q = query.trim();
        if (!q) return;
        router.get(route('admin.search'), { q }, { preserveState: false });
    };

    return (
        <div className="grid min-h-screen w-full lg:grid-cols-[280px_1fr] bg-[#f8f9fa]">
            
            {/* SIDEBAR DESKTOP */}
            <div className="hidden border-r border-batik-800 bg-batik-900 lg:block text-batik-100">
                <div className="flex h-full max-h-screen flex-col gap-2">
                    {/* Header Sidebar */}
                    <div className="flex h-[70px] items-center px-6 border-b border-white/10">
                        <Link className="flex items-center gap-2 font-serif font-bold text-2xl tracking-wide text-white" href="/">
                            HM<span className="text-batik-gold">Akmal</span>
                            <span className="text-[10px] bg-batik-gold text-batik-900 px-1.5 py-0.5 rounded ml-2 font-sans tracking-normal">ADMIN</span>
                        </Link>
                    </div>

                    {/* Menu Navigasi */}
                    <div className="flex-1 overflow-auto py-6 px-4">
                        <nav className="grid items-start gap-2 text-sm font-medium">
                            <AdminNavLink href="/admin/dashboard" active={route().current('admin.dashboard')} icon={<LayoutDashboard />}>
                                Dashboard Overview
                            </AdminNavLink>
                            <AdminNavLink href="/admin/orders" active={route().current('admin.orders')} icon={<ShoppingBag />}>
                                Pesanan Masuk
                            </AdminNavLink>
                            <AdminNavLink href="/admin/products" active={route().current('admin.products')} icon={<Package />}>
                                Manajemen Produk
                            </AdminNavLink>
                            <AdminNavLink href="/admin/users" active={route().current('admin.users')} icon={<Users />}>
                                Data Pelanggan
                            </AdminNavLink>
                            <AdminNavLink href="/admin/logs" active={route().current('admin.logs')} icon={<ScrollText />}>
                                Log Aktivitas
                            </AdminNavLink>
                        </nav>
                    </div>

                    {/* Footer Sidebar */}
                    <div className="p-4 border-t border-white/10">
                        <div className="bg-white/5 rounded-lg p-4 flex items-center gap-3">
                            <div className="w-10 h-10 rounded-full bg-batik-gold flex items-center justify-center text-batik-900 font-bold">
                                {auth.user.name.charAt(0)}
                            </div>
                            <div className="overflow-hidden">
                                <p className="truncate font-medium text-white">{auth.user.name}</p>
                                <p className="text-xs text-batik-400 truncate">{auth.user.email}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* MAIN CONTENT AREA */}
            <div className="flex flex-col">
                {/* Header Topbar */}
                <header className="flex h-[70px] items-center gap-4 border-b bg-white px-6 shadow-sm sticky top-0 z-30">
                    
                    {/* Mobile Toggle */}
                    <Sheet>
                        <SheetTrigger asChild>
                            <Button variant="ghost" size="icon" className="lg:hidden">
                                <Menu className="h-6 w-6" />
                                <span className="sr-only">Toggle menu</span>
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="left" className="bg-batik-900 text-white border-r-batik-800 p-0">
                            {/* Mobile Sidebar Content (Sama dengan desktop tapi di sheet) */}
                            <div className="flex flex-col h-full py-6 px-4">
                                <Link className="font-serif font-bold text-2xl text-white mb-8 px-2" href="/">
                                    Batik<span className="text-batik-gold">Nesia</span>
                                </Link>
                                <nav className="grid gap-2 text-lg font-medium">
                                    <AdminNavLink href="/admin/dashboard" icon={<LayoutDashboard />}>Dashboard</AdminNavLink>
                                    <AdminNavLink href="/admin/orders" icon={<ShoppingBag />}>Pesanan</AdminNavLink>
                                    <AdminNavLink href="/admin/products" icon={<Package />}>Produk</AdminNavLink>
                                    <AdminNavLink href="/admin/users" icon={<Users />}>Pelanggan</AdminNavLink>
                                    <AdminNavLink href="/admin/logs" icon={<ScrollText />}>Log</AdminNavLink>
                                </nav>
                            </div>
                        </SheetContent>
                    </Sheet>

                    {/* Search Bar */}
                    <div className="w-full flex-1">
                        <form onSubmit={handleSearch} className="relative w-full max-w-sm">
                            <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                            <input
                                type="search"
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder="Cari produk, pelanggan, pesanan..."
                                className="w-full bg-gray-50 pl-9 pr-4 py-2 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-1 focus:ring-batik-gold"
                            />
                        </form>
                    </div>

                    {/* Profile Dropdown */}
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="icon" className="rounded-full">
                                <div className="w-8 h-8 rounded-full bg-batik-100 flex items-center justify-center text-batik-900 border border-batik-200">
                                    <Users className="w-4 h-4" />
                                </div>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-56">
                            <DropdownMenuLabel>Akun Administrator</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem>
                                <Link href="/" className="w-full">Lihat Toko (User View)</Link>
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem className="text-red-600 focus:text-red-600">
                                <Link href={route('logout')} method="post" as="button" className="w-full flex items-center gap-2">
                                    <LogOut className="w-4 h-4" /> Keluar
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </header>

                {/* Page Content */}
                <main className="flex-1 p-6 md:p-8 overflow-y-auto">
                    {children}
                </main>
            </div>
        </div>
    );
}

// Component Link Helper agar rapi
function AdminNavLink({ href, active, icon, children }) {
    return (
        <Link
            href={href}
            className={`flex items-center gap-3 rounded-lg px-3 py-2.5 transition-all text-sm ${
                active 
                ? "bg-batik-gold text-batik-900 font-bold shadow-md" 
                : "text-batik-200 hover:bg-white/10 hover:text-white"
            }`}
        >
            {React.cloneElement(icon, { className: "h-4 w-4" })}
            {children}
        </Link>
    );
}