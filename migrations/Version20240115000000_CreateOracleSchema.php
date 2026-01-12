// migrations/Version20240115000000_CreateOracleSchema.php
final class Version20240115000000_CreateOracleSchema extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // Таблица стоических добродетелей пользователя
        $this->addSql('
            CREATE TABLE user_virtues (
                id UUID PRIMARY KEY,
                user_id UUID NOT NULL,
                wisdom INTEGER DEFAULT 0,
                courage INTEGER DEFAULT 0,
                justice INTEGER DEFAULT 0,
                temperance INTEGER DEFAULT 0,
                last_assessed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            )
        ');
        
        // Таблица когнитивных искажений
        $this->addSql('
            CREATE TABLE cognitive_distortions (
                id UUID PRIMARY KEY,
                user_id UUID NOT NULL,
                type VARCHAR(50) NOT NULL,
                description TEXT NOT NULL,
                detected_in TEXT NOT NULL,
                reframed_thought TEXT DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            )
        ');
        
        // Таблица философских рефлексий
        $this->addSql('
            CREATE TABLE reflections (
                id UUID PRIMARY KEY,
                user_id UUID NOT NULL,
                topic VARCHAR(255) NOT NULL,
                insight TEXT NOT NULL,
                kairos_type VARCHAR(50) NOT NULL,
                significance VARCHAR(20) NOT NULL,
                tags JSONB DEFAULT \'[]\',
                created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            )
        ');
        
        // Таблица стоических упражнений
        $this->addSql('
            CREATE TABLE stoic_exercises (
                id UUID PRIMARY KEY,
                user_id UUID NOT NULL,
                exercise_type VARCHAR(50) NOT NULL,
                description TEXT NOT NULL,
                completed BOOLEAN DEFAULT false,
                reflection TEXT DEFAULT NULL,
                assigned_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
                completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            )
        ');
        
        // Индексы для аналитики
        $this->addSql('CREATE INDEX idx_reflections_user_created ON reflections(user_id, created_at DESC)');
        $this->addSql('CREATE INDEX idx_distortions_type ON cognitive_distortions(type)');
    }
}