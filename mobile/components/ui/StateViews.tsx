import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import { Button } from './Button';
import Colors, { MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';

export function LoadingState({ label }: { label?: string }) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  return (
    <View style={styles.center}>
      <ActivityIndicator color={palette.tint} />
      {label ? (
        <Text style={[styles.mono, { color: palette.textFaint }]}>{label.toUpperCase()}</Text>
      ) : null}
    </View>
  );
}

export function ErrorState({ error, onRetry }: { error: unknown; onRetry?: () => void }) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const message = error instanceof Error ? error.message : String(error ?? 'Unknown error');
  return (
    <View style={styles.center}>
      <View style={[styles.iconBadge, { backgroundColor: palette.dangerSoft }]}>
        <Text style={{ color: palette.danger, fontSize: 22, fontWeight: '700' }}>!</Text>
      </View>
      <Text style={[styles.title, { color: palette.text }]}>Something went wrong</Text>
      <Text style={[styles.text, { color: palette.textMuted }]}>{message}</Text>
      {onRetry ? (
        <Button title="Retry" onPress={onRetry} variant="outline" style={{ marginTop: 14 }} />
      ) : null}
    </View>
  );
}

export function EmptyState({ title, subtitle }: { title: string; subtitle?: string }) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  return (
    <View style={styles.center}>
      <View style={[styles.iconBadge, { backgroundColor: palette.surfaceAlt }]}>
        <Text style={{ color: palette.textFaint, fontSize: 22 }}>—</Text>
      </View>
      <Text style={[styles.title, { color: palette.text }]}>{title}</Text>
      {subtitle ? <Text style={[styles.text, { color: palette.textMuted }]}>{subtitle}</Text> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 28, gap: 6 },
  iconBadge: {
    width: 56,
    height: 56,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 8,
  },
  title: { fontSize: 16, fontWeight: '700', letterSpacing: -0.2 },
  text: { fontSize: 13, textAlign: 'center', marginTop: 2 },
  mono: { fontSize: 11, fontFamily: MONO, letterSpacing: 0.6, marginTop: 4 },
});
