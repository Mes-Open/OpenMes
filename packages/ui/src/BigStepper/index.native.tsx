/**
 * BigStepper — Geist White system (design ref: OpenMES Components.dc.html §10⑥).
 * Native-only (no web twin): 48px touch − / + buttons around a mono 30px value.
 */
import React from 'react';
import {
    Pressable,
    StyleSheet,
    Text,
    View,
    type StyleProp,
    type ViewStyle,
} from 'react-native';

import { colors, fonts } from '../tokens';

export interface BigStepperProps {
    value: number;
    onChange: (value: number) => void;
    min?: number;
    max?: number;
    step?: number;
    decrementAccessibilityLabel?: string;
    incrementAccessibilityLabel?: string;
    style?: StyleProp<ViewStyle>;
}

export function BigStepper({
    value,
    onChange,
    min,
    max,
    step = 1,
    decrementAccessibilityLabel = 'Decrease',
    incrementAccessibilityLabel = 'Increase',
    style,
}: BigStepperProps) {
    const canDecrement = min === undefined || value > min;
    const canIncrement = max === undefined || value < max;

    const decrement = () => {
        const next = value - step;
        onChange(min === undefined ? next : Math.max(min, next));
    };
    const increment = () => {
        const next = value + step;
        onChange(max === undefined ? next : Math.min(max, next));
    };

    return (
        <View style={[styles.stepper, style]}>
            <Pressable
                accessibilityRole="button"
                accessibilityLabel={decrementAccessibilityLabel}
                accessibilityState={{ disabled: !canDecrement }}
                disabled={!canDecrement}
                onPress={decrement}
                style={({ pressed }) => [
                    styles.button,
                    styles.minus,
                    !canDecrement && styles.disabled,
                    pressed && canDecrement && styles.pressed,
                ]}
            >
                <Text style={styles.minusGlyph}>{'−'}</Text>
            </Pressable>
            <Text accessibilityLiveRegion="polite" style={styles.value}>
                {value}
            </Text>
            <Pressable
                accessibilityRole="button"
                accessibilityLabel={incrementAccessibilityLabel}
                accessibilityState={{ disabled: !canIncrement }}
                disabled={!canIncrement}
                onPress={increment}
                style={({ pressed }) => [
                    styles.button,
                    styles.plus,
                    !canIncrement && styles.disabled,
                    pressed && canIncrement && styles.pressed,
                ]}
            >
                <Text style={styles.plusGlyph}>+</Text>
            </Pressable>
        </View>
    );
}

const styles = StyleSheet.create({
    stepper: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 14,
    },
    button: {
        width: 48,
        height: 48,
        borderRadius: 13,
        alignItems: 'center',
        justifyContent: 'center',
    },
    minus: {
        backgroundColor: colors.card,
        borderWidth: 1,
        borderColor: colors.line,
    },
    plus: {
        backgroundColor: colors.ink,
    },
    minusGlyph: {
        fontSize: 24,
        fontFamily: fonts.sans.native.regular,
        color: colors.ink,
    },
    plusGlyph: {
        fontSize: 24,
        fontFamily: fonts.sans.native.regular,
        color: '#FFFFFF',
    },
    value: {
        flex: 1,
        textAlign: 'center',
        fontSize: 30,
        fontFamily: fonts.mono.native.medium,
        color: colors.ink,
    },
    pressed: {
        opacity: 0.85,
    },
    disabled: {
        opacity: 0.4,
    },
});
