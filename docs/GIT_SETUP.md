# Настройка Git репозитория

## Быстрая настройка

### Вариант 1: Использование скрипта (рекомендуется)

#### Windows (PowerShell):
```powershell
.\scripts\setup_git_repo.ps1
```

#### Linux/Mac/Git Bash:
```bash
chmod +x scripts/setup_git_repo.sh
./scripts/setup_git_repo.sh
```

### Вариант 2: Ручная настройка

Если Git не установлен, установите его с [https://git-scm.com/download](https://git-scm.com/download)

Затем выполните команды:

```bash
# Инициализация репозитория
git init

# Добавление remote репозитория
git remote add origin https://github.com/sergeyiche/oracool.git

# Проверка remote
git remote -v

# Добавление всех файлов
git add .

# Создание первого коммита
git commit -m "Initial commit: Oracle AI Agent Platform

- Организована структура проекта по Symfony стандартам
- Добавлена документация (Oracle.md, Agents.md, ANALYSIS_AND_PLAN.md)
- Настроена базовая архитектура платформы для ИИ-агентов
- Подготовлены доменные модели и use cases
- Добавлены конфигурационные файлы и миграции БД"

# Установка ветки main
git branch -M main

# Отправка в GitHub
git push -u origin main
```

## Если репозиторий на GitHub пустой

Если репозиторий на GitHub уже существует и пустой, может потребоваться:

```bash
git push -u origin main --force
```

## Проверка настройки

После настройки проверьте:

```bash
# Проверка remote
git remote -v

# Проверка статуса
git status

# Просмотр истории коммитов
git log --oneline
```

## Структура .gitignore

Файл `.gitignore` уже создан и включает:
- Переменные окружения (.env файлы)
- Vendor директории
- Кеш и логи
- Временные файлы
- IDE настройки

## Важные замечания

⚠️ **Никогда не коммитьте:**
- Токены API (Telegram Bot Token, OpenAI API Key и т.д.)
- Файлы `.env` с секретами
- Персональные данные пользователей
- Пароли и ключи доступа

✅ **Используйте:**
- `.env.example` для шаблонов конфигурации
- Переменные окружения для секретов
- `.gitignore` для исключения чувствительных файлов
