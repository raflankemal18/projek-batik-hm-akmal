// AdminLayout.jsx
import { Link } from "@inertiajs/react";
import React, { useState } from "react";

export default function AdminLayout({ children, showTitle }) {
    const [open, setIsOpen] = useState(false);

    return (
        <div className="flex">
            <div
                className={`relative ${
                    open ? "w-72" : "w-20"
                } duration-300 p-5 pt-8 bg-red-500 h-screen`}
            >
                <p
                    className={`absolute cursor-pointer py-4  mr-0 top-9 right-0 w-3 border-2 border-black ${
                        !open && "rotate-180"
                    }`}
                    onClick={() => setIsOpen(!open)}
                >
                    &gt;
                </p>
                <div className="flex flex-col">
                    <Link
                        href={"/admin/dashboard"}
                        className={`text-white origin-left font-medium text-xl mb-4 duration-200 ${
                            !open && "scale-0"
                        }`}
                    >
                        AdminDashboard
                    </Link>

                    <ul
                        className={`text-white origin-left mt-4 font-medium  text-xl duration-200 ${
                            !open && "scale-0"
                        }`}
                    >
                        <hr />
                        <hr />

                        <hr />

                        <li>
                            <Link
                                href={"/admin/user"}
                                className="text-white origin-left  duration-200"
                            >
                                <p className="text-white mb-5 mt-5">User</p>
                            </Link>
                        </li>
                        <hr />
                        <li>
                            <Link
                                href={"/admin/products"}
                                className="text-white origin-left  duration-200"
                            >
                                <p className="text-white mb-5 mt-5">Products</p>
                            </Link>
                        </li>
                        <hr />

                        <li>
                            <Link
                                href={"/admin/order"}
                                className="text-white origin-left  duration-200"
                            >
                                <p className="text-white mb-5 mt-5">Order</p>
                            </Link>
                        </li>
                        <hr />
                    </ul>
                </div>

                <Link
                    className={`text-white font-medium text-xl mb-4 absolute bottom-0 ${
                        !open && "scale-0"
                    }`}
                    href={route("logout")}
                    method="post"
                >
                    Logout
                </Link>
            </div>
            <div className="p-10  w-full">
                {showTitle && <h1>Admin Dashboard</h1>}
                {children}
            </div>
        </div>
    );
}
