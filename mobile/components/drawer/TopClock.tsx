/**
 * Top-right live clock — twin of the web AppLayout's DesktopClock (a strip
 * above <main> on every page): locale-formatted date + a mono HH:MM:SS time,
 * timezone pinned to Europe/Warsaw like the original Blade clock. Ticks once
 * per second; isolated so the tick never re-renders the screens below it.
 * Rendered only in the tablet (permanent-sidebar) layout, like the web's
 * `hidden lg:flex`.
 */
import { useEffect, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts } from '@openmes/ui';

function fmt(locale: string) {
  const now = new Date();
  const tz = { timeZone: 'Europe/Warsaw' } as const;
  return {
    date: now.toLocaleDateString(locale || 'en', { ...tz, weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }),
    time: now.toLocaleTimeString(locale || 'en', { ...tz, hour: '2-digit', minute: '2-digit', second: '2-digit' }),
  };
}

export function TopClock() {
  const { i18n } = useTranslation();
  const [t, setT] = useState(() => fmt(i18n.language));
  useEffect(() => {
    const id = setInterval(() => setT(fmt(i18n.language)), 1000);
    return () => clearInterval(id);
  }, [i18n.language]);

  return (
    <View style={styles.strip}>
      <Text style={styles.icon}>🕒</Text>
      <Text style={styles.date}>{t.date}</Text>
      <Text style={styles.time}>{t.time}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  strip: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'flex-end',
    gap: 6,
    paddingHorizontal: 16,
    paddingVertical: 6,
    backgroundColor: colors.bg,
  },
  icon: { fontSize: 11, color: colors.faint },
  date: { fontSize: 12.5, color: colors.muted, fontFamily: fonts.sans.native.regular },
  time: { fontSize: 12.5, color: colors.ink, fontFamily: fonts.mono.native.medium },
});
