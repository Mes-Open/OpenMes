// Shimmer — a single skeleton placeholder block.
//
// Opacity pulses between 0.4 and 1.0 in a loop. Cheaper than a moving
// gradient sweep, plays nicely with RN-Web (no react-native-reanimated
// dependency), and reads as "loading" without being noisy.
//
// Compose multiple Shimmers to skeleton-out any layout. Pass `radius` to
// match the silhouette of the real content (e.g. radius:100 for a chip).
//
// Light-only v1: Colors[scheme] switching dropped — `forceDark` is accepted but ignored.

import { useEffect, useRef } from 'react';
import { Animated, Easing, type ViewStyle } from 'react-native';

import { colors } from '@openmes/ui';

interface Props {
  width?: number | `${number}%`;
  height?: number;
  radius?: number;
  style?: ViewStyle;
  /** Force a specific scheme — kept for API compatibility, ignored in light-only v1. */
  forceDark?: boolean;
}

export function Shimmer({ width, height = 12, radius = 6, style }: Props) {
  const opacity = useRef(new Animated.Value(0.45)).current;

  useEffect(() => {
    const loop = Animated.loop(
      Animated.sequence([
        Animated.timing(opacity, {
          toValue: 1,
          duration: 700,
          easing: Easing.inOut(Easing.quad),
          useNativeDriver: true,
        }),
        Animated.timing(opacity, {
          toValue: 0.45,
          duration: 700,
          easing: Easing.inOut(Easing.quad),
          useNativeDriver: true,
        }),
      ]),
    );
    loop.start();
    return () => loop.stop();
  }, [opacity]);

  return (
    <Animated.View
      // line2 at full opacity, chip-ish when faded — the shimmer sweeps
      // between the system's two hairline/chip tones, visible against both
      // the page bg and the card bg.
      style={[
        {
          width: width as any,
          height,
          borderRadius: radius,
          backgroundColor: colors.line2,
          opacity,
        },
        style,
      ]}
    />
  );
}
