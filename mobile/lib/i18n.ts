import * as Localization from 'expo-localization';
import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';

import en from '@/lang/en.json';
import pl from '@/lang/pl.json';
import de from '@/lang/de.json';
import tr from '@/lang/tr.json';

export type AppLocale = 'en' | 'pl' | 'de' | 'tr';

export const SUPPORTED_LOCALES: AppLocale[] = ['en', 'pl', 'de', 'tr'];

export function detectDeviceLocale(): AppLocale {
  const locales = Localization.getLocales();
  const tag = locales[0]?.languageCode?.toLowerCase();
  return SUPPORTED_LOCALES.includes(tag as AppLocale) ? (tag as AppLocale) : 'en';
}

i18n
  .use(initReactI18next)
  .init({
    resources: {
      en: { translation: en },
      pl: { translation: pl },
      de: { translation: de },
      tr: { translation: tr },
    },
    lng: detectDeviceLocale(),
    fallbackLng: 'en',
    // Backend uses phrase-as-key (Laravel __()). Disable separators so keys
    // containing ':' or '.' aren't treated as nested paths.
    keySeparator: false,
    nsSeparator: false,
    interpolation: { escapeValue: false },
    returnNull: false,
  });

export default i18n;
