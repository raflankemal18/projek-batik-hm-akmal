import { Button } from "@/Components/ui/button";
import { SheetTrigger, SheetContent, Sheet } from "@/Components/ui/sheet";
import { Link, usePage, router } from "@inertiajs/react"; 
import React, { useState } from "react";

export function Component() {
    const { auth } = usePage().props;
    console.log(auth);
    const handleLogout = () => {
        router.post(route("logout").url(), {}, {
            onFinish: () => router.reload()
        });
    };
    return (
        <header className="flex h-16 w-full items-center justify-between px-4 md:px-6">
            <Link className="flex items-center gap-2" href="/">
                <img src="/dend.jpg" className="h-6 w-6" />
                <span className="text-lg font-semibold">Toko XYZ</span>
            </Link>
            <nav className="hidden items-center gap-6 text-sm font-medium md:flex">
                <Link
                    className="hover:underline hover:underline-offset-4"
                    href="/"
                >
                    Home
                </Link>
                <Link
                    className="hover:underline hover:underline-offset-4"
                    href="/products"
                >
                    Product
                </Link>
                <Link
                    className="hover:underline hover:underline-offset-4"
                    href="/cart"
                >
                    Keranjang
                </Link>
                <Link
                    className="hover:underline hover:underline-offset-4"
                    href="/co"
                >
                    Order
                </Link>
                <Link
                    className="hover:underline hover:underline-offset-4"
                    href="/pembayaran"
                >
                    Pembayaran
                </Link>
                <p>{auth?.user?.name}</p>

                {auth?.user?.role === "admin" && (
                    <Link
                        className="hover:underline hover:underline-offset-4"
                        href={route("admin.dashboard")}
                    >
                        Admin
                    </Link>
                )}
                {auth?.user ? (
                    <Link
                        className="hover:underline hover:underline-offset-4"
                        href={route("logout")}
                        method="post"
                    >
                        Logout
                    </Link>
                ) : (
                    <Link
                        className="hover:underline hover:underline-offset-4"
                        href="/login"
                    >
                        Login
                    </Link>
                )}
            </nav>
            <Sheet>
                <SheetTrigger asChild>
                    <Button className="md:hidden" size="icon" variant="outline">
                        <MenuIcon className="h-6 w-6" />
                        <span className="sr-only">Toggle navigation menu</span>
                    </Button>
                </SheetTrigger>
                <SheetContent className="md:hidden" side="right">
                    <div className="grid gap-4 p-4">
                        <Link
                            className="flex items-center justify-between rounded-md bg-gray-100 px-4 py-2 font-medium transition-colors hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700"
                            href="/"
                        >
                            Home
                            <ChevronRightIcon className="h-5 w-5" />
                        </Link>
                        <Link
                            className="flex items-center justify-between rounded-md bg-gray-100 px-4 py-2 font-medium transition-colors hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700"
                            href="/products"
                        >
                            Product
                            <ChevronRightIcon className="h-5 w-5" />
                        </Link>
                        <Link
                            className="flex items-center justify-between rounded-md bg-gray-100 px-4 py-2 font-medium transition-colors hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700"
                            href="/cart"
                        >
                            Keranjang
                            <ChevronRightIcon className="h-5 w-5" />
                        </Link>
                        <Link
                            className="flex items-center justify-between rounded-md bg-gray-100 px-4 py-2 font-medium transition-colors hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700"
                            href="/co"
                        >
                            Order
                            <ChevronRightIcon className="h-5 w-5" />
                        </Link>
                        <Link
                            className="flex items-center justify-between rounded-md bg-gray-100 px-4 py-2 font-medium transition-colors hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700"
                            href="/pembayaran"
                        >
                            Pembayaran
                            <ChevronRightIcon className="h-5 w-5" />
                        </Link>

                        {auth?.user && auth?.user?.name ? (
                            <>
                                <p className="flex items-center justify-between rounded-md bg-gray-100 px-4 py-2 font-medium transition-colors hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700">
                                    {" "}
                                    Halo!! {auth?.user?.name}
                                </p>
                                <Link
                                    className="flex items-center justify-between rounded-md bg-gray-100 px-4 py-2 font-medium transition-colors hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700"
                                    href={route("logout")}
                                    method="post"
                                >
                                    Logout
                                    <ChevronRightIcon className="h-5 w-5" />
                                </Link>
                            </>
                        ) : (
                            <Link
                                className="flex items-center justify-between rounded-md bg-gray-100 px-4 py-2 font-medium transition-colors hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700"
                                href="/login"
                            >
                                Login
                                <ChevronRightIcon className="h-5 w-5" />
                            </Link>
                        )}
                    </div>
                </SheetContent>
            </Sheet>
        </header>
    );
}

function ChevronRightIcon(props) {
    return (
        <svg
            {...props}
            xmlns="http://www.w3.org/2000/svg"
            width="24"
            height="24"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
        >
            <path d="m9 18 6-6-6-6" />
        </svg>
    );
}

function MenuIcon(props) {
    return (
        <svg
            {...props}
            xmlns="http://www.w3.org/2000/svg"
            width="24"
            height="24"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
        >
            <line x1="4" x2="20" y1="12" y2="12" />
            <line x1="4" x2="20" y1="6" y2="6" />
            <line x1="4" x2="20" y1="18" y2="18" />
        </svg>
    );
}

function MountainIcon(props) {
    return (
        <svg
            {...props}
            xmlns="http://www.w3.org/2000/svg"
            width="24"
            height="24"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
        >
            <path d="m8 3 4 8 5-5 5 15H2L8 3z" />
        </svg>
    );
}
