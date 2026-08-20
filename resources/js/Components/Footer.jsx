import { Link } from "@inertiajs/react";
import { Facebook, Instagram, Twitter, MapPin, Phone, Mail, ArrowRight } from "lucide-react";

export function Footer() {
    return (
        <footer className="relative bg-[#1a1512] text-batik-100 border-t-4 border-batik-gold pt-16 pb-8">
            {/* Pattern Overlay Halus agar tidak polos */}
            <div className="absolute inset-0 opacity-[0.05]" 
                style={{
                    backgroundImage: "radial-gradient(circle at 1px 1px, #d4af37 1px, transparent 0)",
                    backgroundSize: "20px 20px"
                }} 
            />

            <div className="container relative mx-auto px-4 md:px-6 z-10">
                <div className="grid grid-cols-1 md:grid-cols-4 gap-10 lg:gap-16 mb-12">
                    
                    {/* Brand Section */}
                    <div className="col-span-1 md:col-span-2 space-y-6">
                        <Link href="/" className="flex items-center gap-3 group">
                            <div className="w-12 h-12 bg-batik-gold rounded-full flex items-center justify-center text-batik-900 font-bold font-serif text-2xl group-hover:scale-105 transition-transform">
                                H
                            </div>
                            <span className="font-serif text-3xl font-bold text-white tracking-wide">
                                HM <span className="text-batik-gold">Akmal</span>
                            </span>
                        </Link>
                        <p className="text-batik-300 max-w-sm leading-relaxed text-sm md:text-base font-light">
                            Menghidupkan kembali warisan leluhur. Kami menghadirkan batik tulis dan cap dengan kualitas terbaik untuk gaya hidup modern Anda.
                        </p>
                        <div className="flex gap-4 pt-2">
                            <SocialIcon icon={<Instagram size={18}/>} />
                            <SocialIcon icon={<Facebook size={18}/>} />
                            <SocialIcon icon={<Twitter size={18}/>} />
                        </div>
                    </div>

                    {/* Quick Links */}
                    <div>
                        <h4 className="text-batik-gold font-bold uppercase tracking-[0.2em] text-xs mb-6 border-b border-white/10 pb-3 w-fit">Menu</h4>
                        <ul className="space-y-3 text-sm text-batik-200">
                            <FooterLink href="/" label="Beranda" />
                            <FooterLink href="/products" label="Koleksi Terbaru" />
                            <FooterLink href="/about" label="Tentang Kami" />
                            <FooterLink href="#" label="Konfirmasi Pembayaran" />
                        </ul>
                    </div>

                    {/* Contact Info */}
                    <div>
                        <h4 className="text-batik-gold font-bold uppercase tracking-[0.2em] text-xs mb-6 border-b border-white/10 pb-3 w-fit">Kontak</h4>
                        <ul className="space-y-4 text-sm text-batik-200">
                            <li className="flex items-start gap-3">
                                <MapPin className="w-5 h-5 text-batik-gold shrink-0 mt-0.5" />
                                <span className="leading-relaxed">Jl. Gatot Subroto No.628 ,<br/>51139 Pekalongan Jawa Tengah</span>
                            </li>
                            <li className="flex items-center gap-3">
                                <Phone className="w-5 h-5 text-batik-gold shrink-0" />
                                <span>+62 821-3818-9704</span>
                            </li>
                            <li className="flex items-center gap-3">
                                <Mail className="w-5 h-5 text-batik-gold shrink-0" />
                                <span>support@hmakmalbatik.com</span>
                            </li>
                        </ul>
                    </div>
                </div>

                {/* Copyright */}
                <div className="border-t border-white/10 pt-8 flex flex-col md:flex-row justify-between items-center text-xs text-batik-400 gap-4">
                    <p>&copy; 2025 HM AKMAL. Hak Cipta Dilindungi.</p>
                    <div className="flex gap-6">
                        <a href="#" className="hover:text-batik-gold transition-colors">Privacy Policy</a>
                        <a href="#" className="hover:text-batik-gold transition-colors">Terms of Service</a>
                    </div>
                </div>
            </div>
        </footer>
    );
}

const SocialIcon = ({ icon }) => (
    <a href="#" className="w-10 h-10 rounded-full bg-white/5 border border-white/10 flex items-center justify-center text-batik-gold hover:bg-batik-gold hover:text-batik-900 transition-all duration-300 transform hover:-translate-y-1">
        {icon}
    </a>
);

const FooterLink = ({ href, label }) => (
    <li>
        <Link href={href} className="group flex items-center gap-2 hover:text-white transition-all">
            <span className="w-0 group-hover:w-2 h-0.5 bg-batik-gold transition-all duration-300"></span>
            {label}
        </Link>
    </li>
);