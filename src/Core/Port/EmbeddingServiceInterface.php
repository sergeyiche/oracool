<?php

namespace App\Core\Port;

interface EmbeddingServiceInterface
{
    /**
     * Векторизует текст
     * @param string $text Текст для векторизации
     * @return array Вектор (массив float)
     */
    public function embed(string $text): array;
    
    /**
     * Векторизует несколько текстов за один запрос (батч)
     * @param array $texts Массив текстов
     * @return array Массив векторов
     */
    public function embedBatch(array $texts): array;
    
    /**
     * Возвращает размерность вектора для данной модели
     * @return int Размерность (например, 768 или 1536)
     */
    public function getDimension(): int;
    
    /**
     * Возвращает название модели/провайдера
     * @return string Например, "ollama:nomic-embed-text"
     */
    public function getModelName(): string;
}
