// Light-only v1: Colors[scheme] switching dropped — Geist White tokens; dark shop-floor theming returns via token theming later.
import { useEffect, useRef } from 'react';
import {
  Animated,
  Pressable,
  StyleSheet,
  type StyleProp,
  type ViewStyle,
} from 'react-native';

import { colors, radius } from '@openmes/ui';

interface Props {
  value: boolean;
  onValueChange?: (next: boolean) => void;
  disabled?: boolean;
  /** Kept for API compatibility — ignored in light-only v1. */
  dark?: boolean;
  /** Kept for API compatibility — ignored; the "on" track is always accent. */
  onColor?: string;
  style?: StyleProp<ViewStyle>;
  testID?: string;
}

/** Geist White switch geometry: 42×24 track, 18px thumb, 3px insets. */
const TRACK_W = 42;
const TRACK_H = 24;
const KNOB = 18;
const PADDING = 3;
const TRAVEL = TRACK_W - KNOB - PADDING * 2;

/**
 * Pill-shaped two-tone switch on the Geist White design system: a faintest
 * neutral track flips to accent, with a white knob that slides between 3px
 * insets. Replaces React Native's split-color Switch which doesn't honor
 * our palette on Android.
 */
export function Switch({
  value,
  onValueChange,
  disabled,
  dark,
  onColor,
  style,
  testID,
}: Props) {
  const anim = useRef(new Animated.Value(value ? 1 : 0)).current;
  useEffect(() => {
    Animated.timing(anim, {
      toValue: value ? 1 : 0,
      duration: 160,
      useNativeDriver: true,
    }).start();
  }, [value, anim]);

  const translateX = anim.interpolate({
    inputRange: [0, 1],
    outputRange: [0, TRAVEL],
  });

  return (
    <Pressable
      testID={testID}
      onPress={() => !disabled && onValueChange?.(!value)}
      disabled={disabled}
      hitSlop={6}
      style={[{ opacity: disabled ? 0.6 : 1 }, style]}
      accessibilityRole="switch"
      accessibilityState={{ checked: value, disabled }}>
      <Animated.View
        style={[styles.track, { backgroundColor: value ? colors.accent : colors.faintest }]}>
        <Animated.View style={[styles.knob, { transform: [{ translateX }] }]} />
      </Animated.View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  track: {
    width: TRACK_W,
    height: TRACK_H,
    borderRadius: radius.pill,
    justifyContent: 'center',
  },
  knob: {
    position: 'absolute',
    top: PADDING,
    left: PADDING,
    width: KNOB,
    height: KNOB,
    borderRadius: KNOB / 2,
    backgroundColor: '#FFFFFF',
    boxShadow: '0px 1px 3px rgba(0, 0, 0, 0.25)',
    elevation: 2,
  },
});
