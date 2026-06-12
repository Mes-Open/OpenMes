import * as WebBrowser from 'expo-web-browser';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { Mono } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import Colors, { MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
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
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const serverUrl = useSettingsStore((s) => s.serverUrl);

  const openApiDocs = () => {
    if (!serverUrl) return;
    WebBrowser.openBrowserAsync(`${serverUrl}/docs/api`).catch(() => undefined);
  };

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader title={title} subtitle={subtitle ?? 'NOT IMPLEMENTED'} />
      <View style={styles.body}>
        <View style={[styles.card, { backgroundColor: palette.surface, borderColor: palette.border }]}>
          <Text style={[styles.title, { color: palette.text }]}>{title}</Text>
          {endpoint ? (
            <Mono size={11} color={palette.textMuted} style={{ marginTop: 4 }}>
              {endpoint}
            </Mono>
          ) : null}

          <View style={styles.pillRow}>
            <View style={[styles.pillSquare, { borderColor: palette.border }]} />
            <Mono size={10.5} color={palette.textFaint} letterSpacing={0.8} weight="700">
              MISSING
            </Mono>
          </View>

          <Text style={[styles.note, { color: palette.textMuted }]}>
            {note ?? 'This screen has not been built yet. The underlying endpoint is available — wire up the UI when ready.'}
          </Text>

          {endpoint && serverUrl ? (
            <Pressable
              onPress={openApiDocs}
              style={({ pressed }) => [
                styles.linkBtn,
                { borderColor: palette.border, opacity: pressed ? 0.6 : 1 },
              ]}>
              <Mono size={11} color={palette.text} weight="700" letterSpacing={0.5}>
                OPEN API IN BROWSER →
              </Mono>
            </Pressable>
          ) : null}
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  body: { flex: 1, padding: 18 },
  card: {
    borderRadius: 14,
    borderWidth: 1,
    padding: 20,
    gap: 4,
  },
  title: { fontSize: 18, fontWeight: '700', letterSpacing: -0.2 },
  pillRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginTop: 16,
    marginBottom: 8,
  },
  pillSquare: { width: 12, height: 12, borderWidth: 1, borderRadius: 2 },
  note: { fontSize: 13, lineHeight: 20, fontFamily: MONO, opacity: 0.85 },
  linkBtn: {
    marginTop: 18,
    alignSelf: 'flex-start',
    paddingVertical: 8,
    paddingHorizontal: 12,
    borderRadius: 8,
    borderWidth: 1,
  },
});
