import { StyleSheet, Text, View } from 'react-native';
import { FontAwesome } from '@expo/vector-icons';

import { Mono } from '@/components/ui/Mono';
import Colors, { MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';

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

// Light scheme — semantic colors for value + trend, soft tint for emphasis.
// Amber tile uses neutral-dark for the value to match the design (the colored
// background is the accent, not the number).
const TONE_LIGHT: Record<Tone, ToneStyle> = {
  amber:   { softBg: '#fbe9c8', softBorder: '#f5d28a', value: '#171715', trend: '#1f9d6c' },
  green:   { softBg: '#dff5e9', softBorder: '#bce6cf', value: '#1f9d6c', trend: '#1f9d6c' },
  red:     { softBg: '#fbe2e2', softBorder: '#f5c2c2', value: '#dc2626', trend: '#1f9d6c' },
  purple:  { softBg: '#ede7f9', softBorder: '#d6c8f0', value: '#7c3aed', trend: '#7c3aed' },
  blue:    { softBg: '#e2ecfa', softBorder: '#c5d6ef', value: '#1d4ed8', trend: '#1d4ed8' },
  neutral: { softBg: '#ebe8e0', softBorder: '#d9d5cb', value: '#171715', trend: '#5f5d56' },
};

// Dark scheme equivalents.
const TONE_DARK: Record<Tone, ToneStyle> = {
  amber:   { softBg: '#241a08', softBorder: '#3a2a0c', value: '#eaeaea', trend: '#3ecf8e' },
  green:   { softBg: '#0e3424', softBorder: '#13452f', value: '#3ecf8e', trend: '#3ecf8e' },
  red:     { softBg: '#3a0e0e', softBorder: '#4a1414', value: '#ef4444', trend: '#3ecf8e' },
  purple:  { softBg: '#1f1830', softBorder: '#2a2243', value: '#a78bfa', trend: '#a78bfa' },
  blue:    { softBg: '#0e1a3a', softBorder: '#16244a', value: '#5b8def', trend: '#5b8def' },
  neutral: { softBg: '#1d1d22', softBorder: '#26262d', value: '#eaeaea', trend: '#9a9aa2' },
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
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const t = (scheme === 'dark' ? TONE_DARK : TONE_LIGHT)[tone];
  const bg = emphasize ? t.softBg : palette.surface;
  const border = emphasize ? t.softBorder : palette.border;
  const labelColor = palette.textMuted;
  const hintColor = palette.textMuted;

  return (
    <View style={[styles.tile, { backgroundColor: bg, borderColor: border }]}>
      <View style={styles.topRow}>
        <Mono size={10} color={labelColor} letterSpacing={0.8} weight="700">
          {label.toUpperCase()}
        </Mono>
        {trend ? (
          <View style={styles.trendRow}>
            {trendDirection === 'up' ? (
              <FontAwesome name="long-arrow-up" size={9} color={t.trend} />
            ) : trendDirection === 'down' ? (
              <FontAwesome name="long-arrow-down" size={9} color={t.trend} />
            ) : null}
            <Mono size={11} color={t.trend} weight="700">
              {trend}
            </Mono>
          </View>
        ) : null}
      </View>
      <Text style={[styles.value, { color: t.value, fontFamily: MONO }]}>{value}</Text>
      {hint ? (
        <Mono size={11} color={hintColor}>
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
    borderRadius: 14,
    borderWidth: 1,
    gap: 10,
    overflow: 'hidden',
  },
  topRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  trendRow: { flexDirection: 'row', alignItems: 'center', gap: 3 },
  value: { fontSize: 36, fontWeight: '600', letterSpacing: -0.6, lineHeight: 36 },
});
