// Light-only v1: the TONE_DARK branch is dropped — Geist White tokens drive the tones.
import { StyleSheet, Text, View } from 'react-native';
import { FontAwesome } from '@expo/vector-icons';

import { colors, fonts, radius } from '@openmes/ui';

import { Mono } from '@/components/ui/Mono';

export type Tone = 'amber' | 'green' | 'red' | 'purple' | 'blue' | 'neutral';

interface ToneStyle {
  /** Soft tint background — only used when `emphasize` is true. */
  softBg: string;
  softBorder: string;
  /** Big number color. */
  value: string;
  /** Trend pill color. */
  trend: string;
}

// Semantic tone colors mapped onto the design system's status pairs. Amber
// (downtime) keeps a neutral-ink value so the tinted background is the accent,
// not the number. Purple/blue have no token equivalent — kept as fixed hexes.
const TONE: Record<Tone, ToneStyle> = {
  amber:   { softBg: colors.downtimeBg, softBorder: `${colors.downtime}40`, value: colors.ink, trend: colors.running },
  green:   { softBg: colors.runningBg, softBorder: `${colors.running}40`, value: colors.running, trend: colors.running },
  red:     { softBg: colors.blockedBg, softBorder: `${colors.blocked}40`, value: colors.blocked, trend: colors.running },
  purple:  { softBg: '#ede7f9', softBorder: '#d6c8f0', value: '#7c3aed', trend: '#7c3aed' },
  blue:    { softBg: '#e2ecfa', softBorder: '#c5d6ef', value: '#1d4ed8', trend: '#1d4ed8' },
  neutral: { softBg: colors.chip, softBorder: colors.line, value: colors.ink, trend: colors.muted },
};

interface Props {
  label: string;
  value: number | string;
  /** Small text under the value (e.g. "14 in progress"). */
  hint?: string;
  /** Trend pill in the top-right (e.g. "+6", "75%"). */
  trend?: string;
  trendDirection?: 'up' | 'down' | 'flat';
  tone?: Tone;
  /**
   * When true, fills the tile with the tone's soft tint background to draw
   * attention (used for Open Issues when count > 0). When false (default),
   * the tile uses the standard white surface like the other KPI cards.
   */
  emphasize?: boolean;
}

export function StatTile({
  label,
  value,
  hint,
  trend,
  trendDirection,
  tone = 'amber',
  emphasize = false,
}: Props) {
  const t = TONE[tone];
  const bg = emphasize ? t.softBg : colors.card;
  const border = emphasize ? t.softBorder : colors.line2;

  return (
    <View style={[styles.tile, { backgroundColor: bg, borderColor: border }]}>
      <View style={styles.topRow}>
        <Mono size={10} color={colors.faint} letterSpacing={1.2} weight="400">
          {label.toUpperCase()}
        </Mono>
        {trend ? (
          <View style={styles.trendRow}>
            {trendDirection === 'up' ? (
              <FontAwesome name="long-arrow-up" size={9} color={t.trend} />
            ) : trendDirection === 'down' ? (
              <FontAwesome name="long-arrow-down" size={9} color={t.trend} />
            ) : null}
            <Mono size={11} color={t.trend} weight="600">
              {trend}
            </Mono>
          </View>
        ) : null}
      </View>
      <Text style={[styles.value, { color: t.value }]}>{value}</Text>
      {hint ? (
        <Mono size={11} color={colors.muted}>
          {hint}
        </Mono>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  tile: {
    flexBasis: '48%',
    flexGrow: 1,
    minHeight: 112,
    padding: 14,
    borderRadius: radius.md,
    borderWidth: 1,
    gap: 10,
    overflow: 'hidden',
  },
  topRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  trendRow: { flexDirection: 'row', alignItems: 'center', gap: 3 },
  value: {
    fontSize: 36,
    fontFamily: fonts.mono.native.medium,
    letterSpacing: -0.6,
    lineHeight: 36,
  },
});
