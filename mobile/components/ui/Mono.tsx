import { StyleSheet, Text, View, type TextProps, type TextStyle } from 'react-native';
import { useTranslation } from 'react-i18next';

import Colors, {
  MONO,
  SANS_BOLD,
  SANS_MEDIUM,
  SANS_REGULAR,
  SANS_SEMIBOLD,
} from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';

interface Props extends TextProps {
  size?: number;
  color?: string;
  weight?: TextStyle['fontWeight'];
  upper?: boolean;
  letterSpacing?: number;
}

export function Mono({ size = 12, color, weight = '500', upper, letterSpacing, style, children, ...rest }: Props) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  return (
    <Text
      {...rest}
      style={[
        {
          fontFamily: MONO,
          fontSize: size,
          color: color ?? palette.textMuted,
          fontWeight: weight,
          letterSpacing: letterSpacing ?? 0.5,
        },
        upper ? styles.upper : null,
        style,
      ]}>
      {upper && typeof children === 'string' ? children.toUpperCase() : children}
    </Text>
  );
}

interface SectionLabelProps {
  children: string;
  right?: React.ReactNode;
}

export function SectionLabel({ children, right }: SectionLabelProps) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  // Auto-translate so call sites can pass English keys directly.
  const { t } = useTranslation();
  return (
    <View style={styles.row}>
      <Text style={[styles.label, { color: palette.textFaint, fontFamily: MONO }]}>
        {t(children).toUpperCase()}
      </Text>
      {right}
    </View>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Sans — Geist face for titles + body. Mirrors Mono's API so call sites just
// swap the component name. Mono is reserved for IDs / codes / timestamps /
// KPI numbers — anything that reads as data. Sans handles everything else.
// ─────────────────────────────────────────────────────────────────────────────

interface SansProps extends TextProps {
  size?: number;
  color?: string;
  weight?: TextStyle['fontWeight'];
  /** Tracks the line-height to match the chosen size. */
  lineHeight?: number;
  letterSpacing?: number;
}

const SANS_FACE: Record<string, string> = {
  '400': SANS_REGULAR,
  '500': SANS_MEDIUM,
  '600': SANS_SEMIBOLD,
  '700': SANS_BOLD,
};

function sansFontFamily(weight: TextStyle['fontWeight']): string {
  if (typeof weight === 'string' && SANS_FACE[weight]) return SANS_FACE[weight];
  if (typeof weight === 'number') return SANS_FACE[String(weight)] ?? SANS_REGULAR;
  return SANS_REGULAR;
}

export function Sans({
  size = 14,
  color,
  weight = '500',
  lineHeight,
  letterSpacing,
  style,
  children,
  ...rest
}: SansProps) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  return (
    <Text
      {...rest}
      style={[
        {
          // Use the loaded TTF variant for the requested weight — RN-Web
          // otherwise falls back to a system font for any weight that isn't
          // the loaded default, which was the root cause of the mono-looking
          // titles before this change.
          fontFamily: sansFontFamily(weight),
          fontSize: size,
          color: color ?? palette.text,
          fontWeight: weight,
          letterSpacing: letterSpacing ?? -0.1,
          lineHeight: lineHeight ?? Math.round(size * 1.25),
        },
        style,
      ]}>
      {children}
    </Text>
  );
}

const styles = StyleSheet.create({
  upper: { textTransform: 'uppercase' },
  row: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 },
  label: { fontSize: 11, fontWeight: '600', letterSpacing: 0.8 },
});
