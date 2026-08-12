import React, { useState } from "react";
import { ImageOff } from "lucide-react";
import { storageUrl } from "@/lib/image";

/**
 * <img> dengan fallback: menampilkan placeholder (ikon) bila path kosong
 * atau gambar gagal dimuat (404 / broken), sehingga tidak pernah tampil blank.
 */
export default function FallbackImage({ src, alt = "", className = "", ...props }) {
    const [error, setError] = useState(false);

    const resolved = src
        ? (src.startsWith("http") || src.startsWith("/")
            ? src
            : storageUrl(src))
        : null;

    if (!resolved || error) {
        return (
            <div
                role="img"
                aria-label={alt}
                className={`flex items-center justify-center bg-batik-50 ${className}`}
            >
                <ImageOff className="w-10 h-10 text-batik-200" />
            </div>
        );
    }

    return (
        <img
            src={resolved}
            alt={alt}
            className={className}
            loading="lazy"
            onError={() => setError(true)}
            {...props}
        />
    );
}
