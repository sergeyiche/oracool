<?php

namespace App\Core\Port;

interface VectorSearchInterface
{
    /**
     * Находит похожие записи по вектору
     * @param array $vector Вектор для поиска
     * @param string $userId UUID пользователя
     * @param float $threshold Порог схожести (0.0-1.0)
     * @param int $limit Максимальное количество результатов
     * @return array Массив результатов с оценкой схожести
     */
    public function findSimilar(
        array $vector, 
        string $userId, 
        float $threshold = 0.7, 
        int $limit = 5
    ): array;
    
    /**
     * Добавляет вектор в базу знаний
     * @param array $vector Вектор
     * @param string $userId UUID пользователя
     * @param string $text Исходный текст
     * @param array $metadata Дополнительные метаданные
     * @return string UUID созданной записи
     */
    public function addVector(
        array $vector, 
        string $userId, 
        string $text, 
        array $metadata = []
    ): string;
}
