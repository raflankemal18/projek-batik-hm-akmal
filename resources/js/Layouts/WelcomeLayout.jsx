import React, { useEffect } from "react";
import { usePage } from "@inertiajs/react";
import { Toaster } from "sonner";
import { motion, useScroll } from "framer-motion";
import { Footer } from "@/Components/Footer";
import Header from "@/Components/Header";

// Helper Function: Membaca Cookie Browser
// Fungsi ini mencari cookie dengan nama tertentu (misal: 'jwt_token')
const getCookie = (name) => {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
    return null;
};

export default function WelcomeLayout({ children, isFooterFixed = false }) {
    const { scrollYProgress } = useScroll();

    // Efek untuk Menangani Token JWT dari Cookie
    useEffect(() => {
        // 1. Coba ambil token dari Cookie (dikirim oleh Backend saat Login)
        const tokenFromCookie = getCookie('jwt_token');

        if (tokenFromCookie) {
            // 2. Jika ada, pindahkan ke LocalStorage (agar Axios bisa membacanya di setiap request)
            localStorage.setItem("auth_token", tokenFromCookie);
            console.log("✅ Token JWT Tersimpan (via Cookie).");
        } 
        // Catatan: Jika tidak ada di cookie, kita biarkan saja (mungkin user belum login atau token masih ada di localStorage lama)
    }, []);

    return (
        <>
            {/* Indikator Scroll Bar Emas di Atas */}
            <motion.div
                style={{ scaleX: scrollYProgress }}
                className="fixed top-0 left-0 z-[100] w-full bg-batik-gold h-1 origin-left"
            />
            
            {/* Komponen Notifikasi (Toast) */}
            <Toaster position="top-center" richColors />

            {/* Header / Navbar */}
            <Header /> 

            {/* Main Content dengan Background Texture Batik Halus */}
            <main className="min-h-screen bg-[#faf9f6] relative">
                {/* Layer Texture Titik-titik (Dot Pattern) */}
                <div className="absolute inset-0 z-0 opacity-[0.4] pointer-events-none" 
                     style={{
                         backgroundImage: "radial-gradient(#d4af37 0.5px, transparent 0.5px), radial-gradient(#d4af37 0.5px, #faf9f6 0.5px)",
                         backgroundSize: "20px 20px",
                         backgroundPosition: "0 0, 10px 10px"
                     }}>
                </div>
                
                {/* Konten Halaman */}
                <div className="relative z-10">
                    {children}
                </div>
            </main>

            {/* Footer */}
            <div className="h-16" /> {/* Spacer untuk Footer jika Fixed */}
            <Footer isFixed={isFooterFixed} />
        </>
    );
}