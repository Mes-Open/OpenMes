// Light-only v1: Colors[scheme] switching dropped — Geist White tokens.
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Button, colors, fonts } from '@openmes/ui';

export function LoadingState({ label }: { label?: string }) {
  return (
    <View style={styles.center}>
      <ActivityIndicator color={colors.accent} />
      {label ? <Text style={styles.mono}>{label.toUpperCase()}</Text> : null}
    </View>
  );
}

export function ErrorState({ error, onRetry }: { error: unknown; onRetry?: () => void }) {
  // Auto-translate the retry label — same English-as-key convention as Button.
  const { t } = useTranslation();
  const message = error instanceof Error ? error.message : String(error ?? t('Unknown error'));
  return (
    <View style={styles.center}>
      <View style={[styles.iconBadge, { backgroundColor: colors.blockedBg }]}>
        <Text style={{ color: colors.blocked, fontSize: 22, fontFamily: fonts.sans.native.bold }}>!</Text>
      </View>
      <Text style={styles.title}>{t('Something went wrong')}</Text>
      <Text style={styles.text}>{message}</Text>
      {onRetry ? (
        <Button variant="accent" onPress={onRetry} style={{ marginTop: 14 }}>
          {t('Retry')}
        </Button>
      ) : null}
    </View>
  );
}

export function EmptyState({ title, subtitle }: { title: string; subtitle?: string }) {
  return (
    <View style={styles.center}>
      <View style={[styles.iconBadge, { backgroundColor: colors.chip }]}>
        <Text style={{ color: colors.faint, fontSize: 22 }}>—</Text>
      </View>
      <Text style={styles.title}>{title}</Text>
      {subtitle ? <Text style={styles.text}>{subtitle}</Text> : null}
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
  title: { fontSize: 16, fontFamily: fonts.sans.native.semibold, letterSpacing: -0.2, color: colors.ink },
  text: { fontSize: 13, textAlign: 'center', marginTop: 2, color: colors.muted, fontFamily: fonts.sans.native.regular },
  mono: {
    fontSize: 10,
    fontFamily: fonts.mono.native.regular,
    letterSpacing: 1.2,
    textTransform: 'uppercase',
    color: colors.faint,
    marginTop: 4,
  },
});
