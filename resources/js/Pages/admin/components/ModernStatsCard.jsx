import React from "react";
import { Card, CardContent } from "@/Components/ui/card";
import { cn } from "@/lib/utils";
import { TrendingUp } from "lucide-react";

export default function ModernStatsCard({ title, value, icon, desc, trend = "up" }) {
    return (
        <Card className={cn(
            "relative overflow-hidden border-0 bg-batik-900 text-white transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl hover:shadow-batik-gold/20 group"
        )}>
            {/* Background Pattern Halus (Aksen Batik Modern) */}
            <div className="absolute inset-0 opacity-[0.03] mix-blend-overlay pointer-events-none"
                style={{
                    backgroundImage: "radial-gradient(circle at 2px 2px, rgba(212, 175, 55, 0.3) 1px, transparent 0)",
                    backgroundSize: "16px 16px"
                }}
            />
            
            {/* Efek Glow di sudut */}
            <div className="absolute -top-10 -right-10 w-32 h-32 bg-batik-gold/20 blur-[80px] rounded-full group-hover:bg-batik-gold/30 transition-all"></div>

            <CardContent className="p-6 relative z-10">
                <div className="flex justify-between items-start">
                    <div className="space-y-4">
                        {/* Title */}
                        <p className="text-sm font-medium uppercase tracking-widest text-batik-200/80">
                            {title}
                        </p>
                        
                        {/* Value (Nomor Besar) */}
                        <div className="flex items-baseline gap-2">
                            <h3 className="text-4xl md:text-5xl font-serif font-bold tracking-tight text-white group-hover:text-batik-gold transition-colors">
                                {value}
                            </h3>
                        </div>
                    </div>

                    {/* Icon Container Premium */}
                    <div className={cn(
                        "p-4 rounded-2xl bg-gradient-to-br from-white/10 to-white/5 border border-white/10 shadow-inner backdrop-blur-md group-hover:border-batik-gold/50 transition-all"
                    )}>
                        <div className="text-batik-gold drop-shadow-[0_0_8px_rgba(212,175,55,0.5)]">
                            {React.cloneElement(icon, { className: "h-8 w-8" })}
                        </div>
                    </div>
                </div>

                {/* Footer / Description */}
                <div className="mt-6 flex items-center gap-2 text-sm">
                    <div className={cn(
                        "flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold",
                        trend === "up" ? "bg-green-500/20 text-green-400" : "bg-red-500/20 text-red-400"
                    )}>
                        {trend === "up" ? <TrendingUp className="w-3 h-3" /> : null}
                        {/* Anda bisa menambahkan logika panah bawah jika trend down */}
                        {desc}
                    </div>
                    <span className="text-batik-400 text-xs">vs bulan lalu</span>
                </div>
            </CardContent>
        </Card>
    );
}