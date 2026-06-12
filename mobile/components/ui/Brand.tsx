// Light-only v1: no scheme handling — the mark's accent/ink colors are fixed.
import { StyleSheet, Text, View, type ImageStyle, type StyleProp, type ViewStyle } from 'react-native';

import { colors, fonts } from '@openmes/ui';

interface LogoProps {
  /**
   * Approximate cap height of the wordmark (px). The mark square scales with
   * it so the lockup keeps its proportions at any size.
   */
  size?: number;
  /** Kept for API compatibility — the mark has fixed colors and ignores it. */
  color?: string;
  style?: ImageStyle;
}

/**
 * OpenMES brand lockup, drawn in code (no asset): a small split square —
 * accent-orange triangle over ink triangle, divided along the anti-diagonal —
 * followed by the lowercase "openmes" wordmark in Geist semibold ink.
 *
 * Each triangle is an absolutely-positioned 0×0 View using the border trick
 * (colored border + transparent border meet along the diagonal).
 */
export function BrandLogo({ size = 18, style }: LogoProps) {
  const mark = Math.max(8, Math.round(size * 1.1));
  return (
    <View
      accessibilityRole="image"
      accessibilityLabel="openmes"
      // `style` stays typed ImageStyle for API compatibility with the old
      // Image-based logo; the shared subset (layout/margins) applies cleanly.
      style={[styles.row, { gap: Math.round(size * 0.45) }, style as StyleProp<ViewStyle>]}>
      <View style={[styles.mark, { width: mark, height: mark, borderRadius: Math.max(2, Math.round(mark * 0.18)) }]}>
        <View
          style={[
            styles.triangle,
            { borderTopWidth: mark, borderRightWidth: mark, borderTopColor: colors.accent },
          ]}
        />
        <View
          style={[
            styles.triangle,
            { borderBottomWidth: mark, borderLeftWidth: mark, borderBottomColor: colors.ink },
          ]}
        />
      </View>
      <Text style={[styles.word, { fontSize: Math.round(size * 1.05) }]}>openmes</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'center', alignSelf: 'flex-start' },
  mark: { overflow: 'hidden', position: 'relative' },
  triangle: {
    position: 'absolute',
    top: 0,
    left: 0,
    width: 0,
    height: 0,
    borderColor: 'transparent',
  },
  word: {
    fontFamily: fonts.sans.native.semibold,
    color: colors.ink,
    letterSpacing: -0.3,
    textTransform: 'lowercase',
  },
});
