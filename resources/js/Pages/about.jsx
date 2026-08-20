import React from "react";
import WelcomeLayout from "@/Layouts/WelcomeLayout";
import { Head, Link } from "@inertiajs/react";
import { motion } from "framer-motion";
import { CheckCircle, Heart, Users, Sparkles, ArrowRight, PenTool } from "lucide-react";
import { Button } from "@/Components/ui/button";

export default function About() {
    // Animasi Varian
    const fadeInUp = {
        hidden: { opacity: 0, y: 30 },
        visible: { opacity: 1, y: 0, transition: { duration: 0.6 } }
    };

    const stagger = {
        hidden: { opacity: 0 },
        visible: { opacity: 1, transition: { staggerChildren: 0.2 } }
    };

    return (
        <WelcomeLayout>
            <Head title="Tentang Kami - BatikNesia" />

            {/* 1. HERO SECTION */}
            <section className="relative bg-batik-900 text-white py-24 lg:py-32 overflow-hidden">
                {/* Background Pattern */}
                <div className="absolute inset-0 opacity-[0.07] bg-kawung pointer-events-none"></div>
                <div className="absolute top-0 right-0 w-[500px] h-[500px] bg-batik-gold/10 rounded-full blur-[100px] pointer-events-none" />

                <div className="container mx-auto px-4 text-center relative z-10">
                    <motion.div initial="hidden" animate="visible" variants={fadeInUp}>
                        <span className="text-batik-gold font-bold tracking-[0.2em] uppercase text-sm mb-4 block">
                            Warisan & Dedikasi
                        </span>
                        <h1 className="font-serif text-4xl md:text-6xl font-bold mb-6">
                            Menenun Cerita,<br/>Melestarikan Budaya.
                        </h1>
                        <p className="text-batik-200 max-w-2xl mx-auto text-lg leading-relaxed">
                            Batik HM AKMAL lahir dari kecintaan mendalam terhadap wastra Nusantara. 
                            Kami percaya bahwa batik bukan sekadar kain, melainkan identitas yang hidup 
                            dan berkembang bersama zaman.
                        </p>
                    </motion.div>
                </div>
            </section>

            {/* 2. OUR STORY (Split Layout) */}
            <section className="py-20 bg-white">
                <div className="container mx-auto px-4 md:px-6">
                    <div className="grid lg:grid-cols-2 gap-12 items-center">
                        {/* Image Composition */}
                        <motion.div 
                            initial={{ opacity: 0, x: -50 }}
                            whileInView={{ opacity: 1, x: 0 }}
                            viewport={{ once: true }}
                            transition={{ duration: 0.8 }}
                            className="relative"
                        >
                            <div className="absolute -top-4 -left-4 w-24 h-24 bg-batik-gold/20 rounded-full blur-xl"></div>
                            <img 
                                src="https://images.unsplash.com/photo-1612413156637-23c233c7c223?q=80&w=2070&auto=format&fit=crop" 
                                alt="Pengrajin Batik" 
                                className="rounded-2xl shadow-xl relative z-10 aspect-[4/3] object-cover"
                            />
                            <div className="absolute -bottom-6 -right-6 bg-batik-50 p-6 rounded-xl border border-batik-100 shadow-lg z-20 max-w-xs hidden md:block">
                                <p className="font-serif italic text-batik-900 text-lg">"Setiap titik canting adalah doa yang dipanjatkan pengrajin."</p>
                            </div>
                        </motion.div>

                        {/* Text Content */}
                        <motion.div 
                            initial={{ opacity: 0, x: 50 }}
                            whileInView={{ opacity: 1, x: 0 }}
                            viewport={{ once: true }}
                            transition={{ duration: 0.8 }}
                            className="space-y-6"
                        >
                            <h2 className="text-3xl md:text-4xl font-serif font-bold text-batik-900">
                                Berawal dari Pekalongan, <br/>Untuk Dunia.
                            </h2>
                            <p className="text-gray-600 leading-relaxed">
                                Didirikan pada tahun 2023, BatikNesia bermula dari sebuah bengkel kecil di sudut kota Pekalongan. Kami melihat bahwa batik seringkali dianggap "kuno" atau hanya untuk acara formal.
                            </p>
                            <p className="text-gray-600 leading-relaxed">
                                Misi kami sederhana: <strong>Membawa batik ke dalam gaya hidup modern sehari-hari</strong> tanpa menghilangkan nilai filosofisnya. Kami berkolaborasi langsung dengan pengrajin lokal untuk menciptakan motif yang relevan, potongan yang nyaman, dan kualitas yang tak lekang oleh waktu.
                            </p>
                            
                            <div className="grid grid-cols-2 gap-4 pt-4">
                                <div className="flex items-center gap-3">
                                    <div className="w-10 h-10 rounded-full bg-batik-50 flex items-center justify-center text-batik-gold">
                                        <Users className="w-5 h-5" />
                                    </div>
                                    <div>
                                        <h4 className="font-bold text-batik-900">50+</h4>
                                        <p className="text-xs text-gray-500">Pengrajin Lokal</p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-3">
                                    <div className="w-10 h-10 rounded-full bg-batik-50 flex items-center justify-center text-batik-gold">
                                        <Sparkles className="w-5 h-5" />
                                    </div>
                                    <div>
                                        <h4 className="font-bold text-batik-900">100%</h4>
                                        <p className="text-xs text-gray-500">Karya Autentik</p>
                                    </div>
                                </div>
                            </div>
                        </motion.div>
                    </div>
                </div>
            </section>

            {/* 3. OUR VALUES */}
            <section className="py-20 bg-[#faf9f6]">
                <div className="container mx-auto px-4 md:px-6">
                    <div className="text-center max-w-2xl mx-auto mb-16">
                        <span className="text-batik-gold font-bold tracking-widest uppercase text-xs">Nilai Kami</span>
                        <h2 className="text-3xl font-serif font-bold text-batik-900 mt-2">Mengapa HM AKMAL?</h2>
                    </div>

                    <motion.div 
                        variants={stagger}
                        initial="hidden"
                        whileInView="visible"
                        viewport={{ once: true }}
                        className="grid md:grid-cols-3 gap-8"
                    >
                        <ValueCard 
                            icon={<PenTool className="w-8 h-8" />}
                            title="Buatan Tangan"
                            desc="Kami mengutamakan batik tulis dan cap yang dibuat secara manual, menjaga keaslian proses tradisional."
                        />
                        <ValueCard 
                            icon={<Heart className="w-8 h-8" />}
                            title="Fair Trade"
                            desc="Kami memastikan kesejahteraan pengrajin dengan sistem upah yang adil dan transparan."
                        />
                        <ValueCard 
                            icon={<CheckCircle className="w-8 h-8" />}
                            title="Kualitas Premium"
                            desc="Menggunakan bahan katun primisima dan sutra terbaik yang nyaman dipakai di iklim tropis."
                        />
                    </motion.div>
                </div>
            </section>

            {/* 4. CTA (Call To Action) */}
            <section className="py-24 bg-batik-900 relative overflow-hidden">
                <div className="absolute inset-0 opacity-20 bg-[url('https://www.transparenttextures.com/patterns/batik.png')]"></div>
                <div className="container mx-auto px-4 text-center relative z-10">
                    <h2 className="text-3xl md:text-5xl font-serif font-bold text-white mb-6">
                        Mari Bangga Berbatik
                    </h2>
                    <p className="text-batik-200 mb-8 max-w-xl mx-auto text-lg">
                        Temukan koleksi yang sesuai dengan kepribadian Anda dan jadilah bagian dari pelestari budaya.
                    </p>
                    <div className="flex flex-col sm:flex-row gap-4 justify-center">
                        <Link href="/products">
                            <Button className="h-14 px-8 bg-batik-gold hover:bg-yellow-600 text-batik-900 font-bold rounded-full text-base w-full sm:w-auto">
                                Belanja Sekarang
                            </Button>
                        </Link>
                        <Link href="/">
                            <Button variant="outline" className="h-14 px-8 border-batik-gold text-batik-gold hover:bg-batik-gold hover:text-batik-900 rounded-full text-base w-full sm:w-auto bg-transparent">
                                Lihat Beranda
                            </Button>
                        </Link>
                    </div>
                </div>
            </section>
        </WelcomeLayout>
    );
}

// Component Kecil untuk Kartu Nilai
function ValueCard({ icon, title, desc }) {
    return (
        <motion.div 
            variants={{
                hidden: { opacity: 0, y: 20 },
                visible: { opacity: 1, y: 0 }
            }}
            className="bg-white p-8 rounded-2xl shadow-sm border border-batik-100 hover:shadow-lg transition-all duration-300 group"
        >
            <div className="w-16 h-16 bg-batik-50 rounded-2xl flex items-center justify-center text-batik-gold mb-6 group-hover:bg-batik-900 group-hover:text-white transition-colors duration-300">
                {icon}
            </div>
            <h3 className="text-xl font-bold text-batik-900 mb-3 font-serif">{title}</h3>
            <p className="text-gray-500 leading-relaxed text-sm">
                {desc}
            </p>
        </motion.div>
    );
}