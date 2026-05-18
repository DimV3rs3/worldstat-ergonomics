# WorldStat — Ergonomics

Расширение для **World Statistics Platform**: расчёт индекса эргономичности по шести измерениям (F, Cm, H, A, S, Ct), иерархическая агрегация, DSL-модели и коэффициенты. Плагин устроен как **ядро + подключаемые уровни** — отдельные модули под разные объекты учёта (территория, агломерация и т.д.), каждый со своими источниками данных и настройками.

## Зависимости

- **World Statistics Platform** — страны, вкладки на карточке страны, REST/AJAX, UI-компоненты (`WorldStat_UI`).
- Дополнительные плагины подключаются **на уровне**, если тот в этом нуждается (в поставке, например, используется WorldStat Cities для городского уровня).

Точный список обязательных плагинов см. в заголовке `worldstat-ergonomics.php` (`Requires Plugins`).

## Архитектура

```
worldstat-ergonomics.php
  └── bootstrap/load.php              — ядро, реестр уровней
  └── bootstrap/hooks.php             — регистрация в платформе
  └── bootstrap/levels-registry.php   — id активных уровней
  └── core/                           — общая модель, настройки, CPT, админка
  └── levels/{level_id}/
        level.php                     — манифест
        bootstrap.php                 — хуки уровня
        class-*.php, admin/, public/
```

| Слой | Назначение |
|------|------------|
| **Ядро (`core/`)** | Модель измерений (`WSErgo_Model`), хранилище настроек (`WSErgo_Settings`), индикаторы, CPT иерархии «помещение → здание → …», фасад рендера (`WSErgo_Renderer`), оболочка админки (`WSErgo_Admin_Shell`). |
| **Уровень (`levels/{id}/`)** | Собственные расчёты, опции, админ-панель, публичный вывод и при необходимости AJAX. Не смешивается с логикой других уровней. |
| **Платформа** | Регистрация расширения, вкладки на странице страны, провайдеры метрик для карты и рейтингов — через API платформы (`worldstat_init`, фильтры вкладок и метрик). |

Встроенные уровни (`country`, `city` и др.) перечислены в `bootstrap/levels-registry.php` и могут дополняться фильтром `wsergo_levels`.

## Концепции ядра

- **Шесть измерений** — нормализованные оценки 0–100 и сводный индекс E (DSL-формула или взвешенное среднее).
- **Иерархия объектов** — листовые сущности (CPT), агрегация вверх по правилам в настройках (`mean`, взвешивание по населению и т.д.).
- **Модели** — наборы коэффициентов и формул; переключение активной модели в админке.
- **Классификатор уровней** (`WSErgo_Tier_Classifier`) — глобальные категории индекса по перцентилям выборки (доли: 5% / 25% / 40% / 25% / 5%); используется там, где уровень отдаёт индекс в общую шкалу.

Админка: **WorldStat → Эргономичность** (`worldstat_page_wsergo-settings`). Верхние вкладки — по зарегистрированным уровням; внутри уровня — свои подразделы (модели, данные, формулы), описанные в `levels/{id}/admin/panel.php`.

## Уровень (модуль расширения)

Уровень — самостоятельный пакет в `levels/{level_id}/`. Минимальный набор:

| Файл | Роль |
|------|------|
| `level.php` | Манифест: `id`, `label`, `admin_nav`, `prefix`, список `requires`, имя `bootstrap`. |
| `bootstrap.php` | `add_action` / `add_filter`, регистрация AJAX, сброс кэшей уровня. |
| `class-data.php` | Чтение и агрегация данных **своего** источника. |
| `class-renderer.php` | HTML для вкладок платформы и виджетов. |
| `class-admin.php` | Скрипты/стили админки уровня. |
| `admin/panel.php` | Разметка настроек уровня (подключается оболочкой). |

Опционально: `class-settings.php` (опции с префиксом уровня), `public/assets/`, калькуляторы, мосты к внешним плагинам (`class-bridge.php`).

**Источник данных — ответственность уровня.** Ядро не навязывает формат импорта: уровень может читать мета другого плагина, свои таблицы, API или CSV через собственный UI. Другой автор уровня не обязан использовать те же экраны и ключи, что встроенный уровень.

### Манифест (`level.php`)

```php
return [
	'id'        => 'my_level',
	'label'     => __( 'Мой объект', 'worldstat-ergonomics' ),
	'admin_nav' => __( 'Эргономичность (мой объект)', 'worldstat-ergonomics' ),
	'prefix'    => 'my_level',
	'requires'  => [
		'class-settings.php',
		'class-data.php',
		'class-renderer.php',
		'class-admin.php',
	],
	'bootstrap' => 'bootstrap.php',
];
```

`WSErgo_Level_Registry` при загрузке подключает файлы из `requires`, затем `bootstrap.php`.

### Регистрация в реестре

В `bootstrap/levels-registry.php`:

```php
$levels = [ 'country', 'city', 'my_level' ];
return (array) apply_filters( 'wsergo_levels', $levels );
```

Или только через фильтр (без правки файла плагина):

```php
add_filter( 'wsergo_levels', function ( $levels ) {
	$levels[] = 'my_level';
	return $levels;
} );
```

### Настройки

- Общие опции и префиксы — в `core/class-ergo-settings-store.php` (`WSErgo_Settings`).
- Специфичные для уровня — в `levels/{id}/class-settings.php` с отдельным префиксом опций (`wsergo_{prefix}_*`).
- Сохранение через `register_setting` / обработчики в `WSErgo_Admin`; после изменений данных уровня вызывайте сброс своего кэша в `bootstrap.php` уровня.

### Интеграция с платформой

1. **`bootstrap/hooks.php`** — регистрация расширения на `worldstat_init`, вкладка на странице страны, провайдер метрик для карты/рейтингов (если нужен).
2. **`class-renderer.php` уровня** — callback вкладки: аргумент ISO2 (или иной код сущности), вывод через `WorldStat_UI::*`.
3. **Провайдер данных** — `WorldStat_Extensions::add_data_provider()` с метриками и callback'ами чтения (см. документацию платформы и `sdk/extension-boilerplate`).

Публичный контент вкладок подгружается платформой по AJAX/REST (`worldstat_load_tab`, REST `worldstat/v1/tabs/...`); уровень только отдаёт HTML в зарегистрированном callback.

### Публичные ресурсы

В `bootstrap.php` уровня:

```php
add_action( 'wp_enqueue_scripts', [ 'My_Level_Renderer', 'enqueue_public_assets' ], 20 );
```

URL и версия файлов — через `WSErgo_Level_Registry::url( $level_id, $relative_path )` и `::asset_version()`.

## Добавление нового уровня (краткий чек-лист)

1. Создать каталог `levels/{id}/` с манифестом и классами из таблицы выше.
2. Добавить `id` в `wsergo_levels` (файл реестра или фильтр).
3. Реализовать `class-data.php` — свой источник и расчёт индекса.
4. Реализовать `admin/panel.php` — формы настроек уровня.
5. При необходимости — `class-renderer.php` и регистрация вкладки/метрик в `bootstrap/hooks.php` или в `bootstrap.php` уровня.
6. Проверить активацию: зависимости из `level.php` / главного файла плагина, отсутствие фаталов при пустых данных.

За образцом структуры и объёма кода можно смотреть существующие каталоги в `levels/` — как референс, не как обязательный контракт для новых уровней.

## Ссылки

- Документация платформы: `world-statistics-platform/docs/PLATFORM-AND-EXTENSIONS-GUIDE.md`, `STEP-BY-STEP-NEW-EXTENSION.md`.
- Шаблон расширения: `world-statistics-platform/sdk/extension-boilerplate/`.

## Версия

`WSERGO_VERSION` в `worldstat-ergonomics.php`.
