// Light-only v1: Colors[scheme] defaults dropped — Geist White tokens.
import { StyleSheet, Text, View, type TextProps, type TextStyle } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts } from '@openmes/ui';

interface Props extends TextProps {
  size?: number;
  color?: string;
  weight?: TextStyle['fontWeight'];
  upper?: boolean;
  letterSpacing?: number;
}

// Only weights 400 / 500 / 600 ship for Geist Mono — bold maps to semibold.
const MONO_FACE: Record<string, string> = {
  '400': fonts.mono.native.regular,
  '500': fonts.mono.native.medium,
  '600': fonts.mono.native.semibold,
  '700': fonts.mono.native.semibold,
};

function monoFontFamily(weight: TextStyle['fontWeight']): string {
  if (typeof weight === 'string' && MONO_FACE[weight]) return MONO_FACE[weight];
  if (typeof weight === 'number') return MONO_FACE[String(weight)] ?? fonts.mono.native.medium;
  return fonts.mono.native.medium;
}

export function Mono({ size = 12, color, weight = '500', upper, letterSpacing, style, children, ...rest }: Props) {
  return (
    <Text
      {...rest}
      style={[
        {
          fontFamily: monoFontFamily(weight),
          fontSize: size,
          color: color ?? colors.muted,
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

/** Mono uppercase letterspaced section label — the system's metadata idiom. */
export function SectionLabel({ children, right }: SectionLabelProps) {
  // Auto-translate so call sites can pass English keys directly.
  const { t } = useTranslation();
  return (
    <View style={styles.row}>
      <Text style={styles.label}>{t(children).toUpperCase()}</Text>
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
  '400': fonts.sans.native.regular,
  '500': fonts.sans.native.medium,
  '600': fonts.sans.native.semibold,
  '700': fonts.sans.native.bold,
};

function sansFontFamily(weight: TextStyle['fontWeight']): string {
  if (typeof weight === 'string' && SANS_FACE[weight]) return SANS_FACE[weight];
  if (typeof weight === 'number') return SANS_FACE[String(weight)] ?? fonts.sans.native.regular;
  return fonts.sans.native.regular;
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
          color: color ?? colors.ink,
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
  label: {
    fontFamily: fonts.mono.native.regular,
    fontSize: 10,
    letterSpacing: 1.2,
    textTransform: 'uppercase',
    color: colors.faint,
  },
});
