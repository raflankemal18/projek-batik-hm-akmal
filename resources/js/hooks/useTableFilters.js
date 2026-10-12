import { useState, useEffect, useRef } from "react";
import { router } from "@inertiajs/react";

export default function useTableFilters(initialSort = "created_at", initialDirection = "desc") {
    const [search, setSearch] = useState("");
    const [sort, setSort] = useState(initialSort);
    const [direction, setDirection] = useState(initialDirection);

    // Catatan: ref "sudah render pertama" harus DIPISAH per effect.
    // Kalau keduanya memakai satu ref yang sama, effect search (yang dideklarasikan
    // lebih dulu) men-set ref itu menjadi false, lalu effect sort/direction — yang
    // jalan setelahnya — langsung menganggap ini bukan render pertama dan
    // memanggil reload(). Karena Inertia me-remount komponen halaman pada visit,
    // ref ter-reset lagi → reload lagi → halaman admin (Produk/Pesanan/Pelanggan)
    // melakukan request reload tanpa henti sejak dibuka.
    const firstSearch = useRef(true);
    const firstSort = useRef(true);

    const reload = () => {
        router.reload({
            data: { search, sort, direction },
            preserveState: true,
            preserveScroll: true,
        });
    };

    useEffect(() => {
        if (firstSearch.current) {
            firstSearch.current = false;
            return;
        }
        const t = setTimeout(reload, 350);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    useEffect(() => {
        if (firstSort.current) {
            firstSort.current = false;
            return;
        }
        reload();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [sort, direction]);

    return { search, setSearch, sort, setSort, direction, setDirection };
}