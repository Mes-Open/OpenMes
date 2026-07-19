// Light-only v1: Colors[scheme] switching dropped — Geist White tokens.
import * as WebBrowser from 'expo-web-browser';
import { StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Button, colors, fonts, radius } from '@openmes/ui';

import { Mono } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { useSettingsStore } from '@/stores/settingsStore';

interface Props {
  /** What the screen is meant to show — used as the header title and card title. */
  title: string;
  /** The backend endpoint this screen would call, e.g. "GET /api/v1/analytics/cycle-time". */
  endpoint?: string;
  /** Optional one-line subtitle in the screen header. */
  subtitle?: string;
  /** Optional explainer beneath the MISSING pill. */
  note?: string;
}

/**
 * Stub used wherever a backend capability exists but no UI has been built yet.
 * Shows a single clean card so designers/QA can immediately tell what's
 * missing without confusing it with a real (empty) screen. The endpoint link
 * opens the Laravel API docs at that path when one is set in settings.
 */
export function MissingScreen({ title, endpoint, subtitle, note }: Props) {
  const { t } = useTranslation();
  const serverUrl = useSettingsStore((s) => s.serverUrl);

  const openApiDocs = () => {
    if (!serverUrl) return;
    WebBrowser.openBrowserAsync(`${serverUrl}/docs/api`).catch(() => undefined);
  };

  return (
    <View style={styles.screen}>
      <ScreenHeader title={title} subtitle={subtitle ?? 'NOT IMPLEMENTED'} />
      <View style={styles.body}>
        <View style={styles.card}>
          <Text style={styles.title}>{t(title)}</Text>
          {endpoint ? (
            <Mono size={11} color={colors.muted} style={{ marginTop: 4 }}>
              {endpoint}
            </Mono>
          ) : null}

          <View style={styles.pillRow}>
            <View style={styles.pillSquare} />
            <Mono size={10} color={colors.faint} letterSpacing={1.2} weight="600">
              {t('Missing').toUpperCase()}
            </Mono>
          </View>

          <Text style={styles.note}>
            {note ?? t('This screen has not been built yet. The underlying endpoint is available — wire up the UI when ready.')}
          </Text>

          {endpoint && serverUrl ? (
            <Button variant="accent" onPress={openApiDocs} style={styles.linkBtn}>
              {t('Open API in browser')} →
            </Button>
          ) : null}
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  body: { flex: 1, padding: 18 },
  card: {
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.line2,
    backgroundColor: colors.card,
    padding: 20,
    gap: 4,
  },
  title: {
    fontSize: 18,
    fontFamily: fonts.sans.native.semibold,
    letterSpacing: -0.2,
    color: colors.ink,
  },
  pillRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginTop: 16,
    marginBottom: 8,
  },
  pillSquare: { width: 12, height: 12, borderWidth: 1, borderRadius: 2, borderColor: colors.faintest },
  note: {
    fontSize: 13,
    lineHeight: 20,
    color: colors.muted,
    fontFamily: fonts.sans.native.regular,
  },
  linkBtn: { marginTop: 18, alignSelf: 'flex-start' },
});
