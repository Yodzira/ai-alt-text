# AI Alt Text

**[EN]** A media library full of images without alt text? Generate draft alt texts in batches with your own OpenAI-compatible vision key — review and approve with one click. Nothing goes live without you.

**[RU]** Медиатека из сотен картинок без alt? Сгенерируйте черновики батчами через собственный AI-ключ (vision) — и одобрите в один клик. Ничего не публикуется без вашего решения.

🔗 [**Скачать бесплатно / Download free**](https://github.com/Yodzira/ai-alt-text/releases/latest/download/ai-alt-text.zip)

## Возможности

- **Скан медиатеки**: находит вложения без alt (батчами на кроне)
- **Генерация черновиков**: стиль descriptive/SEO, язык на выбор, лимит длины
- **Очередь одобрения**: черновик → [Apply] / [Reject] — approve пишет alt в нативное поле
- **Bring Your Own Key**: ваш OpenAI-совместимый ключ, никакого посредника
- **Бюджет**: дневной лимит токенов + счётчик «потрачено сегодня»
- **Чистый uninstall**: ключ API не переживает удаление плагина

## Принципы

- Ничего не применяется без одобрения (черновик — черновик)
- Ключ хранится в вашей БД и уходит только к вашему провайдеру
- Генерация — на кроне, не на посетителях
- Альтернативная отстройка: одобренный alt видят все плагины (A11yFix, аудиторы)

## Установка / Install

1. Скачайте [`ai-alt-text.zip`](https://github.com/Yodzira/ai-alt-text/releases/latest/download/ai-alt-text.zip)
2. WP-админка → **Плагины → Добавить новый → Загрузить плагин** → zip → Активировать
3. Меню **AI Alt Text** → ключ API → Save → **Generate next batch** → одобряйте

## Требования / Requirements

- WordPress 6.0+ (протестировано до 7.1), PHP 7.4+
- OpenAI-совместимый vision API ключ (ваш)

## Качество / Quality

- PHPUnit (ядро): 7 тестов, 19 assertions ✅ (промпты, парсинг ответов, бюджет, санитизация)
- Интеграция на живом WP 7.1: 15/15 (стаб-транспорт: скан → генерация → approve → meta) ✅
- Официальный Plugin Checker: 0 errors (release build) ✅
- Uninstall: таблица/опции (включая ключ) стёрты ✅

## Лицензия / License

GPL-2.0-or-later (совместимо с WordPress).

💰 **[Купить Pro / Buy Pro — 2 990 ₽/год](https://yodsira.duckdns.org/buy/ai-alt-text)** — лицензия на 1 сайт, 12 месяцев обновлений.
