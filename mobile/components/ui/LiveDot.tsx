// Light-only v1: Colors[scheme] switching dropped — `dark` is accepted but ignored.
import { StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { OnlineDot, colors, fonts } from '@openmes/ui';

import { useEchoConnectionState } from '@/hooks/useEchoConnectionState';

/**
 * Tiny live/polling indicator. Renders a green dot + "LIVE" when the
 * WebSocket is connected (delegated to the design system's OnlineDot),
 * amber + "POLLING" otherwise (operator's queries still refetch on the
 * existing 30s interval — they just don't get instant push updates).
 *
 * Drop it into any screen header that runs realtime hooks; it adds no
 * other behavior, just visual reassurance.
 */
export function LiveDot(_props: { dark?: boolean }) {
  const { t } = useTranslation();
  const state = useEchoConnectionState();
  const live = state === 'connected';

  if (live) {
    return <OnlineDot label={t('LIVE').toUpperCase()} pulse />;
  }
  return (
    <View accessibilityRole="text" style={styles.row}>
      <View style={styles.dot} />
      <Text style={styles.label}>{t('POLLING').toUpperCase()}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'center', gap: 5, alignSelf: 'flex-start' },
  dot: { width: 7, height: 7, borderRadius: 3.5, backgroundColor: colors.downtime },
  label: { fontFamily: fonts.mono.native.regular, fontSize: 10, color: colors.downtime },
});
