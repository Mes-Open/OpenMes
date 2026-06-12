// Light-only v1: Colors[scheme] + dark-surface branches dropped — `dark` is accepted but ignored.
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { colors, fonts, radius } from '@openmes/ui';

export type BannerTone = 'danger' | 'warning' | 'info' | 'success';

interface Props {
  tone: BannerTone;
  /** Short title (e.g. "2 events overdue"). */
  title: string;
  /** Mono-uppercase detail line (e.g. "ROBOT-A2 actuator · CONV-03 lub"). */
  detail?: string;
  /** Optional tap action — adds "OPEN" call-to-action label on the right. */
  onPress?: () => void;
  cta?: string;
  /**
   * Force dark-surface styling (used inside the dark Connectivity hub).
   * Light-only v1: accepted for API compatibility, ignored.
   */
  dark?: boolean;
}

/** Tone → InlineAlert severity colors (Geist White §08 idiom: tinted bg + dot). */
const TONE: Record<BannerTone, { bg: string; fg: string }> = {
  danger: { bg: colors.blockedBg, fg: colors.blocked },
  warning: { bg: colors.downtimeBg, fg: colors.downtime },
  info: { bg: colors.chip, fg: colors.accent },
  success: { bg: colors.runningBg, fg: colors.running },
};

export function Banner({ tone, title, detail, onPress, cta }: Props) {
  const t = TONE[tone];

  const inner = (
    <View accessibilityRole="alert" style={[styles.bar, { backgroundColor: t.bg }]}>
      <View style={[styles.dot, { backgroundColor: t.fg }]} />
      <View style={{ flex: 1, minWidth: 0 }}>
        <Text style={styles.title} numberOfLines={1}>
          {title}
        </Text>
        {detail ? (
          <Text style={styles.detail} numberOfLines={1}>
            {detail}
          </Text>
        ) : null}
      </View>
      {cta ? <Text style={[styles.cta, { color: t.fg }]}>{cta.toUpperCase()}</Text> : null}
    </View>
  );

  if (onPress) {
    return (
      <Pressable onPress={onPress} style={({ pressed }) => ({ opacity: pressed ? 0.85 : 1 })}>
        {inner}
      </Pressable>
    );
  }
  return inner;
}

const styles = StyleSheet.create({
  bar: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 11,
    paddingVertical: 13,
    paddingHorizontal: 15,
    borderRadius: radius.md,
  },
  dot: { width: 9, height: 9, borderRadius: 4.5, flexShrink: 0 },
  title: { fontSize: 13, fontFamily: fonts.sans.native.semibold, color: colors.ink },
  detail: {
    marginTop: 3,
    fontFamily: fonts.mono.native.regular,
    fontSize: 10,
    letterSpacing: 0.6,
    color: colors.muted,
  },
  cta: {
    marginLeft: 8,
    fontFamily: fonts.mono.native.semibold,
    fontSize: 10,
    letterSpacing: 0.8,
    textTransform: 'uppercase',
  },
});
