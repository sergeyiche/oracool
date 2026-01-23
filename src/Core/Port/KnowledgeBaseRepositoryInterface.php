<?php

namespace App\Core\Port;

use App\Core\Domain\KnowledgeBase\KnowledgeBaseEntry;

interface KnowledgeBaseRepositoryInterface
{
    /**
     * Находит запись по ID
     */
    public function findById(string $id): ?KnowledgeBaseEntry;
    
    /**
     * Находит все записи пользователя
     */
    public function findByUserId(string $userId): array;
    
    /**
     * Сохраняет запись
     */
    public function save(KnowledgeBaseEntry $entry): void;
    
    /**
     * Удаляет запись
     */
    public function delete(string $id): void;
    
    /**
     * Массовый импорт записей
     */
    public function bulkImport(array $entries): void;
    
    /**
     * Подсчитывает количество записей пользователя
     */
    public function countByUserId(string $userId): int;
}
