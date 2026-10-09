# Translations

OpenMES uses Laravel JSON translations. Each language is a single JSON file.

## Adding a new language

1. Copy `en.json` to `{locale}.json` (e.g. `de.json` for German)
2. Translate all values (keys stay in English)
3. Add the locale to `config/app.php` (`available_locales`) and `AppServiceProvider::availableLocales()`
4. Submit a pull request

## File format

```json
{
    "Dashboard": "Tableau de bord",
    "Settings": "Paramètres",
    ...
}
```

Keys are English strings used in Blade views via `{{ __('key') }}`.
Values are translations. If a key is missing, Laravel falls back to the key itself (English).

## Available languages

- `en.json` — English (default)
- `pl.json` — Polski (Polish)

- `zh.json` — 简体中文 (Simplified Chinese)

The initial Simplified Chinese catalog translates 6397 of 6902 current English keys. Missing keys fall back to English; they are intentionally omitted rather than presented as translated. The application default remains English. Select 简体中文 in the language switcher to use it.
