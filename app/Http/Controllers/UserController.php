<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;
use App\Services\AuditLogService;
use Inertia\Inertia;

class UserController extends Controller
{
    public function __construct(private UserRepositoryInterface $userRepository) {}

    public function index()
    {
        return Inertia::render('user/Dashboard');
    }

    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        $deletedUserId = $user->id;
        $deletedEmail = $user->email;

        $this->userRepository->delete($user->id);

        AuditLogService::recordAdmin('user.delete', $user, 'User dihapus', [
            'deleted_user_id' => $deletedUserId,
            'deleted_email' => $deletedEmail,
        ]);

        return redirect()->back()->with('success', 'User berhasil dihapus');
    }
}
