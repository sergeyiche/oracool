# Инструкция по установке Oracle Platform MVP

## Требования

- PHP 8.1 или выше
- PostgreSQL 14+ с расширением `pgvector`
- Redis 6+
- Composer 2+
- Ollama (для локальных AI моделей) или API ключи OpenAI

## Шаг 1: Установка зависимостей

```bash
composer install
```

## Шаг 2: Настройка базы данных

### Установка PostgreSQL с pgvector

#### Ubuntu/Debian:
```bash
sudo apt update
sudo apt install postgresql-14 postgresql-contrib-14

# Установка pgvector
sudo apt install postgresql-14-pgvector
```

#### macOS (Homebrew):
```bash
brew install postgresql@14
brew install pgvector
```

#### Docker (альтернатива):
```bash
docker run -d \
  --name oracool-postgres \
  -e POSTGRES_PASSWORD=password \
  -e POSTGRES_USER=oracool \
  -e POSTGRES_DB=oracool \
  -p 5432:5432 \
  ankane/pgvector:latest
```

### Создание базы данных

```bash
# Подключитесь к PostgreSQL
psql -U postgres

# Создайте базу данных
CREATE DATABASE oracool;
CREATE USER oracool WITH ENCRYPTED PASSWORD 'password';
GRANT ALL PRIVILEGES ON DATABASE oracool TO oracool;

# Выйдите из psql
\q
```

## Шаг 3: Установка Redis

```bash
# Ubuntu/Debian
sudo apt install redis-server
sudo systemctl start redis-server

# macOS
brew install redis
brew services start redis

# Docker (альтернатива)
docker run -d --name oracool-redis -p 6379:6379 redis:7-alpine
```

## Шаг 4: Установка Ollama (для локальных AI моделей)

```bash
# Linux
curl -fsSL https://ollama.com/install.sh | sh

# macOS
brew install ollama

# Скачайте необходимые модели
ollama pull nomic-embed-text  # для эмбеддингов (768 размерность)
ollama pull llama3.2          # для генерации текста
```

Проверьте, что Ollama запущен:
```bash
curl http://localhost:11434/api/tags
```

## Шаг 5: Настройка переменных окружения

Скопируйте файл с примером и настройте параметры:

```bash
cp env.example .env
```

Отредактируйте `.env`:

```bash
# Базовые настройки
APP_ENV=dev
APP_SECRET=ваш-секретный-ключ-здесь

# База данных
DATABASE_URL="postgresql://oracool:password@127.0.0.1:5432/oracool?serverVersion=14&charset=utf8"

# Redis
MESSENGER_TRANSPORT_DSN=redis://127.0.0.1:6379/messages
CACHE_DSN=redis://127.0.0.1:6379/cache

# Telegram Bot
TELEGRAM_BOT_TOKEN=ваш-токен-от-@BotFather
TELEGRAM_SECRET_TOKEN=случайная-строка-для-webhook
TELEGRAM_WEBHOOK_URL=https://ваш-домен.com/webhook/telegram/oracle

# AI Провайдеры (начните с ollama)
EMBEDDING_PROVIDER=ollama
LLM_PROVIDER=ollama

# Ollama настройки
OLLAMA_BASE_URL=http://localhost:11434
OLLAMA_EMBEDDING_MODEL=nomic-embed-text
OLLAMA_LLM_MODEL=llama3.2
OLLAMA_EMBEDDING_DIMENSION=768

# ID владельца бота (ваш Telegram ID)
BOT_OWNER_TELEGRAM_ID=ваш-telegram-id
```

### Как узнать свой Telegram ID:
1. Напишите боту [@userinfobot](https://t.me/userinfobot)
2. Скопируйте ваш ID

## Шаг 6: Применение миграций

```bash
# Создайте структуру базы данных
php bin/console doctrine:migrations:migrate --no-interaction
```

Если возникают ошибки, проверьте подключение к БД:
```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

## Шаг 7: Проверка установки

```bash
# Проверьте подключение к БД
php bin/console doctrine:query:sql "SELECT version()"

# Проверьте расширение pgvector
php bin/console doctrine:query:sql "SELECT * FROM pg_extension WHERE extname = 'vector'"

# Проверьте что таблицы созданы
php bin/console doctrine:query:sql "SELECT tablename FROM pg_tables WHERE schemaname = 'public'"
```

## Шаг 8: Настройка Telegram webhook (для продакшена)

Для разработки используйте ngrok или аналог для тестирования локально:

```bash
# Установите ngrok
# https://ngrok.com/download

# Запустите туннель
ngrok http 8000

# Скопируйте URL (например, https://abc123.ngrok.io)
# Обновите TELEGRAM_WEBHOOK_URL в .env

# Установите webhook
curl -X POST "https://api.telegram.org/bot<ваш-токен>/setWebhook" \
  -H "Content-Type: application/json" \
  -d '{"url": "https://abc123.ngrok.io/webhook/telegram/oracle", "secret_token": "ваш-секретный-токен"}'
```

## Шаг 9: Запуск приложения

### Режим разработки:

```bash
# Запустите встроенный сервер Symfony
symfony server:start

# Или используйте PHP встроенный сервер
php -S localhost:8000 -t public/
```

### Запуск workers для обработки очередей:

```bash
# В отдельном терминале
php bin/console messenger:consume async -vv
```

## Шаг 10: Тестирование

1. Отправьте сообщение вашему боту в Telegram
2. Проверьте логи: `tail -f var/log/dev.log`
3. Проверьте что сообщение сохранено в БД:
   ```bash
   php bin/console doctrine:query:sql "SELECT * FROM messages ORDER BY created_at DESC LIMIT 5"
   ```

## Переключение на OpenAI (опционально)

Если хотите использовать OpenAI вместо Ollama:

1. Получите API ключ на https://platform.openai.com/api-keys
2. Обновите `.env`:
   ```bash
   EMBEDDING_PROVIDER=openai
   LLM_PROVIDER=openai
   OPENAI_API_KEY=sk-ваш-ключ-здесь
   ```
3. Перезапустите приложение

## Устранение неполадок

### Ошибка: "SQLSTATE[08006] Connection refused"
- Проверьте, что PostgreSQL запущен: `sudo systemctl status postgresql`
- Проверьте DATABASE_URL в `.env`

### Ошибка: "Extension vector does not exist"
- Установите pgvector: `sudo apt install postgresql-14-pgvector`
- Или выполните в psql: `CREATE EXTENSION vector;`

### Ошибка: "Connection refused to Redis"
- Проверьте, что Redis запущен: `redis-cli ping` (должен ответить PONG)

### Ошибка при обращении к Ollama
- Проверьте, что Ollama запущен: `curl http://localhost:11434`
- Проверьте что модели загружены: `ollama list`

## Следующие шаги

1. Создайте базу знаний (см. `docs/KNOWLEDGE_BASE_SETUP.md`)
2. Настройте режимы работы бота
3. Начните обучение на ваших сообщениях

## Поддержка

Если возникли проблемы:
1. Проверьте логи: `var/log/dev.log`
2. Включите debug режим в `.env`: `APP_ENV=dev`
3. Создайте issue в репозитории
