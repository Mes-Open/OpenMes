import { useEffect, useRef } from 'react';
import {
  Animated,
  Pressable,
  StyleSheet,
  type StyleProp,
  type ViewStyle,
} from 'react-native';

import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';

interface Props {
  value: boolean;
  onValueChange?: (next: boolean) => void;
  disabled?: boolean;
  /** Force dark variant (operator screens). */
  dark?: boolean;
  /** Override the "on" track color. Default: green. */
  onColor?: string;
  style?: StyleProp<ViewStyle>;
  testID?: string;
}

const TRACK_W = 40;
const TRACK_H = 22;
const KNOB = 18;
const PADDING = 2;
const TRAVEL = TRACK_W - KNOB - PADDING * 2;

/**
 * Pill-shaped two-tone switch matching the OpenMES design language: a soft
 * neutral track flips to the "on" color, with a white knob that slides between
 * 2px insets. Replaces React Native's split-color Switch which doesn't honor
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
  const scheme = useColorScheme() ?? 'light';
  const palette = dark ? Colors.dark : Colors[scheme];
  const useDarkOff = dark || scheme === 'dark';

  const offTrack = useDarkOff ? '#3a3a44' : '#cfccc4';
  const onTrack = onColor ?? palette.success;

  const anim = useRef(new Animated.Value(value ? 1 : 0)).current;
  useEffect(() => {
    Animated.timing(anim, {
      toValue: value ? 1 : 0,
      duration: 150,
      useNativeDriver: false,
    }).start();
  }, [value, anim]);

  const trackColor = anim.interpolate({
    inputRange: [0, 1],
    outputRange: [offTrack, onTrack],
  });
  const knobLeft = anim.interpolate({
    inputRange: [0, 1],
    outputRange: [PADDING, PADDING + TRAVEL],
  });

  return (
    <Pressable
      testID={testID}
      onPress={() => !disabled && onValueChange?.(!value)}
      disabled={disabled}
      hitSlop={6}
      style={[
        { opacity: disabled ? 0.5 : 1 },
        style,
      ]}
      accessibilityRole="switch"
      accessibilityState={{ checked: value, disabled }}>
      <Animated.View style={[styles.track, { backgroundColor: trackColor }]}>
        <Animated.View style={[styles.knob, { left: knobLeft }]} />
      </Animated.View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  track: {
    width: TRACK_W,
    height: TRACK_H,
    borderRadius: TRACK_H / 2,
    justifyContent: 'center',
  },
  knob: {
    position: 'absolute',
    top: PADDING,
    width: KNOB,
    height: KNOB,
    borderRadius: KNOB / 2,
    backgroundColor: '#ffffff',
    boxShadow: '0px 1px 2px rgba(0, 0, 0, 0.2)',
    elevation: 2,
  },
});
