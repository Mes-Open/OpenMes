import { FontAwesome } from '@expo/vector-icons';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { Mono } from '@/components/ui/Mono';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';

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
   * Defaults to following the app scheme.
   */
  dark?: boolean;
}

export function Banner({ tone, title, detail, onPress, cta, dark }: Props) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  const t = TONE[tone];
  // Solid tone for danger/warning so the alert is unmissable; soft tone for
  // info/success so it doesn't shout.
  const solid = tone === 'danger' || tone === 'warning';
  const bg = solid ? t.bg : dark ? '#1f1f24' : palette.surface;
  const border = solid ? t.bg : dark ? '#3a3a44' : palette.border;
  const fg = solid ? '#fff' : t.fg;
  const detailFg = solid ? 'rgba(255,255,255,0.85)' : palette.textMuted;

  const inner = (
    <View style={[styles.bar, { backgroundColor: bg, borderColor: border }]}>
      <View style={[styles.iconWrap, solid ? null : { backgroundColor: t.bg }]}>
        <FontAwesome name={t.icon} size={16} color={solid ? '#fff' : t.fg} />
      </View>
      <View style={{ flex: 1, minWidth: 0 }}>
        <Text style={[styles.title, { color: fg }]} numberOfLines={1}>
          {title}
        </Text>
        {detail ? (
          <Mono size={11} color={detailFg} style={{ marginTop: 3 }}>
            {detail}
          </Mono>
        ) : null}
      </View>
      {cta ? (
        <Mono
          size={10}
          color={solid ? '#fff' : t.fg}
          weight="700"
          letterSpacing={0.6}
          style={{ marginLeft: 8 }}>
          {cta.toUpperCase()}
        </Mono>
      ) : null}
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

const TONE: Record<
  BannerTone,
  { bg: string; fg: string; icon: React.ComponentProps<typeof FontAwesome>['name'] }
> = {
  danger: { bg: '#dc2626', fg: '#b91c1c', icon: 'exclamation-triangle' },
  warning: { bg: '#f5a524', fg: '#8a5a0e', icon: 'exclamation' },
  info: { bg: '#3a6ed6', fg: '#1d4ed8', icon: 'info-circle' },
  success: { bg: '#1f9d6c', fg: '#0f7a4f', icon: 'check' },
};

const styles = StyleSheet.create({
  bar: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 12,
    borderRadius: 12,
    borderWidth: 1,
  },
  iconWrap: {
    width: 32,
    height: 32,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  title: { fontSize: 14, fontWeight: '600', letterSpacing: -0.2 },
});
