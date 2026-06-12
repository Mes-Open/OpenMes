// Shimmer — a single skeleton placeholder block.
//
// Opacity pulses between 0.4 and 1.0 in a loop. Cheaper than a moving
// gradient sweep, plays nicely with RN-Web (no react-native-reanimated
// dependency), and reads as "loading" without being noisy.
//
// Compose multiple Shimmers to skeleton-out any layout. Pass `radius` to
// match the silhouette of the real content (e.g. radius:100 for a chip).

import { useEffect, useRef } from 'react';
import { Animated, Easing, type ViewStyle } from 'react-native';

import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';

interface Props {
  width?: number | `${number}%`;
  height?: number;
  radius?: number;
  style?: ViewStyle;
  /** Force a specific scheme — used when the skeleton sits inside an
   *  always-dark surface (operator kiosk). */
  forceDark?: boolean;
}

export function Shimmer({ width, height = 12, radius = 6, style, forceDark }: Props) {
  const scheme = useColorScheme() ?? 'light';
  const palette = forceDark ? Colors.dark : Colors[scheme];
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
      // surfaceAlt sits one step above the page background — visible
      // against both the page bg and the card bg, in both schemes.
      style={[
        {
          width: width as any,
          height,
          borderRadius: radius,
          backgroundColor: palette.surfaceAlt,
          opacity,
        },
        style,
      ]}
    />
  );
}
