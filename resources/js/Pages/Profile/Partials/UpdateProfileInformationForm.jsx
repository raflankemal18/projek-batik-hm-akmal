import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Link, useForm, usePage } from '@inertiajs/react';
import { Transition } from '@headlessui/react';

export default function UpdateProfileInformation({ mustVerifyEmail, status, className = '' }) {
    const user = usePage().props.auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: user.name,
        email: user.email,
    });

    const submit = (e) => {
        e.preventDefault();

        patch(route('profile.update'));
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-xl font-serif font-bold text-batik-900">Informasi Profil</h2>

                <p className="mt-1 text-sm text-gray-500">
                    Perbarui informasi profil dan alamat email akun Anda.
                </p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-6">
                <div>
                    <InputLabel htmlFor="name" value="Nama Lengkap" />

                    <TextInput
                        id="name"
                        className="mt-1 block w-full focus:border-batik-gold focus:ring-batik-gold"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        isFocused
                        autoComplete="name"
                    />

                    <InputError className="mt-2" message={errors.name} />
                </div>

                <div>
                    <InputLabel htmlFor="email" value="Alamat Email" />

                    <TextInput
                        id="email"
                        type="email"
                        className="mt-1 block w-full focus:border-batik-gold focus:ring-batik-gold"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                        autoComplete="username"
                    />

                    <InputError className="mt-2" message={errors.email} />
                </div>

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div>
                        <p className="text-sm mt-2 text-gray-800">
                            Alamat email Anda belum terverifikasi.
                            <Link
                                href={route('verification.send')}
                                method="post"
                                as="button"
                                className="underline text-sm text-batik-600 hover:text-batik-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-batik-gold ml-1"
                            >
                                Klik di sini untuk mengirim ulang email verifikasi.
                            </Link>
                        </p>

                        {status === 'verification-link-sent' && (
                            <div className="mt-2 font-medium text-sm text-green-600">
                                Link verifikasi baru telah dikirim ke alamat email Anda.
                            </div>
                        )}
                    </div>
                )}

                <div className="flex items-center gap-4">
                    <PrimaryButton 
                        disabled={processing}
                        className="bg-batik-900 hover:bg-batik-800 text-white px-6 py-2.5 rounded-md"
                    >
                        Simpan
                    </PrimaryButton>

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm text-batik-gold font-medium">Berhasil disimpan.</p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
