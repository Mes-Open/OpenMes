// Light-only v1: no scheme handling — downtime tokens, fixed.
import { StyleSheet, Text, View } from 'react-native';

import { colors, fonts } from '@openmes/ui';

import { Mono } from '@/components/ui/Mono';

interface Props {
  /** Number of mutations queued while offline. */
  queued?: number;
  /** When false, the banner doesn't render at all. */
  visible?: boolean;
}

/**
 * Top downtime-amber banner shown when the device is offline. The mobile app
 * doesn't currently maintain an offline action queue (no AsyncStorage / MMKV
 * backed mutation buffer), so this is rendered conditionally by the screens
 * that surface offline state — see operator's Today / Active step.
 *
 * TODO(offline): wire to a real network-state hook (NetInfo) and a queued
 * mutation store. Until then the screens decide when to mount this.
 */
export function OfflineBanner({ queued, visible = true }: Props) {
  if (!visible) return null;
  return (
    <View style={styles.bar}>
      <View style={styles.dot} />
      <Mono size={11} color={colors.downtime} weight="600" letterSpacing={0.6} style={{ flex: 1 }}>
        {queued && queued > 0
          ? `OFFLINE · ${queued} ACTION${queued === 1 ? '' : 'S'} QUEUED`
          : 'OFFLINE'}
      </Mono>
      <Text style={styles.cta}>WILL SYNC</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  bar: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 8,
    paddingHorizontal: 14,
    backgroundColor: colors.downtimeBg,
  },
  dot: { width: 8, height: 8, borderRadius: 4, backgroundColor: colors.downtime },
  cta: {
    color: colors.downtime,
    fontFamily: fonts.mono.native.semibold,
    fontSize: 10,
    letterSpacing: 0.4,
  },
});
