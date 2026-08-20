import defaultTheme from "tailwindcss/defaultTheme";
import forms from "@tailwindcss/forms";
const { nextui } = require("@nextui-org/theme");

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: "class",
    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/views/**/*.blade.php",
        "./resources/js/**/*.jsx",
        "./node_modules/@nextui-org/theme/dist/**/*.{js,ts,jsx,tsx}",
    ],

    theme: {
        container: {
            center: true,
            padding: "2rem",
            screens: {
                "2xl": "1400px",
            },
        },
        extend: {
            fontFamily: {
                // Font Utama (Figtree/Inter) untuk teks biasa
                sans: ["Figtree", ...defaultTheme.fontFamily.sans],
                // Font Mewah (Playfair Display) untuk Judul/Heading
                serif: ["Playfair Display", ...defaultTheme.fontFamily.serif],
            },
            colors: {
                // Palet Warna Khusus Tema Batik
                batik: {
                    50: "#fdf8f6",
                    100: "#f2e8e5",
                    200: "#eaddd7",
                    300: "#e0cec7",
                    400: "#d2bab0",
                    500: "#a0937d", // Warna Earth Tone
                    600: "#8a7e68",
                    700: "#635848", // Coklat Tua
                    800: "#4a4036", // Hampir Hitam
                    900: "#2d2620", // Warna Dasar Gelap (Charcoal)
                    gold: "#d4af37", // Aksen Emas Mewah
                },
                // Warna Standar Shadcn UI (Mapping ke warna netral jika variable CSS belum ada)
                border: "hsl(var(--border, 214.3 31.8% 91.4%))",
                input: "hsl(var(--input, 214.3 31.8% 91.4%))",
                ring: "hsl(var(--ring, 215 20.2% 65.1%))",
                background: "hsl(var(--background, 0 0% 100%))",
                foreground: "hsl(var(--foreground, 222.2 47.4% 11.2%))",
                primary: {
                    DEFAULT: "hsl(var(--primary, 222.2 47.4% 11.2%))",
                    foreground: "hsl(var(--primary-foreground, 210 40% 98%))",
                },
                secondary: {
                    DEFAULT: "hsl(var(--secondary, 210 40% 96.1%))",
                    foreground: "hsl(var(--secondary-foreground, 222.2 47.4% 11.2%))",
                },
                destructive: {
                    DEFAULT: "hsl(var(--destructive, 0 100% 50%))",
                    foreground: "hsl(var(--destructive-foreground, 210 40% 98%))",
                },
                muted: {
                    DEFAULT: "hsl(var(--muted, 210 40% 96.1%))",
                    foreground: "hsl(var(--muted-foreground, 215.4 16.3% 46.9%))",
                },
                accent: {
                    DEFAULT: "hsl(var(--accent, 210 40% 96.1%))",
                    foreground: "hsl(var(--accent-foreground, 222.2 47.4% 11.2%))",
                },
                popover: {
                    DEFAULT: "hsl(var(--popover, 0 0% 100%))",
                    foreground: "hsl(var(--popover-foreground, 222.2 47.4% 11.2%))",
                },
                card: {
                    DEFAULT: "hsl(var(--card, 0 0% 100%))",
                    foreground: "hsl(var(--card-foreground, 222.2 47.4% 11.2%))",
                },
            },
            // Pattern Batik Kawung menggunakan CSS Gradient
            backgroundImage: {
                kawung: "radial-gradient(circle at 0% 50%, transparent 9px, #2d2620 10px, #2d2620 15px, transparent 16px), radial-gradient(circle at 100% 50%, transparent 9px, #2d2620 10px, #2d2620 15px, transparent 16px)",
            },
            borderRadius: {
                lg: "var(--radius, 0.5rem)",
                md: "calc(var(--radius, 0.5rem) - 2px)",
                sm: "calc(var(--radius, 0.5rem) - 4px)",
            },
            keyframes: {
                "accordion-down": {
                    from: { height: "0" },
                    to: { height: "var(--radix-accordion-content-height)" },
                },
                "accordion-up": {
                    from: { height: "var(--radix-accordion-content-height)" },
                    to: { height: "0" },
                },
            },
            animation: {
                "accordion-down": "accordion-down 0.2s ease-out",
                "accordion-up": "accordion-up 0.2s ease-out",
            },
        },
    },
    plugins: [
        forms,
        nextui(),
        require("tailwindcss-animate"), // Plugin penting untuk animasi modern
    ],
};