# Уровень City — файловая структура

```
levels/city/
├── level.php                 # Манифест уровня (id, список классов, bootstrap)
├── bootstrap.php             # Хуки WP: шорткод, вкладка города, meta, пересчёт при save
├── ajax.php                  # Заглушка; публичный AJAX зарегистрирован в bootstrap.php
│
├── class-settings.php        # Опции городского уровня (карта полей, CSV-привязки)
├── class-bridge.php          # Cities (wscity_*) → показатели → листовой E → meta
├── class-defaults.php        # Дефолтная карта полей и начальный пакет настроек
├── class-data.php            # Индекс E города, субиндексы, данные для сравнения/карты
├── class-regression.php      # Регрессия и рекомендации по стране
├── class-explorer.php        # Блок «Анализ города», AJAX сравнение и explorer
├── class-country-integration.php  # Вкладка страны: подмена explorer, fallback «Страна/Город»
├── class-renderer.php        # Вывод на странице города/страны, подключение assets
│
├── admin/
│   └── panel.php             # Админ: 7 вкладок настроек «Эргономичность города»
│
└── public/assets/
    ├── css/city-ergo-public.css
    ├── css/city-explorer-public.css
    └── js/
        ├── wsergo-city-explorer-boot.js
        ├── wsergo-city-search.js
        ├── wsergo-city-compare.js
        └── wsergo-city-regression-chart.js
```

## Суть по файлам

| Файл | Суть |
|------|------|
| `level.php` | Подключение уровня в `WSErgo_Level_Registry` |
| `bootstrap.php` | Регистрация хуков, `wsergo_city_leaf_index`, автопересчёт при сохранении города |
| `class-bridge.php` | Связь с **worldstat-cities**, расчёт листового E без кварталов |
| `class-data.php` | E города (кварталы или лист), выборки для UI и платформы |
| `class-regression.php` | OLS по городам страны, текст рекомендаций |
| `class-explorer.php` | Блок «Анализ города», AJAX, `capture_render_block()` для вкладки страны |
| `class-country-integration.php` | Встраивание explorer на вкладку страны и fallback «Страна/Город» без правок levels/country/ |
| `class-renderer.php` | Карточка E, секция эргономики, шорткод `[wsergo_city_e]` |
| `class-settings.php` | Чтение/сохранение городских опций в `wp_options` |
| `class-defaults.php` | Стартовые сопоставления полей и сид настроек |
| `admin/panel.php` | Настройки: обзор, измерения, показатели, данные, формула, k, агрегация |
| `public/assets/*` | Поиск городов, таблица сравнения, график регрессии, стили |

## Поток данных

```
worldstat-cities (wscity_*)
        → class-bridge.php (листовой E)
        → class-data.php (агрегация по кварталам, если есть)
        → class-renderer.php / class-explorer.php (показ)
```

## Интеграция с уровнем country

`WSErgo_City_Country_Integration` перехватывает `WSErgo_Renderer::render_country_tab()` и подменяет legacy `wsergo-city-leaf-explorer` на `WSErgo_City_Explorer::capture_render_block()`. AJAX `wsergo_load_country_city_explorer` обслуживается city-уровнем. Если на странице нет макро-блока country (`macro_datasets`), показываются собственные подвкладки «Страна» / «Город» (`.wsergo-city-ergo-scope`). Assets — только из `levels/city/public/assets/`.

Ядро расчёта: `core/class-ergo-calculator.php`, `core/class-ergo-expression.php`.
