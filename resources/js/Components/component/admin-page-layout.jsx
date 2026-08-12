import React, { useState, useEffect } from "react";
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
    Image as ImageIcon,
    LayoutDashboard, 
    ShoppingBag, 
    Package, 
    Users, 
    Menu, 
    LogOut,
    Search,
    ScrollText,
    ChevronLeft,
    ChevronRight
} from "lucide-react";

export default function AdminLayout({ children }) {
    const { auth } = usePage().props;
    const [query, setQuery] = useState("");
    
    // Simpan state collapsed di localStorage agar konsisten
    const [isCollapsed, setIsCollapsed] = useState(() => {
        if (typeof window !== 'undefined') {
            return localStorage.getItem('adminSidebarCollapsed') === 'true';
        }
        return false;
    });

    useEffect(() => {
        localStorage.setItem('adminSidebarCollapsed', isCollapsed);
    }, [isCollapsed]);

    const handleSearch = (e) => {
        e.preventDefault();
        const q = query.trim();
        if (!q) return;
        router.get(route('admin.search'), { q }, { preserveState: false });
    };

    return (
        <div className={`grid min-h-screen w-full transition-all duration-300 bg-[#f8f9fa] ${isCollapsed ? 'lg:grid-cols-[80px_1fr]' : 'lg:grid-cols-[280px_1fr]'}`}>
            
            {/* SIDEBAR DESKTOP */}
            <div className="hidden border-r border-batik-800 bg-batik-900 lg:flex text-batik-100 sticky top-0 h-screen transition-all duration-300 z-40 relative flex-col">
                {/* Header Sidebar */}
                <div className={`flex h-[70px] items-center border-b border-white/10 shrink-0 transition-all duration-300 ${isCollapsed ? 'justify-center px-0' : 'px-6 justify-between'}`}>
                    <Link className={`flex items-center gap-2 font-serif font-bold tracking-wide text-white ${isCollapsed ? 'justify-center' : ''}`} href="/">
                        {isCollapsed ? (
                            <div className="w-10 h-10 bg-batik-gold rounded-full flex items-center justify-center text-batik-900 font-bold text-xl">H</div>
                        ) : (
                            <>
                                <span className="text-2xl">HM<span className="text-batik-gold">Akmal</span></span>
                                <span className="text-[10px] bg-batik-gold text-batik-900 px-1.5 py-0.5 rounded ml-2 font-sans tracking-normal">ADMIN</span>
                            </>
                        )}
                    </Link>
                </div>

                {/* Toggle Button Absolute */}
                <button 
                    type="button"
                    onClick={(e) => {
                        e.preventDefault();
                        setIsCollapsed((prev) => !prev);
                    }} 
                    className="absolute -right-3 top-20 z-50 flex h-6 w-6 items-center justify-center rounded-full bg-batik-gold text-batik-900 shadow-md hover:bg-yellow-400 focus:outline-none transition-transform hover:scale-110"
                    title={isCollapsed ? "Perlebar Sidebar" : "Perkecil Sidebar"}
                >
                    {isCollapsed ? <ChevronRight className="h-4 w-4 ml-0.5" /> : <ChevronLeft className="h-4 w-4 mr-0.5" />}
                </button>

                {/* Menu Navigasi */}
                <div className="flex-1 overflow-y-auto overflow-x-hidden py-4 px-3 flex flex-col gap-2">
                    <nav className="grid items-start gap-2 text-sm font-medium">
                        <AdminNavLink href="/admin/dashboard" active={route().current('admin.dashboard')} icon={<LayoutDashboard />} isCollapsed={isCollapsed}>
                            Dashboard Overview
                        </AdminNavLink>
                        <AdminNavLink 
                            href="/admin/orders" 
                            active={route().current('admin.orders')} 
                            icon={<ShoppingBag />}
                            badge={usePage().props.pendingOrdersCount}
                            isCollapsed={isCollapsed}
                        >
                            Pesanan Masuk
                        </AdminNavLink>
                        <AdminNavLink href="/admin/products" active={route().current('admin.products')} icon={<Package />} isCollapsed={isCollapsed}>
                            Manajemen Produk
                        </AdminNavLink>
                        <AdminNavLink href="/admin/users" active={route().current('admin.users')} icon={<Users />} isCollapsed={isCollapsed}>
                            Data Pelanggan
                        </AdminNavLink>
                        <AdminNavLink href="/admin/banners" active={route().current('admin.banners')} icon={<ImageIcon />} isCollapsed={isCollapsed}>
                            Manajemen Banner
                        </AdminNavLink>
                        <AdminNavLink href="/admin/logs" active={route().current('admin.logs')} icon={<ScrollText />} isCollapsed={isCollapsed}>
                            Log Aktivitas
                        </AdminNavLink>
                    </nav>
                </div>

                {/* Footer Sidebar */}
                <div className="p-3 border-t border-white/10 shrink-0">
                    <div className={`bg-white/5 rounded-lg flex items-center transition-all duration-300 ${isCollapsed ? 'justify-center p-2' : 'gap-3 p-3'}`}>
                        <div className="w-10 h-10 rounded-full bg-batik-gold flex items-center justify-center text-batik-900 font-bold shrink-0">
                            {auth.user.name.charAt(0).toUpperCase()}
                        </div>
                        {!isCollapsed && (
                            <div className="overflow-hidden">
                                <p className="truncate font-medium text-white text-sm">{auth.user.name}</p>
                                <p className="text-[11px] text-batik-400 truncate">{auth.user.email}</p>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* MAIN CONTENT AREA */}
            <div className="flex flex-col min-w-0">
                {/* Header Topbar */}
                <header className="flex h-[70px] items-center gap-4 border-b bg-white px-6 shadow-sm sticky top-0 z-30 shrink-0">
                    
                    {/* Mobile Toggle */}
                    <Sheet>
                        <SheetTrigger asChild>
                            <Button variant="ghost" size="icon" className="lg:hidden">
                                <Menu className="h-6 w-6" />
                                <span className="sr-only">Toggle menu</span>
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="left" className="bg-batik-900 text-white border-r-batik-800 p-0 flex flex-col h-full">
                            {/* Mobile Sidebar Content */}
                            <div className="flex flex-col h-full">
                                <div className="p-6 shrink-0 border-b border-white/10">
                                    <Link className="font-serif font-bold text-2xl text-white flex items-center gap-2" href="/">
                                        HM<span className="text-batik-gold">Akmal</span>
                                    </Link>
                                </div>
                                <div className="flex-1 overflow-y-auto p-4">
                                    <nav className="grid gap-2 text-lg font-medium">
                                        <AdminNavLink href="/admin/dashboard" icon={<LayoutDashboard />}>Dashboard</AdminNavLink>
                                        <AdminNavLink 
                                            href="/admin/orders" 
                                            icon={<ShoppingBag />}
                                            badge={usePage().props.pendingOrdersCount}
                                        >
                                            Pesanan
                                        </AdminNavLink>
                                        <AdminNavLink href="/admin/products" icon={<Package />}>Produk</AdminNavLink>
                                        <AdminNavLink href="/admin/users" icon={<Users />}>Pelanggan</AdminNavLink>
                                        <AdminNavLink href="/admin/banners" icon={<ImageIcon />}>Banner</AdminNavLink>
                                        <AdminNavLink href="/admin/logs" icon={<ScrollText />}>Log</AdminNavLink>
                                    </nav>
                                </div>
                            </div>
                        </SheetContent>
                    </Sheet>

                    {/* Search Bar */}
                    <div className="w-full flex-1 min-w-0">
                        <form onSubmit={handleSearch} className="relative w-full max-w-sm">
                            <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                            <input
                                type="search"
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder="Cari..."
                                className="w-full bg-gray-50 pl-9 pr-4 py-2 rounded-lg border border-gray-200 text-sm focus:outline-none focus:ring-1 focus:ring-batik-gold"
                            />
                        </form>
                    </div>

                    {/* Profile Dropdown */}
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="icon" className="rounded-full shrink-0">
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
                <main className="flex-1 p-4 md:p-6 lg:p-8 overflow-x-hidden">
                    {children}
                </main>
            </div>
        </div>
    );
}

// Component Link Helper agar rapi
function AdminNavLink({ href, active, icon, children, badge, isCollapsed = false }) {
    return (
        <Link
            href={href}
            className={`relative flex items-center rounded-lg transition-all ${
                isCollapsed ? 'justify-center p-3' : 'gap-3 px-3 py-2.5'
            } ${
                active 
                ? "bg-batik-gold text-batik-900 font-bold shadow-md" 
                : "text-batik-200 hover:bg-white/10 hover:text-white"
            }`}
            title={isCollapsed ? (typeof children === 'string' ? children : '') : ''}
        >
            {React.cloneElement(icon, { className: "h-5 w-5 shrink-0" })}
            {!isCollapsed && <span className="flex-1 truncate text-sm">{children}</span>}
            
            {/* Badge Indicator */}
            {badge > 0 && (
                isCollapsed ? (
                    <span className="absolute top-1 right-1 bg-red-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full shadow-sm ring-2 ring-batik-900">
                        {badge}
                    </span>
                ) : (
                    <span className="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full ml-auto shadow-sm">
                        {badge}
                    </span>
                )
            )}
        </Link>
    );
}