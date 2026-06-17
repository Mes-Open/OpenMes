# Translation files

`en.json` and `pl.json` mirror `OpenMes/backend/lang/{en,pl}.json` so the mobile
app and the web backend share a single translation catalog.

**Format:** flat phrase-as-key JSON (Laravel `__()` convention). The English
phrase is the key; the value is the localized string. `en.json` has identical
keys and values — calling `t('Save')` with `lng: 'en'` returns `'Save'`.

## When backend translations change

Copy them over verbatim:

```sh
cp ../OpenMes/backend/lang/en.json lang/en.json
cp ../OpenMes/backend/lang/pl.json lang/pl.json
```

Do **not** edit these files directly — any change drifts the two codebases
apart. Add new strings to the backend first (so web translates them too) then
re-sync.

## Adding a new language

1. Drop the new file (e.g. `de.json`) into `backend/lang/` first.
2. Copy it here.
3. Add the locale to the `resources` map in `lib/i18n.ts`.
4. Add the option to the language selector in `screens/login.tsx`.
