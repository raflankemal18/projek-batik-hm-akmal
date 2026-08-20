import { Link, usePage, router } from "@inertiajs/react";
import React, { useState } from "react";
import { ShoppingBag, Menu, X, LayoutDashboard } from "lucide-react";

export default function Header() {
    const { auth } = usePage().props;
    
    // Cek Role untuk Conditional Rendering
    const isAdmin = auth.user?.role === 'admin';
const isUser = auth.user?.role === 'user';

    const [isClick, setisClick] = useState(false);
    const toggleNavbar = () => setisClick(!isClick);

    // --- LOGIKA LOGOUT DENGAN HAPUS TOKEN JWT ---
    const handleLogout = (e) => {
        e.preventDefault();
        
        router.post(route("logout"), {}, {
            onFinish: () => {
                // 1. Hapus Token dari Penyimpanan Browser
                localStorage.removeItem("auth_token");
                console.log("🔒 Logout: JWT Token dihapus.");                
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
                            <NavLink href="/about">Tentang Kami</NavLink>
                    {isUser && (
                            <NavLink href="/pembayaran" active={route().current('pembayaran')}>pesanan saya</NavLink>)}
                        </div>
                    </div>

                    {/* ICONS & AUTH */}
                    <div className="hidden md:flex items-center gap-6">
                        
                        {/* ICON KERANJANG (Hanya muncul jika BUKAN Admin) */}
                        {!isAdmin && (
                            <Link href="/cart" className="text-batik-100 hover:text-batik-gold transition-colors relative">
                                <ShoppingBag className="w-6 h-6" />
                            </Link>
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
                            <div className="flex items-center gap-4">
                                <span className="text-batik-200 text-sm font-serif italic">Halo, {auth.user.name}</span>
                                <button 
                                    onClick={handleLogout} 
                                    className="text-sm text-red-400 hover:text-red-300 transition-colors uppercase tracking-wider font-medium"
                                >
                                    Keluar
                                </button>
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
                        <MobileNavLink href="/">Beranda</MobileNavLink>
                        <MobileNavLink href="/products">Koleksi</MobileNavLink>
                        <MobileNavLink href="/pembayaran">pesanan saya</MobileNavLink>
                        
                        
                        {!isAdmin && (
                            <MobileNavLink href="/cart">Keranjang</MobileNavLink>
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
                                <button 
                                    onClick={handleLogout} 
                                    className="block w-full text-left px-3 py-2 text-red-400 font-medium hover:bg-white/5 rounded-md"
                                >
                                    KELUAR
                                </button>
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