<?php

namespace App\Repositories;

use CodeIgniter\Model;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseResult;

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

    public function insert(array $data): int|string|bool
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

    // ========================================================================
    // SAFE QUERY HELPER METHODS (Phase Hotfix Enterprise)
    // Prevents "Call to a member function getResultArray() on false"
    // ========================================================================

    /**
     * Safe execution of $builder->get() returning result array.
     * If query fails, logs the error and returns empty array.
     *
     * @param BaseBuilder $builder  The query builder instance
     * @param int|null    $limit    Optional row limit
     * @param string      $caller   Calling method name for error log context
     * @return array
     */
    protected static function safeGet(BaseBuilder $builder, ?int $limit = null, string $caller = ''): array
    {
        try {
            $db = \Config\Database::connect();
            $query = ($limit !== null) ? $builder->get($limit) : $builder->get();

            if ($query === false || !($query instanceof BaseResult)) {
                $error = $db->error();
                log_message('error', '[SafeGet] Query gagal pada ' . $caller . ' | Code: ' . ($error['code'] ?? 'N/A') . ' | Message: ' . ($error['message'] ?? 'Unknown') . ' | SQL: ' . (string)$db->getLastQuery());
                return [];
            }

            return $query->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', '[SafeGet] Exception pada ' . $caller . ' | ' . $e->getMessage() . ' | File: ' . $e->getFile() . ':' . $e->getLine());
            return [];
        }
    }

    /**
     * Safe execution of $builder->get() returning single row array.
     * If query fails, logs the error and returns null.
     *
     * @param BaseBuilder $builder  The query builder instance
     * @param string      $caller   Calling method name for error log context
     * @return array|null
     */
    protected static function safeRow(BaseBuilder $builder, string $caller = ''): ?array
    {
        try {
            $db = \Config\Database::connect();
            $query = $builder->get();

            if ($query === false || !($query instanceof BaseResult)) {
                $error = $db->error();
                log_message('error', '[SafeRow] Query gagal pada ' . $caller . ' | Code: ' . ($error['code'] ?? 'N/A') . ' | Message: ' . ($error['message'] ?? 'Unknown') . ' | SQL: ' . (string)$db->getLastQuery());
                return null;
            }

            return $query->getRowArray() ?: null;
        } catch (\Throwable $e) {
            log_message('error', '[SafeRow] Exception pada ' . $caller . ' | ' . $e->getMessage() . ' | File: ' . $e->getFile() . ':' . $e->getLine());
            return null;
        }
    }

    /**
     * Safe execution of $builder->get() returning result array (alias for safeGet).
     *
     * @param BaseBuilder $builder
     * @param string      $caller
     * @return array
     */
    protected static function safeResult(BaseBuilder $builder, string $caller = ''): array
    {
        return static::safeGet($builder, null, $caller);
    }
}
