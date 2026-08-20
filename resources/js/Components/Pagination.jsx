import React from "react";

export default function Pagination({ links }) {
    console.log(links);

    if (!links) {
        return <div>Loading...</div>;
    }

    return (
        <div>
            <nav aria-label="Page navigation example">
                <ul className="flex items-center -space-x-px h-10 text-base">
                    {links.map((link, i) => (
                        <li key={i}>
                            <a
                                href={link.url || "#"} // Gunakan link.url untuk navigasi, jaga "#" sebagai fallback
                                className="flex items-center justify-center px-4 h-10 leading-tight text-gray-500 border-lg border-b-2"
                                aria-current={link.active ? "page" : undefined} // Tandai link aktif jika ada
                                dangerouslySetInnerHTML={{ __html: link.label }} // Gunakan innerHTML untuk render HTML yang mungkin ada pada label, misalnya panah atau simbol HTML
                            ></a>
                        </li>
                    ))}
                </ul>
            </nav>
        </div>
    );
}
