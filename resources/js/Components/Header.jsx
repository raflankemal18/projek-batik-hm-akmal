import { Link, usePage, router } from "@inertiajs/react";
import React, { useState } from "react";
import Dropdown from "@/Components/Dropdown";
import { ShoppingBag, Menu, X, LayoutDashboard, Heart, UserCircle, Search } from "lucide-react";

export default function Header() {
    const { auth } = usePage().props;
    
    // Cek Role untuk Conditional Rendering
    const isAdmin = auth.user?.role === 'admin';
const isUser = auth.user?.role === 'user';

    const [isClick, setisClick] = useState(false);
    const toggleNavbar = () => setisClick(!isClick);

    const [searchQuery, setSearchQuery] = useState("");
    const handleSearch = (e) => {
        e.preventDefault();
        if (searchQuery.trim()) {
            router.get("/search", { q: searchQuery });
        }
    };

    // --- LOGOUT MELALUI SESSION (COOKIE) ---
    const handleLogout = (e) => {
        e.preventDefault();
        
        router.post(route("logout"), {}, {
            onFinish: () => {
                window.location.reload(); 
            }
        });
    };

    return (
        <header className="bg-batik-900 border-b border-white/10 sticky top-0 z-50 backdrop-blur-md bg-opacity-95">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="flex items-center justify-between h-20">
                    
                    {/* LOGO */}
                    <div className="flex-shrink-0">
                        <Link href="/" className="flex items-center gap-2 group">
                            {/* Logo Icon Huruf 'H' atau 'HA' */}
                            <div className="w-10 h-10 bg-batik-gold rounded-full flex items-center justify-center text-batik-900 font-bold font-serif text-xl group-hover:bg-white transition-colors">
                                H
                            </div>
                            {/* Teks Logo */}
                            <span className="font-serif text-2xl font-bold text-batik-50 tracking-wide">
                                HM <span className="text-batik-gold">Akmal</span>
                            </span>
                        </Link>
                    </div>

                    {/* DESKTOP MENU */}
                    <div className="hidden md:block">
                        <div className="ml-10 flex items-baseline space-x-8">
                            <NavLink href="/" active={route().current('home')}>Beranda</NavLink>
                            <NavLink href="/products" active={route().current('products')}>Koleksi</NavLink>
                            <NavLink href="/about" active={route().current('about')}>Tentang Kami</NavLink>
                        </div>
                    </div>

                    {/* ICONS & AUTH */}
                    <div className="hidden md:flex items-center gap-6">
                        {/* SEARCH DESKTOP */}
                        <form onSubmit={handleSearch} className="relative flex items-center">
                            <input 
                                type="text" 
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                placeholder="Cari motif..." 
                                className="bg-white/10 text-batik-50 placeholder-batik-200 border border-white/20 rounded-full px-4 py-1.5 focus:outline-none focus:ring-1 focus:ring-batik-gold text-sm w-48 transition-all"
                            />
                            <button type="submit" className="absolute right-3 text-batik-100 hover:text-batik-gold">
                                <Search className="w-4 h-4" />
                            </button>
                        </form>
                        
                        {/* ICON KERANJANG (Hanya muncul jika BUKAN Admin) */}
                        {!isAdmin && (
                            <>
                                <Link href="/wishlist" className="text-batik-100 hover:text-batik-gold transition-colors relative">
                                    <Heart className="w-6 h-6" />
                                    {usePage().props.wishlistIds?.length > 0 && (
                                        <span className="absolute -top-1 -right-2 bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">
                                            {usePage().props.wishlistIds.length}
                                        </span>
                                    )}
                                </Link>
                                <Link href="/cart" className="text-batik-100 hover:text-batik-gold transition-colors relative">
                                    <ShoppingBag className="w-6 h-6" />
                                    {usePage().props.cartCount > 0 && (
                                        <span className="absolute -top-1 -right-2 bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">
                                            {usePage().props.cartCount}
                                        </span>
                                    )}
                                </Link>
                            </>
                        )}

                        {/* TOMBOL DASHBOARD (Hanya muncul jika ADMIN) */}
                        {isAdmin && (
                            <a href={route('admin.dashboard')} className="flex items-center gap-2 text-batik-gold font-bold border border-batik-gold px-4 py-2 rounded-full hover:bg-batik-gold hover:text-batik-900 transition-all text-xs uppercase tracking-wider">
                                <LayoutDashboard className="w-4 h-4" />
                                Dashboard
                            </a>
                        )}

                        {!auth.user ? (
                            <Link href={route("login")} className="px-6 py-2 rounded-full border border-batik-gold text-batik-gold hover:bg-batik-gold hover:text-batik-900 transition-all font-medium text-sm tracking-wide uppercase">
                                Masuk
                            </Link>
                        ) : (
                            <div className="relative">
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <button className="flex items-center gap-2 hover:bg-white/5 px-2 py-1.5 rounded-full transition-colors group">
                                            <div className="w-8 h-8 rounded-full bg-batik-gold text-batik-900 flex items-center justify-center font-bold text-sm">
                                                {auth.user.name.charAt(0).toUpperCase()}
                                            </div>
                                            <span className="text-sm font-medium text-batik-50 group-hover:text-batik-gold transition-colors hidden lg:block">
                                                {auth.user.name}
                                            </span>
                                        </button>
                                    </Dropdown.Trigger>
                                    <Dropdown.Content align="right">
                                        <Dropdown.Link href={route("profile.edit")}>Profil Saya</Dropdown.Link>
                                        <button
                                            onClick={handleLogout}
                                            className="block w-full px-4 py-2 text-start text-sm leading-5 text-red-600 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out"
                                        >
                                            Keluar
                                        </button>
                                    </Dropdown.Content>
                                </Dropdown>
                            </div>
                        )}
                    </div>

                    {/* MOBILE MENU BUTTON */}
                    <div className="md:hidden flex items-center">
                        <button onClick={toggleNavbar} className="text-batik-100 hover:text-batik-gold transition-colors">
                            {isClick ? <X className="w-8 h-8" /> : <Menu className="w-8 h-8" />}
                        </button>
                    </div>
                </div>
            </div>

            {/* MOBILE MENU DROPDOWN */}
            {isClick && (
                <div className="md:hidden bg-batik-900 border-t border-white/10 animate-accordion-down">
                    <div className="px-4 pt-2 pb-6 space-y-2">
                        {/* SEARCH MOBILE */}
                        <form onSubmit={handleSearch} className="py-2">
                            <div className="relative flex items-center">
                                <input 
                                    type="text" 
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    placeholder="Cari motif..." 
                                    className="w-full bg-white/10 text-batik-50 placeholder-batik-200 border border-white/20 rounded-md px-4 py-2 focus:outline-none focus:ring-1 focus:ring-batik-gold text-sm"
                                />
                                <button type="submit" className="absolute right-3 text-batik-100 hover:text-batik-gold">
                                    <Search className="w-5 h-5" />
                                </button>
                            </div>
                        </form>
                        <MobileNavLink href="/">Beranda</MobileNavLink>
                        <MobileNavLink href="/products">Koleksi</MobileNavLink>
                        {!isAdmin && (
                            <>
                                <MobileNavLink href="/wishlist">Wishlist</MobileNavLink>
                                <MobileNavLink href="/cart">Keranjang</MobileNavLink>
                            </>
                        )}

                        {isAdmin && (
                             <a href={route('admin.dashboard')} className="text-batik-gold hover:bg-white/5 block px-3 py-3 rounded-md text-base font-medium flex items-center gap-2">
                                <LayoutDashboard className="w-4 h-4" /> Dashboard Admin
                            </a>
                        )}
                        
                        <div className="pt-4 mt-4 border-t border-white/10">
                            {!auth.user ? (
                                <MobileNavLink href={route("login")}>Masuk / Daftar</MobileNavLink>
                            ) : (
                                <>
                                    <MobileNavLink href={route("profile.edit")}>Profil Saya</MobileNavLink>
                                    <button 
                                        onClick={handleLogout} 
                                        className="block w-full text-left px-3 py-3 text-red-400 font-medium hover:bg-white/5 rounded-md"
                                    >
                                        Keluar
                                    </button>
                                </>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </header>
    );
}

// Komponen Helper
const NavLink = ({ href, active, children }) => (
    <Link 
        href={href} 
        className={`${active ? 'text-batik-gold' : 'text-batik-100'} hover:text-batik-gold px-3 py-2 text-sm font-medium uppercase tracking-widest transition-colors duration-300`}
    >
        {children}
    </Link>
);

const MobileNavLink = ({ href, children }) => (
    <Link href={href} className="text-batik-100 hover:text-batik-gold hover:bg-white/5 block px-3 py-3 rounded-md text-base font-medium">
        {children}
    </Link>
);