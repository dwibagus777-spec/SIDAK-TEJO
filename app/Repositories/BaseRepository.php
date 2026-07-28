<?php

namespace App\Repositories;

use CodeIgniter\Model;
use CodeIgniter\Database\BaseBuilder;

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
        $this->clearCache();
        return $this->model->insert($data);
    }

    public function update(int $id, array $data): bool
    {
        $this->clearCache();
        return $this->model->update($id, $data);
    }

    public function delete(int $id): bool
    {
        $this->clearCache();
        return $this->model->delete($id);
    }

    public function getModel(): Model
    {
        return $this->model;
    }

    protected function getBuilder(?string $table = null): BaseBuilder
    {
        $db = \Config\Database::connect();
        return $table ? $db->table($table) : $this->model->builder();
    }

    protected function clearCache(): void
    {
        \Config\Services::cache()->clean();
    }

    public function __call(string $name, array $arguments)
    {
        return $this->model->$name(...$arguments);
    }
}
