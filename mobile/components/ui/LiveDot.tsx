import { StyleSheet, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Mono } from '@/components/ui/Mono';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useEchoConnectionState } from '@/hooks/useEchoConnectionState';

/**
 * Tiny live/polling indicator. Renders a green dot + "LIVE" when the
 * WebSocket is connected, amber + "POLLING" otherwise (operator's queries
 * still refetch on the existing 30s interval — they just don't get instant
 * push updates).
 *
 * Drop it into any screen header that runs realtime hooks; it adds no
 * other behavior, just visual reassurance.
 */
export function LiveDot({ dark }: { dark?: boolean }) {
  const scheme = useColorScheme() ?? 'light';
  const palette = dark ? Colors.dark : Colors[scheme];
  const { t } = useTranslation();
  const state = useEchoConnectionState();
  const live = state === 'connected';
  const color = live ? palette.success : BRAND.amber;

  return (
    <View style={styles.row}>
      <View style={[styles.dot, { backgroundColor: color }]} />
      <Mono size={10} color={color} weight="700" letterSpacing={0.6}>
        {live ? t('LIVE').toUpperCase() : t('POLLING').toUpperCase()}
      </Mono>
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'center', gap: 5 },
  dot: { width: 6, height: 6, borderRadius: 3 },
});
