import React from "react";
import { Toaster } from "sonner";
import { motion, useScroll } from "framer-motion";
import { Footer } from "@/Components/Footer";
import Header from "@/Components/Header";

export default function WelcomeLayout({ children, isFooterFixed = false }) {
    const { scrollYProgress } = useScroll();

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
            <div className="h-16" />
            <Footer isFixed={isFooterFixed} />
        </>
    );
}
