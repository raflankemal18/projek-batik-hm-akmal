<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    protected function modelClass(): string
    {
        return User::class;
    }

    public function paginate(int $perPage = 10)
    {
        return $this->query()
            ->select(['id', 'name', 'email', 'role', 'created_at'])
            ->paginate($perPage);
    }
}
