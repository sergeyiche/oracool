<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Добавляет поля в таблицу messages для связи с обратной связью
 */
final class Version20240116000002_UpdateMessagesForFeedback extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Добавляет поля для хранения информации о релевантности и связи сообщений с ответами бота';
    }

    public function up(Schema $schema): void
    {
        // Добавляем поля для хранения информации о генерации ответа
        $this->addSql('
            ALTER TABLE messages 
            ADD COLUMN IF NOT EXISTS bot_response_id UUID,
            ADD COLUMN IF NOT EXISTS relevance_score FLOAT,
            ADD COLUMN IF NOT EXISTS was_responded BOOLEAN DEFAULT false,
            ADD COLUMN IF NOT EXISTS response_generated_at TIMESTAMPTZ
        ');

        // Индекс для поиска ответов бота
        $this->addSql('
            CREATE INDEX IF NOT EXISTS idx_messages_bot_response 
            ON messages(bot_response_id) 
            WHERE bot_response_id IS NOT NULL
        ');
        
        // Индекс для фильтрации по релевантности
        $this->addSql('
            CREATE INDEX IF NOT EXISTS idx_messages_relevance 
            ON messages(relevance_score) 
            WHERE relevance_score IS NOT NULL
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_messages_relevance');
        $this->addSql('DROP INDEX IF EXISTS idx_messages_bot_response');
        $this->addSql('
            ALTER TABLE messages 
            DROP COLUMN IF EXISTS response_generated_at,
            DROP COLUMN IF EXISTS was_responded,
            DROP COLUMN IF EXISTS relevance_score,
            DROP COLUMN IF EXISTS bot_response_id
        ');
    }
}
