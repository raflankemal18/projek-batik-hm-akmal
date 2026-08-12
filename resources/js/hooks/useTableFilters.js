import { useState, useEffect, useRef } from "react";
import { router } from "@inertiajs/react";

export default function useTableFilters(initialSort = "created_at", initialDirection = "desc") {
    const [search, setSearch] = useState("");
    const [sort, setSort] = useState(initialSort);
    const [direction, setDirection] = useState(initialDirection);
    const first = useRef(true);

    const reload = () => {
        router.reload({
            data: { search, sort, direction },
            preserveState: true,
            preserveScroll: true,
        });
    };

    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const t = setTimeout(reload, 350);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    useEffect(() => {
        if (first.current) return;
        reload();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [sort, direction]);

    return { search, setSearch, sort, setSort, direction, setDirection };
}
