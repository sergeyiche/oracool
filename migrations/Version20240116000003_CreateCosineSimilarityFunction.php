<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Создает SQL функцию для вычисления косинусной близости между векторами в JSONB
 */
final class Version20240116000003_CreateCosineSimilarityFunction extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Создает SQL функцию для вычисления косинусной близости между JSONB векторами';
    }

    public function up(Schema $schema): void
    {
        // Функция для вычисления косинусной близости между двумя JSONB векторами
        $this->addSql("
            CREATE OR REPLACE FUNCTION calculate_cosine_similarity(vector1 jsonb, vector2 jsonb)
            RETURNS float AS $$
            DECLARE
                dot_product float := 0;
                magnitude1 float := 0;
                magnitude2 float := 0;
                i int;
                v1_array float[];
                v2_array float[];
            BEGIN
                -- Преобразуем JSONB в массивы
                SELECT array_agg(value::text::float) INTO v1_array
                FROM jsonb_array_elements(vector1);
                
                SELECT array_agg(value::text::float) INTO v2_array
                FROM jsonb_array_elements(vector2);
                
                -- Проверяем, что векторы одинаковой размерности
                IF array_length(v1_array, 1) != array_length(v2_array, 1) THEN
                    RETURN 0;
                END IF;
                
                -- Вычисляем скалярное произведение и магнитуды
                FOR i IN 1..array_length(v1_array, 1) LOOP
                    dot_product := dot_product + (v1_array[i] * v2_array[i]);
                    magnitude1 := magnitude1 + (v1_array[i] * v1_array[i]);
                    magnitude2 := magnitude2 + (v2_array[i] * v2_array[i]);
                END LOOP;
                
                magnitude1 := sqrt(magnitude1);
                magnitude2 := sqrt(magnitude2);
                
                -- Избегаем деления на ноль
                IF magnitude1 = 0 OR magnitude2 = 0 THEN
                    RETURN 0;
                END IF;
                
                -- Возвращаем косинусную близость
                RETURN dot_product / (magnitude1 * magnitude2);
            END;
            $$ LANGUAGE plpgsql IMMUTABLE PARALLEL SAFE;
        ");
        
        // Создаем индекс для ускорения поиска (GIN индекс для JSONB)
        $this->addSql('
            CREATE INDEX IF NOT EXISTS idx_knowledge_base_embedding_gin 
            ON knowledge_base USING gin(embedding jsonb_path_ops)
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_knowledge_base_embedding_gin');
        $this->addSql('DROP FUNCTION IF EXISTS calculate_cosine_similarity(jsonb, jsonb)');
    }
}
