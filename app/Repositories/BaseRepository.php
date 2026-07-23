<?php

namespace App\Repositories;

use CodeIgniter\Model;

abstract class BaseRepository
{
    protected Model $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function find(int $id): ?array
    {
        return $this->model->find($id);
    }

    public function findAll(): array
    {
        return $this->model->findAll();
    }

    public function insert(array $data)
    {
        \Config\Services::cache()->clean();
        return $this->model->insert($data);
    }

    public function update(int $id, array $data): bool
    {
        \Config\Services::cache()->clean();
        return $this->model->update($id, $data);
    }

    public function delete(int $id): bool
    {
        \Config\Services::cache()->clean();
        return $this->model->delete($id);
    }

    public function getModel(): Model
    {
        return $this->model;
    }
}
