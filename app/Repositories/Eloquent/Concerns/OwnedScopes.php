<?php

namespace App\Repositories\Eloquent\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

trait OwnedScopes
{
    public function findOwned(int $id, $user): Model
    {
        $model = $this->model->where('id', $id)
            ->where('user_id', is_object($user) ? $user->id : $user)
            ->first();

        if (! $model) {
            throw (new ModelNotFoundException)->setModel(get_class($this->model), [$id]);
        }

        return $model;
    }
}
