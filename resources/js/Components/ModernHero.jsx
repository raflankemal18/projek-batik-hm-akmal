import React from "react";
import { motion } from "framer-motion";
import { Link } from "@inertiajs/react";
import { Button } from "@/Components/ui/button"; 
import { ArrowRight, Sparkles } from "lucide-react";

export default function ModernHero() {
    return (
        <section className="relative w-full overflow-hidden bg-[#1a1512] text-white pt-24 pb-16 lg:pt-40 lg:pb-32">
            {/* Pattern Batik Kawung (CSS Only) */}
            <div className="absolute inset-0 opacity-[0.07]" 
                style={{
                    backgroundImage: "radial-gradient(circle at 1px 1px, #d4af37 1px, transparent 0)",
                    backgroundSize: "40px 40px"
                }} 
            />
            
            {/* Dekorasi Emas (Gold Accents) */}
            <div className="absolute top-0 right-0 w-[600px] h-[600px] bg-batik-gold/10 rounded-full blur-[120px] pointer-events-none" />
            <div className="absolute bottom-0 left-0 w-[400px] h-[400px] bg-amber-700/20 rounded-full blur-[100px] pointer-events-none" />

            <div className="container relative mx-auto px-4 md:px-6 z-10">
                <div className="grid gap-12 lg:grid-cols-2 lg:gap-16 items-center">
                    {/* Text Content */}
                    <motion.div
                        initial={{ opacity: 0, y: 30 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.8, ease: "easeOut" }}
                        className="flex flex-col justify-center space-y-8"
                    >
                        <div className="space-y-4">
                            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full border border-batik-gold/30 bg-batik-gold/10 text-batik-gold text-xs tracking-widest uppercase font-medium w-fit">
                                <Sparkles className="w-3 h-3" />
                                Warisan Budaya Indonesia
                            </div>
                            
                            <h1 className="font-serif text-5xl md:text-6xl lg:text-7xl font-bold leading-tight">
                                Pesona Batik <br />
                                <span className="text-batik-gold italic">Modern Living.</span>
                            </h1>
                            
                            <p className="max-w-[600px] text-gray-400 text-lg md:text-xl font-light leading-relaxed">
                                Menghidupkan kembali seni leluhur dalam balutan desain kontemporer. Elegan untuk pesta, nyaman untuk bekerja.
                            </p>
                        </div>

                        <div className="flex flex-col sm:flex-row gap-4">
                            <Link href="/products">
                                <Button className="h-14 px-8 rounded-none bg-batik-gold text-black hover:bg-white hover:text-black font-semibold tracking-wide transition-all text-base w-full sm:w-auto">
                                    LIHAT KOLEKSI
                                </Button>
                            </Link>
                            <Link href="/about">
                                <Button variant="outline" className="h-14 px-8 rounded-none border-gray-600 text-white hover:bg-white hover:text-black hover:border-white transition-all text-base w-full sm:w-auto bg-transparent">
                                    TENTANG KAMI <ArrowRight className="ml-2 w-4 h-4" />
                                </Button>
                            </Link>
                        </div>
                    </motion.div>

                    {/* Image Visual (Frame Batik Style) */}
                    <motion.div
                        initial={{ opacity: 0, scale: 0.95 }}
                        animate={{ opacity: 1, scale: 1 }}
                        transition={{ duration: 1, delay: 0.2 }}
                        className="relative mx-auto lg:ml-auto w-full max-w-[500px]"
                    >
                        {/* Frame Border Emas */}
                        <div className="absolute -inset-4 border border-batik-gold/30 opacity-60 rounded-t-full rounded-b-none" />
                        
                        <div className="relative rounded-t-full rounded-b-lg overflow-hidden shadow-2xl aspect-[3/4]">
                            <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent z-10" />
                            <img
                                alt="Model Batik Modern"
                                className="object-cover w-full h-full hover:scale-105 transition-transform duration-700"
                                src="https://images.unsplash.com/photo-1617112837772-a0833a693207?q=80&w=2070&auto=format&fit=crop"
                            />
                            
                            {/* Floating Card */}
                            <div className="absolute bottom-8 left-8 right-8 z-20">
                                <div className="bg-white/10 backdrop-blur-md border border-white/20 p-6 text-white">
                                    <p className="font-serif text-2xl italic">"Batik Parang"</p>
                                    <p className="text-xs tracking-widest text-batik-gold mt-2 uppercase">Koleksi Eksklusif 2025</p>
                                </div>
                            </div>
                        </div>
                    </motion.div>
                </div>
            </div>
        </section>
    );
}