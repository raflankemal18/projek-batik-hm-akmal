import { useRef, useState } from 'react';
import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { useForm } from '@inertiajs/react';

export default function DeleteUserForm({ className = '' }) {
    const [confirmingUserDeletion, setConfirmingUserDeletion] = useState(false);
    const passwordInput = useRef();

    const {
        data,
        setData,
        delete: destroy,
        processing,
        reset,
        errors,
    } = useForm({
        password: '',
    });

    const confirmUserDeletion = () => {
        setConfirmingUserDeletion(true);
    };

    const deleteUser = (e) => {
        e.preventDefault();

        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => passwordInput.current.focus(),
            onFinish: () => reset(),
        });
    };

    const closeModal = () => {
        setConfirmingUserDeletion(false);

        reset();
    };

    return (
        <section className={`space-y-6 ${className}`}>
            <header>
                <h2 className="text-xl font-serif font-bold text-red-600">Hapus Akun</h2>

                <p className="mt-1 text-sm text-gray-500">
                    Setelah akun Anda dihapus, semua sumber daya dan data di dalamnya akan dihapus secara permanen.
                    Sebelum menghapus akun, harap unduh data atau informasi apa pun yang ingin Anda simpan.
                </p>
            </header>

            <DangerButton onClick={confirmUserDeletion} className="bg-red-600 hover:bg-red-700">Hapus Akun Secara Permanen</DangerButton>

            <Modal show={confirmingUserDeletion} onClose={closeModal}>
                <form onSubmit={deleteUser} className="p-6 bg-white rounded-2xl">
                    <h2 className="text-xl font-serif font-bold text-batik-900">
                        Apakah Anda yakin ingin menghapus akun ini?
                    </h2>

                    <p className="mt-2 text-sm text-gray-500">
                        Setelah akun Anda dihapus, semua sumber daya dan data di dalamnya akan dihapus secara permanen.
                        Silakan masukkan kata sandi Anda untuk mengonfirmasi bahwa Anda ingin menghapus akun Anda secara permanen.
                    </p>

                    <div className="mt-6">
                        <InputLabel htmlFor="password" value="Kata Sandi" className="sr-only" />

                        <TextInput
                            id="password"
                            type="password"
                            name="password"
                            ref={passwordInput}
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            className="mt-1 block w-3/4 focus:border-red-500 focus:ring-red-500"
                            isFocused
                            placeholder="Kata Sandi"
                        />

                        <InputError message={errors.password} className="mt-2 text-red-600" />
                    </div>

                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton onClick={closeModal} className="border-gray-300">Batal</SecondaryButton>

                        <DangerButton className="bg-red-600 hover:bg-red-700" disabled={processing}>
                            Ya, Hapus Akun
                        </DangerButton>
                    </div>
                </form>
            </Modal>
        </section>
    );
}
