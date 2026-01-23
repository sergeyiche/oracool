# 🚀 Развертывание и настройка Философского Telegram-бота

**Статус:** ✅ Production Ready  
**Версия:** 1.0.0  
**Дата:** 2026-01-16

---

## 📋 Содержание

1. [Требования](#требования)
2. [Быстрый старт](#быстрый-старт)
3. [Docker развертывание](#docker-развертывание)
4. [Настройка AI (DeepSeek)](#настройка-ai)
5. [Telegram webhook](#telegram-webhook)
6. [Переключение моделей](#переключение-моделей)
7. [Тестирование](#тестирование)
8. [Troubleshooting](#troubleshooting)

---

## 🔧 Требования

### Система:
- **OS:** Linux / macOS / Windows (WSL2)
- **Docker:** 20.10+
- **Docker Compose:** 2.0+
- **RAM:** Минимум 4GB, рекомендуется 8GB
- **Диск:** 10GB свободного места

### API ключи:
- **Telegram Bot Token** (получить у @BotFather)
- **DeepSeek API Key** (https://platform.deepseek.com) - рекомендуется
- *Или* **OpenAI API Key** (https://platform.openai.com) - альтернатива

---

## ⚡ Быстрый старт

### 1. Клонирование и настройка

```bash
# Клонируйте репозиторий
cd /www
git clone <your-repo-url> oracool
cd oracool

# Скопируйте .env
cp env.example .env

# Отредактируйте .env
nano .env
```

### 2. Минимальная конфигурация `.env`

```bash
# PostgreSQL
POSTGRES_DB=oracool
POSTGRES_USER=oracool
POSTGRES_PASSWORD=your_strong_password

# Telegram
TELEGRAM_BOT_TOKEN=your_bot_token_from_botfather
TELEGRAM_WEBHOOK_SECRET=your_random_secret_string

# AI Providers
EMBEDDING_PROVIDER=ollama
LLM_PROVIDER=deepseek

# DeepSeek (рекомендуется)
DEEPSEEK_API_KEY=sk-your-deepseek-api-key
DEEPSEEK_BASE_URL=https://api.deepseek.com
DEEPSEEK_LLM_MODEL=deepseek-chat

# Ollama (для embedding)
OLLAMA_BASE_URL=http://ollama:11434
OLLAMA_EMBEDDING_MODEL=nomic-embed-text
OLLAMA_EMBEDDING_DIMENSION=768
```

### 3. Запуск

```bash
# Поднимите контейнеры
docker compose up -d

# Дождитесь запуска (30-60 сек)
docker compose logs -f

# Проверьте статус
docker compose ps
```

### 4. Инициализация базы данных

```bash
# Примените миграции
docker exec oracool-app php bin/console doctrine:migrations:migrate --no-interaction

# Создайте профиль пользователя
docker exec oracool-app php bin/console profile:create YOUR_TELEGRAM_ID --mode=active
```

### 5. Загрузите базу знаний

```bash
# Импортируйте философские тексты
cd knowledge_examples
./import_all.sh YOUR_TELEGRAM_ID

# Проверьте статистику
docker exec oracool-app php bin/console knowledge:stats YOUR_TELEGRAM_ID
```

### 6. Настройте webhook

```bash
# Установите webhook (замените URL)
docker exec oracool-app php bin/console telegram:webhook:setup \
  https://your-domain.com/webhook/telegram

# Проверьте статус
docker exec oracool-app php bin/console telegram:webhook:setup --info
```

### 7. Протестируйте

```bash
# Тест полного пайплайна
docker exec oracool-app php bin/console test:response YOUR_TELEGRAM_ID \
  "Как найти смысл жизни?"
```

**Готово!** Откройте бота в Telegram и начинайте диалог.

---

## 🐳 Docker развертывание

### Архитектура контейнеров

```
┌─────────────┐  ┌─────────────┐  ┌─────────────┐
│   Nginx     │  │   PHP-FPM   │  │  Messenger  │
│   :8000     │←─│   Symfony   │←─│   Consumer  │
└─────────────┘  └─────────────┘  └─────────────┘
                        ↓                  ↓
         ┌──────────────┴──────────────────┴──────┐
         │                                         │
    ┌────▼────┐  ┌───────────┐  ┌────────────┐   │
    │PostGres │  │   Redis   │  │   Ollama   │   │
    │+pgvector│  │  :6379    │  │  :11434    │   │
    └─────────┘  └───────────┘  └────────────┘   │
                                                  │
                                    ┌─────────────▼──┐
                                    │  DeepSeek API  │
                                    │   (external)   │
                                    └────────────────┘
```

### Сервисы:

| Сервис | Порт | Описание |
|--------|------|----------|
| `nginx` | 8000 | Веб-сервер |
| `app` | - | PHP-FPM + Symfony |
| `postgres` | 5433 | PostgreSQL + pgvector |
| `redis` | 6379 | Кэш и очереди |
| `messenger-consumer` | - | Асинхронная обработка |
| `ollama` | - | AI embeddings |

### Полезные команды Docker

```bash
# Запуск/остановка
docker compose up -d
docker compose down

# Перезапуск отдельного сервиса
docker compose restart app

# Логи
docker compose logs -f
docker compose logs app -f

# Очистка
docker compose down -v  # удалить volumes
docker system prune -a  # очистить всё

# Зайти в контейнер
docker exec -it oracool-app bash
```

---

## 🤖 Настройка AI

### DeepSeek (рекомендуется)

**Почему DeepSeek:**
- ✅ В 10 раз дешевле OpenAI (~$2/месяц)
- ✅ Отличная поддержка русского языка
- ✅ Качество как GPT-4
- ✅ Быстрая генерация (7-10 сек)

**Настройка:**

```bash
# 1. Получите ключ на https://platform.deepseek.com
# 2. Пополните баланс ($5-10 хватит надолго)

# 3. Добавьте в .env
DEEPSEEK_API_KEY=sk-your-api-key

# 4. Переключитесь на DeepSeek
/www/oracool/scripts/switch_llm.sh deepseek

# 5. Протестируйте
docker exec oracool-app php bin/console test:response YOUR_ID \
  "Как найти смысл жизни?"
```

**Ожидаемый результат:**
- ✅ LLM Model: `deepseek:deepseek-chat`
- ✅ Время: 7-10 секунд
- ✅ Чистый русский язык
- ✅ RAG релевантность: 85-90%

### OpenAI (альтернатива)

```bash
# Добавьте в .env
OPENAI_API_KEY=sk-your-openai-key

# Переключитесь
/www/oracool/scripts/switch_llm.sh openai
```

### Ollama (бесплатно, но проблемы с русским)

```bash
# Переключитесь на Llama3.2
/www/oracool/scripts/switch_llm.sh llama

# Или Mistral
/www/oracool/scripts/switch_llm.sh mistral
```

---

## 📱 Telegram webhook

### Создание бота

```bash
# 1. Найдите @BotFather в Telegram
# 2. Отправьте /newbot
# 3. Следуйте инструкциям
# 4. Скопируйте токен
```

### Настройка webhook

**Требования:**
- HTTPS домен (ngrok для разработки)
- Публичный URL доступен из интернета

```bash
# Установите webhook
docker exec oracool-app php bin/console telegram:webhook:setup \
  https://your-domain.com/webhook/telegram

# Проверьте статус
docker exec oracool-app php bin/console telegram:webhook:setup --info

# Удалите webhook (если нужно)
docker exec oracool-app php bin/console telegram:webhook:setup --delete
```

### Использование ngrok (для разработки)

```bash
# Установите ngrok: https://ngrok.com

# Запустите туннель
ngrok http 8000

# Скопируйте HTTPS URL и установите webhook
docker exec oracool-app php bin/console telegram:webhook:setup \
  https://your-ngrok-url.ngrok.io/webhook/telegram
```

---

## 🔄 Переключение моделей

### Скрипт `switch_llm.sh`

Автоматически:
1. Обновляет `.env`
2. Обновляет `config/services.yaml`
3. Перезапускает контейнеры
4. Очищает кэш

```bash
# DeepSeek (рекомендуется)
/www/oracool/scripts/switch_llm.sh deepseek

# OpenAI GPT-4o-mini
/www/oracool/scripts/switch_llm.sh openai

# Ollama Llama3.2
/www/oracool/scripts/switch_llm.sh llama

# Ollama Mistral
/www/oracool/scripts/switch_llm.sh mistral

# Ollama Qwen2.5
/www/oracool/scripts/switch_llm.sh qwen
```

### Ручное переключение

```bash
# 1. Отредактируйте .env
nano .env

# Измените:
LLM_PROVIDER=deepseek  # или openai, ollama

# 2. Отредактируйте config/services.yaml
nano config/services.yaml

# Найдите и измените:
App\Core\Port\LLMServiceInterface:
    alias: 'llm_service.deepseek'  # или openai, ollama

# 3. Перезапустите
docker compose restart app messenger-consumer
docker exec oracool-app php bin/console cache:clear
```

---

## 🧪 Тестирование

### Тест RAG (только векторный поиск)

```bash
docker exec oracool-app php bin/console test:rag YOUR_ID \
  "как справиться со страхом?"
```

**Ожидаемый вывод:**
```
✅ Найдено 5 записей
✅ Релевантность: 85-90%
✅ Топ запись: [87.2%] "Франкл говорил..."
```

### Тест полного пайплайна (RAG + LLM)

```bash
docker exec oracool-app php bin/console test:response YOUR_ID \
  "Как найти смысл жизни?"
```

**Ожидаемый вывод:**
```
✅ Профиль загружен
✅ Ответ сгенерирован за 7-10 сек
✅ Релевантность: 87%
✅ LLM Model: deepseek:deepseek-chat
✅ Ответ: "Вместо поиска абстрактного "смысла жизни"..."
```

### Статистика базы знаний

```bash
docker exec oracool-app php bin/console knowledge:stats YOUR_ID
```

**Ожидаемый вывод:**
```
Total Entries: 217
By Source:
- manual: 217
Latest Entry: 2026-01-16
```

---

## 🐛 Troubleshooting

### Проблема: Контейнеры не запускаются

```bash
# Проверьте логи
docker compose logs

# Проверьте порты
netstat -tuln | grep -E '8000|5433|6379'

# Остановите конфликтующие сервисы
sudo systemctl stop apache2
sudo systemctl stop postgresql
```

### Проблема: База данных не создаётся

```bash
# Пересоздайте volumes
docker compose down -v
docker compose up -d

# Подождите 30 сек, затем миграции
docker exec oracool-app php bin/console doctrine:migrations:migrate
```

### Проблема: Ollama не работает

```bash
# Проверьте что контейнер запущен
docker compose ps ollama

# Проверьте доступность API
docker exec oracool-app curl http://ollama:11434/api/tags

# Скачайте модель заново
docker exec oracool-ollama ollama pull nomic-embed-text
```

### Проблема: DeepSeek "Insufficient Balance"

```bash
# Баланс закончился
# 1. Зайдите на https://platform.deepseek.com
# 2. Пополните баланс ($5-10)
# 3. Попробуйте снова
```

### Проблема: Telegram webhook не работает

```bash
# Проверьте webhook
docker exec oracool-app php bin/console telegram:webhook:setup --info

# Проверьте секрет в .env
grep TELEGRAM_WEBHOOK_SECRET .env

# Проверьте доступность URL
curl -X POST https://your-domain.com/webhook/telegram

# Переустановите webhook
docker exec oracool-app php bin/console telegram:webhook:setup --delete
docker exec oracool-app php bin/console telegram:webhook:setup \
  https://your-domain.com/webhook/telegram
```

### Проблема: Бот не отвечает

```bash
# 1. Проверьте что профиль в режиме active
docker exec oracool-app php bin/console profile:update YOUR_ID --mode=active

# 2. Проверьте что база знаний загружена
docker exec oracool-app php bin/console knowledge:stats YOUR_ID

# 3. Проверьте логи messenger consumer
docker logs oracool-messenger -f

# 4. Перезапустите consumer
docker compose restart messenger-consumer
```

### Проблема: Русский язык с артефактами

```bash
# Используете Ollama Llama/Qwen?
# Переключитесь на DeepSeek или OpenAI

/www/oracool/scripts/switch_llm.sh deepseek
```

---

## 📊 Метрики Production

### Целевые показатели:

| Метрика | Норма | Отлично |
|---------|-------|---------|
| RAG релевантность | > 70% | > 85% |
| Время ответа | < 15 сек | < 10 сек |
| База знаний | > 100 записей | > 500 записей |
| Uptime | > 99% | 99.9% |

### Мониторинг:

```bash
# Логи приложения
docker logs oracool-app -f

# Логи messenger
docker logs oracool-messenger -f

# Использование ресурсов
docker stats

# Статистика базы
docker exec oracool-app php bin/console knowledge:stats YOUR_ID
```

---

## 🔐 Безопасность

### Обязательно:

1. ✅ Используйте сильные пароли в `.env`
2. ✅ Не коммитьте `.env` в git (есть в `.gitignore`)
3. ✅ Используйте HTTPS для webhook
4. ✅ Установите `TELEGRAM_WEBHOOK_SECRET`
5. ✅ Регулярно обновляйте зависимости

```bash
# Обновление зависимостей
docker exec oracool-app composer update
```

### Production checklist:

- [ ] `.env` с сильными паролями
- [ ] HTTPS домен настроен
- [ ] Webhook secret установлен
- [ ] Firewall настроен (только 80/443)
- [ ] Логи ротируются
- [ ] Backup базы данных настроен
- [ ] Мониторинг работает

---

## 📚 Дополнительная документация

- `docs/AI_SETUP.md` - Подробно про RAG, DeepSeek, модели
- `docs/TRAINING.md` - Как обучать и пополнять базу знаний
- `knowledge_examples/README.md` - Примеры философских текстов

---

## 🆘 Поддержка

**Проблемы?**
1. Проверьте логи: `docker compose logs`
2. Смотрите Troubleshooting выше
3. Проверьте документацию в `docs/`

---

**Версия:** 1.0.0  
**Дата:** 2026-01-16  
**Статус:** ✅ Production Ready
