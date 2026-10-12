import { useEffect } from 'react';
import InputError from '@/Components/InputError';
import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from "@/Components/ui/button";

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    useEffect(() => {
        return () => {
            reset('password', 'password_confirmation');
        };
    }, []);

    const submit = (e) => {
        e.preventDefault();
        post(route('register'));
    };

    return (
        <div className="min-h-screen grid lg:grid-cols-2">
            <Head title="Daftar - BatikNesia" />

            {/* BAGIAN KIRI: Form Register */}
            <div className="flex items-center justify-center p-8 bg-batik-50 order-2 lg:order-1">
                <div className="w-full max-w-md space-y-8 bg-white p-10 rounded-2xl shadow-xl border border-batik-100">
                    <div className="text-center">
                        <Link href="/" className="inline-block mb-2">
                            <span className="font-serif text-2xl font-bold text-batik-900">
                                Batik<span className="text-batik-gold">Nesia</span>
                            </span>
                        </Link>
                        <h2 className="text-2xl font-bold text-gray-900 font-serif">Buat Akun Baru</h2>
                        <p className="mt-2 text-sm text-gray-600">Mulai perjalanan gaya etnik anda hari ini.</p>
                    </div>

                    <form onSubmit={submit} className="space-y-5">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap</label>
                            <input
                                data-testid="register-name"
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-batik-gold focus:ring-1 focus:ring-batik-gold outline-none"
                                required
                            />
                            <InputError message={errors.name} className="mt-2" />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input
                                data-testid="register-email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                className="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-batik-gold focus:ring-1 focus:ring-batik-gold outline-none"
                                required
                            />
                            <InputError message={errors.email} className="mt-2" />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Password</label>
                            <input
                                data-testid="register-password"
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                className="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-batik-gold focus:ring-1 focus:ring-batik-gold outline-none"
                                required
                            />
                            <InputError message={errors.password} className="mt-2" />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Konfirmasi Password</label>
                            <input
                                data-testid="register-password-confirmation"
                                type="password"
                                value={data.password_confirmation}
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                                className="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-batik-gold focus:ring-1 focus:ring-batik-gold outline-none"
                                required
                            />
                            <InputError message={errors.password_confirmation} className="mt-2" />
                        </div>

                        <Button data-testid="register-submit" disabled={processing} className="w-full h-12 bg-batik-gold hover:bg-yellow-600 text-batik-900 font-bold rounded-lg transition-all text-base">
                            Daftar Akun
                        </Button>

                        <div className="text-center text-sm text-gray-600">
                            Sudah punya akun?{" "}
                            <Link href={route("login")} className="text-batik-900 font-bold hover:underline">
                                Masuk disini
                            </Link>
                        </div>
                    </form>
                </div>
            </div>

            {/* BAGIAN KANAN: Visual Batik */}
            <div className="hidden lg:block relative bg-batik-900 overflow-hidden order-1 lg:order-2">
                <div className="absolute inset-0 bg-batik-900/40 z-10" />
                <img 
                    src="https://images.unsplash.com/photo-1598555835695-17a42421253c?q=80&w=1974&auto=format&fit=crop" 
                    alt="Batik Fabric" 
                    className="absolute inset-0 w-full h-full object-cover opacity-80"
                />
                <div className="relative z-20 h-full flex flex-col justify-end p-16 text-white text-right">
                    <h2 className="font-serif text-4xl font-bold mb-4">"Wastra Nusantara."</h2>
                    <p className="text-batik-100/80">Kebanggaan identitas bangsa dalam sehelai kain.</p>
                </div>
            </div>
        </div>
    );
}