import WelcomeLayout from '@/Layouts/WelcomeLayout';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';
import { Head, usePage } from '@inertiajs/react';
import { UserCircle } from 'lucide-react';

export default function Edit({ auth, mustVerifyEmail, status }) {
    return (
        <WelcomeLayout>
            <Head title="Profil Saya - BatikNesia" />

            <div className="min-h-screen bg-[#faf9f6] py-12">
                <div className="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-8">
                    
                    <div className="flex items-center gap-4 mb-8">
                        <div className="w-16 h-16 rounded-full bg-batik-gold/20 flex items-center justify-center">
                            <UserCircle className="w-10 h-10 text-batik-gold" />
                        </div>
                        <div>
                            <h1 className="text-3xl font-serif font-bold text-batik-900">Pengaturan Profil</h1>
                            <p className="text-gray-500">Kelola informasi pribadi dan keamanan akun Anda</p>
                        </div>
                    </div>

                    <div className="p-6 sm:p-10 bg-white shadow-xl shadow-gray-200/40 rounded-2xl border border-gray-100">
                        <UpdateProfileInformationForm
                            mustVerifyEmail={mustVerifyEmail}
                            status={status}
                            className="max-w-xl"
                        />
                    </div>

                    <div className="p-6 sm:p-10 bg-white shadow-xl shadow-gray-200/40 rounded-2xl border border-gray-100">
                        <UpdatePasswordForm className="max-w-xl" />
                    </div>

                    <div className="p-6 sm:p-10 bg-red-50/50 shadow-xl shadow-gray-200/40 rounded-2xl border border-red-100">
                        <DeleteUserForm className="max-w-xl" />
                    </div>
                </div>
            </div>
        </WelcomeLayout>
    );
}
