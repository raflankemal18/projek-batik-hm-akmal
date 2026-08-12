<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;
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

        $this->userRepository->delete($user->id);

        return redirect()->back()->with('success', 'User berhasil dihapus');
    }
}
