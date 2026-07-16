// Light-only v1: no scheme handling — downtime tokens, fixed.
import { StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

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
  const { t } = useTranslation();
  if (!visible) return null;
  return (
    <View style={styles.bar}>
      <View style={styles.dot} />
      <Mono size={11} color={colors.downtime} weight="600" letterSpacing={0.6} style={{ flex: 1 }}>
        {queued && queued > 0
          ? `${t('Offline').toUpperCase()} · ${queued} ${(queued === 1 ? t('action queued') : t('actions queued')).toUpperCase()}`
          : t('Offline').toUpperCase()}
      </Mono>
      <Text style={styles.cta}>{t('Will sync').toUpperCase()}</Text>
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
