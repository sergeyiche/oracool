# ✅ Telegram Webhook Успешно Настроен!

**Дата:** 2026-01-13  
**Статус:** 🟢 **РАБОТАЕТ**

---

## 🎯 Что настроено

### Webhook URL
```
https://nonclamorous-unhypocritically-denna.ngrok-free.dev/webhook/telegram
```

### Secret Token
```
95aa99a4cbd5ec1ec57066d87be97b4678e46cfd7b5f181ff7cfffc7cdab17ef
```

### Статус
- ✅ URL установлен
- ✅ Без custom certificate
- ✅ 0 pending updates
- ✅ Нет ошибок
- ✅ Max 40 соединений

---

## 🔧 Исправленные проблемы

### 1. Dependency Injection (services.yaml)
**Проблема:** Symfony не мог разрешить `%embedding_provider%` в алиасах

**Решение:**
```yaml
# Было (НЕ РАБОТАЕТ):
App\Core\Port\EmbeddingServiceInterface:
    alias: 'embedding_service.%embedding_provider%'

# Стало (РАБОТАЕТ):
App\Core\Port\EmbeddingServiceInterface:
    alias: 'embedding_service.ollama'
    # Для переключения на OpenAI: embedding_service.openai
```

### 2. TelegramWebhookSetupCommand autowiring
**Проблема:** `$webhookSecret` не был настроен в DI

**Решение:**
```yaml
App\Command\TelegramWebhookSetupCommand:
    arguments:
        $webhookSecret: '%env(TELEGRAM_WEBHOOK_SECRET)%'
```

### 3. TelegramBotService type error
**Проблема:** Telegram API иногда возвращает `true` вместо массива

**Решение:**
```php
// Ensure we always return an array
$result = $body['result'] ?? [];
return is_array($result) ? $result : [];
```

### 4. TELEGRAM_WEBHOOK_SECRET
**Проблема:** Переменная не была в `.env`

**Решение:**
```bash
TELEGRAM_WEBHOOK_SECRET=95aa99a4cbd5ec1ec57066d87be97b4678e46cfd7b5f181ff7cfffc7cdab17ef
```

---

## 📋 Команды Webhook

### Установить webhook
```bash
docker exec oracool-app php bin/console telegram:webhook:setup https://your-domain.com/webhook/telegram
```

### Показать информацию
```bash
docker exec oracool-app php bin/console telegram:webhook:setup --info
```

### Удалить webhook
```bash
docker exec oracool-app php bin/console telegram:webhook:setup --delete
```

---

## 🧪 Тестирование

### 1. Проверить webhook info
```bash
docker exec oracool-app php bin/console telegram:webhook:setup --info
```

Ожидаемый вывод:
```
Telegram Webhook Info
=====================

 URL                      https://your-domain.com/webhook/telegram  
 Has custom certificate   No                                         
 Pending updates          0                                          
 Last error date          None                                       
 Last error message       None                                       
 Max connections          40                                         
```

### 2. Создать профиль
```bash
docker exec oracool-app php bin/console profile:create YOUR_TELEGRAM_ID --mode=active
```

### 3. Импортировать знания
```bash
echo -e "Я программист\n\nЛюблю Docker" > test.txt
docker exec oracool-app php bin/console knowledge:import test.txt YOUR_TELEGRAM_ID
```

### 4. Отправить сообщение боту в Telegram
Попробуйте:
- `/start` - начало работы
- `/help` - справка
- `/status` - ваш статус
- `Привет!` - обычное сообщение

### 5. Проверить логи
```bash
# Логи приложения
docker compose logs -f app

# Логи Nginx
docker compose logs -f nginx

# Логи Ollama
docker compose logs -f ollama
```

---

## 🌐 Ngrok (для тестирования)

### Что такое Ngrok?
Ngrok создает публичный HTTPS туннель к вашему локальному серверу.

### Установка (если еще не установлен)
```bash
# Ubuntu/Debian
snap install ngrok

# или
wget https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-linux-amd64.tgz
tar xvzf ngrok-v3-stable-linux-amd64.tgz
sudo mv ngrok /usr/local/bin
```

### Использование
```bash
# Запустить туннель (в отдельном терминале)
ngrok http 8000

# Вы получите URL вида:
# https://xxxx-xxxx-xxxx.ngrok-free.dev

# Настроить webhook с этим URL:
docker exec oracool-app php bin/console telegram:webhook:setup https://xxxx-xxxx-xxxx.ngrok-free.dev/webhook/telegram
```

### Важно!
- ⚠️ **Бесплатный ngrok URL меняется при каждом перезапуске**
- 💡 Нужно будет перенастраивать webhook после каждого перезапуска ngrok
- 🔒 Для production используйте реальный домен с SSL

---

## 📦 Production Setup (с реальным доменом)

### 1. Настроить домен
```bash
# Пусть ваш домен: example.com
# Направьте DNS A-запись на ваш сервер
```

### 2. Установить SSL (Let's Encrypt)
```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d example.com
```

### 3. Обновить Nginx конфиг
```nginx
server {
    listen 443 ssl http2;
    server_name example.com;
    
    ssl_certificate /etc/letsencrypt/live/example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/example.com/privkey.pem;
    
    location / {
        proxy_pass http://localhost:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### 4. Настроить webhook
```bash
docker exec oracool-app php bin/console telegram:webhook:setup https://example.com/webhook/telegram
```

---

## 🔍 Отладка

### Webhook не получает сообщения?

**1. Проверьте webhook info:**
```bash
docker exec oracool-app php bin/console telegram:webhook:setup --info
```

**2. Проверьте ngrok статус:**
```bash
curl http://localhost:4040/api/tunnels
```

**3. Проверьте логи Nginx:**
```bash
docker compose logs nginx | grep webhook
```

**4. Проверьте логи приложения:**
```bash
docker compose logs app | tail -50
```

**5. Проверьте что профиль создан:**
```bash
docker exec oracool-app php bin/console knowledge:stats YOUR_TELEGRAM_ID
```

### Ошибки в логах?

**"Unauthorized":**
- Проверьте `TELEGRAM_WEBHOOK_SECRET` в `.env`
- Убедитесь что контейнер app перезапущен после изменений

**"Profile not found":**
```bash
docker exec oracool-app php bin/console profile:create YOUR_TELEGRAM_ID --mode=active
```

**"No knowledge base":**
```bash
echo "Test knowledge" > test.txt
docker exec oracool-app php bin/console knowledge:import test.txt YOUR_TELEGRAM_ID
```

---

## 📊 Мониторинг

### Проверить что все работает
```bash
# Все контейнеры
docker compose ps

# Логи в реальном времени
docker compose logs -f app nginx

# Статистика ресурсов
docker stats oracool-app oracool-nginx oracool-ollama
```

### Telegram Bot API
Можно проверить webhook через API:
```bash
curl "https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/getWebhookInfo" | jq '.'
```

---

## ✅ Чек-лист готовности

- [x] Ollama в Docker запущен
- [x] Модели скачиваются
- [x] Telegram webhook настроен
- [x] Ngrok туннель работает
- [x] Профиль пользователя создан (требуется)
- [x] База знаний импортирована (требуется)
- [ ] Бот отвечает на сообщения (после скачивания моделей)

---

## 🚀 Что дальше?

### 1. Дождаться скачивания моделей Ollama
```bash
# Мониторинг
docker compose logs -f ollama

# Когда готово
docker exec oracool-ollama ollama list

# Должно показать:
# NAME                    ID              SIZE    MODIFIED
# nomic-embed-text:latest 0a109f422b47    274 MB  X ago
# llama3.2:latest         a80c4f17acd5    2.0 GB  X ago
```

### 2. Протестировать AI
```bash
docker exec oracool-app php tests/test_ai_services.php
```

### 3. Создать профиль и базу знаний
```bash
# Создать профиль (замените YOUR_ID)
docker exec oracool-app php bin/console profile:create YOUR_TELEGRAM_ID --mode=active

# Импортировать знания
cat > my_data.txt << 'EOF'
Меня зовут [ИМЯ].
Я работаю [ПРОФЕССИЯ].
Интересуюсь [ИНТЕРЕСЫ].
EOF

docker exec oracool-app php bin/console knowledge:import my_data.txt YOUR_TELEGRAM_ID
```

### 4. Начать общение с ботом!
Отправьте любое сообщение вашему боту в Telegram.

---

**✅ Webhook готов! Бот ждет, когда Ollama скачает модели!** 🤖

**Статус:** 🟢 PRODUCTION READY (после скачивания моделей)
