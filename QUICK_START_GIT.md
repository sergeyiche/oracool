# Быстрая настройка Git репозитория

## Проблема: "No such file or directory"

Если вы получили ошибку при запуске скрипта, попробуйте следующие варианты:

## Вариант 1: Запуск из корневой директории проекта

Убедитесь, что вы находитесь в корневой директории проекта:

```bash
# Проверьте текущую директорию
pwd
# Должно быть: /c/www/oracle

# Если нет, перейдите в корень проекта
cd /c/www/oracle

# Запустите скрипт
bash scripts/setup_git.sh
```

## Вариант 2: Использование полного пути

```bash
bash /c/www/oracle/scripts/setup_git.sh
```

## Вариант 3: Ручное выполнение команд

Если скрипт не работает, выполните команды вручную:

```bash
# Перейдите в корень проекта
cd /c/www/oracle

# Инициализация Git
git init

# Добавление remote
git remote add origin https://github.com/sergeyiche/oracool.git

# Проверка remote
git remote -v

# Добавление файлов
git add .

# Создание коммита
git commit -m "Initial commit: Oracle AI Agent Platform

- Организована структура проекта по Symfony стандартам
- Добавлена документация
- Настроена базовая архитектура платформы"

# Установка ветки main
git branch -M main

# Отправка в GitHub
git push -u origin main
```

## Вариант 4: Проверка прав доступа

Если скрипт не исполняется, сделайте его исполняемым:

```bash
chmod +x scripts/setup_git.sh
./scripts/setup_git.sh
```

## Проверка

После настройки проверьте:

```bash
# Проверка remote
git remote -v

# Должно показать:
# origin  https://github.com/sergeyiche/oracool.git (fetch)
# origin  https://github.com/sergeyiche/oracool.git (push)

# Проверка статуса
git status
```

## Если репозиторий на GitHub уже существует

Если репозиторий на GitHub не пустой, может потребоваться:

```bash
git pull origin main --allow-unrelated-histories
# или
git push -u origin main --force
```

⚠️ **Внимание:** `--force` перезапишет историю на GitHub. Используйте осторожно!
