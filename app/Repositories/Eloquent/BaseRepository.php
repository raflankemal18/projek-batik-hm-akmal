<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Eloquent\Concerns\OwnedScopes;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository
{
    use OwnedScopes;

    protected Model $model;

    public function __construct()
    {
        $this->model = app($this->modelClass());
    }

    abstract protected function modelClass(): string;

    protected function query()
    {
        return $this->model->newQuery();
    }

    public function find(int $id): Model
    {
        return $this->model->findOrFail($id);
    }

    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    public function update(int $id, array $data): Model
    {
        $model = $this->find($id);
        $model->update($data);

        return $model;
    }

    public function delete(int $id): bool
    {
        return $this->find($id)->delete();
    }
}
