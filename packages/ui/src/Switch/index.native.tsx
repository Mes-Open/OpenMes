/**
 * Switch — Geist White system (design ref: OpenMES Components.dc.html §03).
 * Native twin of index.web.jsx — identical props API. Custom Pressable track
 * (not RN Switch) so the 42×24 / 18px-thumb geometry matches the design.
 */
import React from 'react';
import { Animated, Pressable, StyleSheet, type StyleProp, type ViewStyle } from 'react-native';

import { colors, radius } from '../tokens';

export interface SwitchProps {
    checked: boolean;
    onChange?: (checked: boolean) => void;
    disabled?: boolean;
    style?: StyleProp<ViewStyle>;
}

/** Thumb travel: track 42 − thumb 18 − 2 × 3px inset. */
const TRAVEL = 18;

export function Switch({ checked, onChange, disabled = false, style }: SwitchProps) {
    const anim = React.useRef(new Animated.Value(checked ? 1 : 0)).current;

    React.useEffect(() => {
        Animated.timing(anim, {
            toValue: checked ? 1 : 0,
            duration: 160,
            useNativeDriver: true,
        }).start();
    }, [checked, anim]);

    const translateX = anim.interpolate({ inputRange: [0, 1], outputRange: [0, TRAVEL] });

    return (
        <Pressable
            accessibilityRole="switch"
            accessibilityState={{ checked, disabled }}
            disabled={disabled}
            onPress={() => onChange?.(!checked)}
            style={[
                styles.track,
                { backgroundColor: checked ? colors.accent : colors.faintest },
                disabled && styles.disabled,
                style,
            ]}
        >
            <Animated.View style={[styles.thumb, { transform: [{ translateX }] }]} />
        </Pressable>
    );
}

const styles = StyleSheet.create({
    track: {
        width: 42,
        height: 24,
        borderRadius: radius.pill,
        justifyContent: 'center',
        flexShrink: 0,
    },
    thumb: {
        position: 'absolute',
        top: 3,
        left: 3,
        width: 18,
        height: 18,
        borderRadius: 9,
        backgroundColor: '#FFFFFF',
        shadowColor: '#000000',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.25,
        shadowRadius: 3,
        elevation: 2,
    },
    disabled: {
        opacity: 0.6,
    },
});
