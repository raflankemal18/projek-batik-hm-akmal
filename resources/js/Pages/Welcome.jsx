import React, { useEffect } from "react";
import WelcomeLayout from "@/Layouts/WelcomeLayout";
import ProductCards from "@/Components/ProductCards";
import ModernHero from "@/Components/ModernHero";
import { motion, useAnimation } from "framer-motion";
import { useInView } from "react-intersection-observer";
import Review from "@/Components/pagewelcome/Review";

export default function Welcome({ products, banners }) {
    const sectionVariant = {
        visible: { opacity: 1, y: 0, transition: { duration: 0.8 } },
        hidden: { opacity: 0, y: 50 },
    };

    const AnimatedSection = ({ children, className = "" }) => {
        const controls = useAnimation();
        const [ref, inView] = useInView({ triggerOnce: true, threshold: 0.1 });

        useEffect(() => {
            if (inView) controls.start("visible");
        }, [controls, inView]);

        return (
            <motion.section
                ref={ref}
                variants={sectionVariant}
                initial="hidden"
                animate={controls}
                className={className}
            >
                {children}
            </motion.section>
        );
    };

    return (
        <WelcomeLayout>
            <ModernHero banners={banners} />

            {/* Section Produk: Latar belakang krem hangat khas kanvas batik */}
            <div className="bg-[#faf9f6] py-24"> 
                <div className="max-w-screen-xl mx-auto px-4 lg:px-8">
                    <AnimatedSection className="space-y-16">
                        {/* Header Klasik */}
                        <div className="text-center space-y-4">
                            <span className="text-batik-gold uppercase tracking-[0.2em] text-sm font-semibold">
                                Pilihan Kurator
                            </span>
                            <h2 className="font-serif text-4xl md:text-5xl text-gray-900">
                                Koleksi Mahakarya
                            </h2>
                            <div className="w-24 h-1 bg-batik-gold mx-auto" />
                        </div>

                        <ProductCards products={products} />
                        
                        <div className="text-center pt-8">
                            <a href="/products" className="inline-block border-b border-black pb-1 text-sm font-medium hover:text-batik-gold hover:border-batik-gold transition-colors uppercase tracking-widest">
                                Lihat Semua Koleksi
                            </a>
                        </div>
                    </AnimatedSection>
                </div>
            </div>

            {/* Section Review */}
            <div className="bg-white">
                <AnimatedSection>
                    <Review />
                </AnimatedSection>
            </div>
        </WelcomeLayout>
    );
}