import InputError from '@/Components/InputError';
import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from "@/Components/ui/button";

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <div className="min-h-screen grid lg:grid-cols-2">
            <Head title="Lupa Kata Sandi - BatikNesia" />

            {/* BAGIAN KIRI: Visual Batik */}
            <div className="hidden lg:block relative bg-batik-900 overflow-hidden">
                <div className="absolute inset-0 bg-batik-900/40 z-10" />
                <img 
                    src="https://images.unsplash.com/photo-1605330880193-277567793d98?q=80&w=2070&auto=format&fit=crop" 
                    alt="Batik Pattern" 
                    className="absolute inset-0 w-full h-full object-cover opacity-80"
                />
                <div className="relative z-20 h-full flex flex-col justify-end p-16 text-white">
                    <h2 className="font-serif text-4xl font-bold mb-4">"Kembali Terhubung dengan Warisan Kita."</h2>
                    <p className="text-batik-100/80 max-w-md">Kami akan membantu Anda mendapatkan kembali akses ke akun Anda.</p>
                </div>
            </div>

            {/* BAGIAN KANAN: Form Reset */}
            <div className="flex items-center justify-center p-8 bg-batik-50">
                <div className="w-full max-w-md space-y-8 bg-white p-10 rounded-2xl shadow-xl border border-batik-100">
                    
                    <div className="text-center">
                        <Link href="/" className="inline-block mb-2">
                            <span className="font-serif text-2xl font-bold text-batik-900">
                                Batik<span className="text-batik-gold">Nesia</span>
                            </span>
                        </Link>
                        <h2 className="text-2xl font-bold text-gray-900 font-serif">Lupa Kata Sandi?</h2>
                        <p className="mt-2 text-sm text-gray-500">
                            Tidak masalah. Masukkan alamat email Anda dan kami akan mengirimkan tautan untuk mengatur ulang kata sandi Anda.
                        </p>
                    </div>

                    {status && (
                        <div className="mb-4 font-medium text-sm text-batik-900 bg-batik-gold/20 border border-batik-gold p-4 rounded-lg">
                            {status}
                        </div>
                    )}

                    <form onSubmit={submit} className="space-y-6">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input
                                type="email"
                                name="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                className="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-batik-gold focus:ring-1 focus:ring-batik-gold outline-none transition-all"
                                placeholder="nama@email.com"
                                required
                                autoFocus
                            />
                            <InputError message={errors.email} className="mt-2 text-red-600" />
                        </div>

                        <Button disabled={processing} className="w-full h-12 bg-batik-900 hover:bg-batik-800 text-white font-bold rounded-lg transition-all text-base">
                            Kirim Tautan Reset
                        </Button>
                        
                        <div className="text-center text-sm text-gray-600 mt-4">
                            Ingat kata sandi Anda?{" "}
                            <Link href={route("login")} className="text-batik-gold font-bold hover:underline">
                                Kembali untuk Masuk
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}
