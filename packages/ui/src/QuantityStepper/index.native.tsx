/**
 * QuantityStepper — Geist White system (design ref: OpenMES Components.dc.html §04).
 * Native twin of index.web.jsx — identical props API. Compact form-field
 * stepper, not the big touch stepper.
 */
import React from 'react';
import { Pressable, StyleSheet, Text, View, type StyleProp, type ViewStyle } from 'react-native';

import { colors, fonts, radius } from '../tokens';

export interface QuantityStepperProps {
    value: number;
    onChange?: (value: number) => void;
    min?: number;
    max?: number;
    step?: number;
    style?: StyleProp<ViewStyle>;
}

export function QuantityStepper({ value, onChange, min, max, step = 1, style }: QuantityStepperProps) {
    const atMin = min != null && value <= min;
    const atMax = max != null && value >= max;
    const clamp = (n: number) =>
        Math.min(max ?? Number.POSITIVE_INFINITY, Math.max(min ?? Number.NEGATIVE_INFINITY, n));

    return (
        <View style={[styles.row, style]}>
            <Pressable
                accessibilityRole="button"
                accessibilityState={{ disabled: atMin }}
                disabled={atMin}
                onPress={() => onChange?.(clamp(value - step))}
                style={styles.button}
            >
                <Text style={[styles.glyph, atMin && styles.glyphDisabled]}>−</Text>
            </Pressable>
            <Text style={styles.value}>{value}</Text>
            <Pressable
                accessibilityRole="button"
                accessibilityState={{ disabled: atMax }}
                disabled={atMax}
                onPress={() => onChange?.(clamp(value + step))}
                style={styles.button}
            >
                <Text style={[styles.glyph, atMax && styles.glyphDisabled]}>+</Text>
            </Pressable>
        </View>
    );
}

const styles = StyleSheet.create({
    row: {
        flexDirection: 'row',
        alignItems: 'center',
        borderWidth: 1,
        borderColor: colors.line,
        borderRadius: radius.sm,
        backgroundColor: colors.bg,
        overflow: 'hidden',
    },
    button: {
        paddingVertical: 9,
        paddingHorizontal: 12,
    },
    glyph: {
        fontSize: 16,
        lineHeight: 18,
        color: colors.muted,
        fontFamily: fonts.sans.native.regular,
    },
    glyphDisabled: {
        color: colors.faintest,
    },
    value: {
        flex: 1,
        textAlign: 'center',
        fontSize: 13,
        color: colors.ink,
        fontFamily: fonts.mono.native.regular,
    },
});
