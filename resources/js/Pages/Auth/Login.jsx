import { useEffect } from "react";
import Checkbox from "@/Components/Checkbox";
import InputError from "@/Components/InputError";
import { Head, Link, useForm } from "@inertiajs/react";
import { Button } from "@/Components/ui/button"; // Pakai Button Shadcn
import { ArrowRight } from "lucide-react";

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: "",
        password: "",
        remember: false,
    });

    useEffect(() => {
        return () => {
            reset("password");
        };
    }, []);

    const submit = (e) => {
        e.preventDefault();
        post(route("login"));
    };

    return (
        <div className="min-h-screen grid lg:grid-cols-2">
            <Head title="Masuk - BatikNesia" />

            {/* BAGIAN KIRI: Visual Batik */}
            <div className="hidden lg:block relative bg-batik-900 overflow-hidden">
                <div className="absolute inset-0 bg-batik-900/40 z-10" />
                <img 
                    src="https://images.unsplash.com/photo-1598335624164-98ec22340203?q=80&w=2070&auto=format&fit=crop" 
                    alt="Batik Process" 
                    className="absolute inset-0 w-full h-full object-cover opacity-80"
                />
                <div className="relative z-20 h-full flex flex-col justify-end p-16 text-white">
                    <h2 className="font-serif text-4xl font-bold mb-4">"Keanggunan dalam Setiap Goresan Malam."</h2>
                    <p className="text-batik-100/80 max-w-md">Bergabunglah dengan komunitas pecinta warisan budaya Indonesia.</p>
                </div>
            </div>

            {/* BAGIAN KANAN: Form Login */}
            <div className="flex items-center justify-center p-8 bg-batik-50">
                <div className="w-full max-w-md space-y-8 bg-white p-10 rounded-2xl shadow-xl border border-batik-100">
                    
                    <div className="text-center">
                        <Link href="/" className="inline-block mb-2">
                            <span className="font-serif text-2xl font-bold text-batik-900">
                                Batik<span className="text-batik-gold">Nesia</span>
                            </span>
                        </Link>
                        <h2 className="text-2xl font-bold text-gray-900 font-serif">Selamat Datang Kembali</h2>
                        <p className="mt-2 text-sm text-gray-600">Masuk ke akun anda untuk melanjutkan.</p>
                    </div>

                    {status && <div className="mb-4 font-medium text-sm text-green-600 bg-green-50 p-3 rounded">{status}</div>}

                    <form onSubmit={submit} className="space-y-6">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input
                                type="email"
                                value={data.email}
                                onChange={(e) => setData("email", e.target.value)}
                                className="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-batik-gold focus:ring-1 focus:ring-batik-gold outline-none transition-all"
                                placeholder="nama@email.com"
                            />
                            <InputError message={errors.email} className="mt-2" />
                        </div>

                        <div>
                            <div className="flex justify-between items-center mb-1">
                                <label className="block text-sm font-medium text-gray-700">Password</label>
                                {canResetPassword && (
                                    <Link href={route("password.request")} className="text-xs text-batik-gold hover:underline">
                                        Lupa password?
                                    </Link>
                                )}
                            </div>
                            <input
                                type="password"
                                value={data.password}
                                onChange={(e) => setData("password", e.target.value)}
                                className="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-batik-gold focus:ring-1 focus:ring-batik-gold outline-none transition-all"
                                placeholder="••••••••"
                            />
                            <InputError message={errors.password} className="mt-2" />
                        </div>

                        <div className="block">
                            <label className="flex items-center">
                                <Checkbox name="remember" checked={data.remember} onChange={(e) => setData("remember", e.target.checked)} />
                                <span className="ms-2 text-sm text-gray-600">Ingat saya</span>
                            </label>
                        </div>

                        <Button disabled={processing} className="w-full h-12 bg-batik-900 hover:bg-batik-800 text-white font-bold rounded-lg transition-all text-base">
                            Masuk Sekarang
                        </Button>

                        <div className="text-center text-sm text-gray-600">
                            Belum punya akun?{" "}
                            <Link href={route("register")} className="text-batik-gold font-bold hover:underline">
                                Daftar disini
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}