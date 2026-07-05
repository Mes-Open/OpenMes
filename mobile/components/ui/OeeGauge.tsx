/**
 * Semicircle OEE gauge with red/yellow/green zones and a needle — a faithful
 * react-native-svg port of the web `components/OeeGauge.jsx` (itself the React
 * port of `<x-oee-gauge>`). Points lie on a unit semicircle centered at (50,50),
 * r=40: p=0 → (10,50), p=50 → (50,10), p=100 → (90,50).
 */
import { StyleSheet, Text, View } from 'react-native';
import Svg, { Circle, Line, Path } from 'react-native-svg';

import { colors, fonts } from '@openmes/ui';

// OEE banding — mirrors backend/app/Support/OeeBand.php (red < 65 ≤ yellow < 85 ≤ green).
export const OEE_RED_BELOW = 65;
export const OEE_GREEN_AT_LEAST = 85;

export function oeeColor(value: number | null | undefined): 'green' | 'yellow' | 'red' | 'gray' {
  if (value == null) return 'gray';
  if (value >= OEE_GREEN_AT_LEAST) return 'green';
  if (value >= OEE_RED_BELOW) return 'yellow';
  return 'red';
}

const TEXT_COLOR = {
  green: colors.running,
  yellow: colors.downtime,
  red: colors.blocked,
  gray: colors.faint,
} as const;

interface Props {
  value: number | null | undefined;
  size?: number;
}

export function OeeGauge({ value, size = 104 }: Props) {
  const hasValue = value != null;
  const p = hasValue ? Math.max(0, Math.min(100, Number(value))) : 0;

  const pointAt = (q: number, r = 40): [number, number] => {
    const a = (q / 100) * Math.PI;
    return [50 - r * Math.cos(a), 50 - r * Math.sin(a)];
  };
  const [rEndX, rEndY] = pointAt(OEE_RED_BELOW);
  const [yEndX, yEndY] = pointAt(OEE_GREEN_AT_LEAST);
  const [gEndX, gEndY] = pointAt(100);
  const [needleX, needleY] = pointAt(p, 35);

  return (
    <View style={{ width: size, alignItems: 'center' }}>
      <Svg width={size} height={size * 0.6} viewBox="0 0 100 60">
        {/* Track */}
        <Path d="M 10 50 A 40 40 0 0 1 90 50" fill="none" stroke={colors.line2} strokeWidth={10} />
        {/* Bands */}
        <Path d={`M 10 50 A 40 40 0 0 1 ${rEndX} ${rEndY}`} fill="none" stroke="#D6442F" strokeWidth={10} />
        <Path d={`M ${rEndX} ${rEndY} A 40 40 0 0 1 ${yEndX} ${yEndY}`} fill="none" stroke="#C9821E" strokeWidth={10} />
        <Path d={`M ${yEndX} ${yEndY} A 40 40 0 0 1 ${gEndX} ${gEndY}`} fill="none" stroke="#1C9A55" strokeWidth={10} />
        {/* Needle + hub */}
        {hasValue ? (
          <>
            <Line x1={50} y1={50} x2={needleX} y2={needleY} stroke={colors.ink} strokeWidth={1.6} strokeLinecap="round" />
            <Circle cx={50} cy={50} r={2.2} fill={colors.ink} />
          </>
        ) : (
          <Circle cx={50} cy={50} r={2.2} fill={colors.faintest} />
        )}
      </Svg>
      <View style={[styles.labels, { marginTop: -size * 0.04 }]}>
        <Text style={{ fontFamily: fonts.mono.native.medium, fontSize: size * 0.18, color: TEXT_COLOR[oeeColor(hasValue ? p : null)] }}>
          {hasValue ? `${p.toFixed(1)}%` : 'N/A'}
        </Text>
        <Text style={{ fontFamily: fonts.mono.native.regular, fontSize: size * 0.085, color: colors.faint, letterSpacing: 0.6 }}>
          OEE
        </Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  labels: { alignItems: 'center' },
});
