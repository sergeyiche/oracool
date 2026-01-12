# Установка webhook для Telegram
curl -X POST "https://api.telegram.org/bot{TOKEN}/setWebhook" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://your-domain.com/webhook/telegram/joy_optimizer_primary",
    "secret_token": "your-secret-token",
    "drop_pending_updates": true
  }'

# Запуск workers
php bin/console messenger:consume ai_processing scheduled_processing