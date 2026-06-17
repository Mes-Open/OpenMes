/**
 * RadioGroup — Geist White system (design ref: OpenMES Components.dc.html §05).
 * Native twin of index.web.jsx — identical props API.
 */
import React from 'react';
import { Pressable, StyleSheet, Text, View, type StyleProp, type ViewStyle } from 'react-native';

import { colors, fonts } from '../tokens';

export interface RadioGroupOption {
    value: string;
    label: string;
}

export interface RadioGroupProps {
    options: RadioGroupOption[];
    value: string;
    onChange?: (value: string) => void;
    /** Row layout like the design; set false to stack vertically. */
    horizontal?: boolean;
    style?: StyleProp<ViewStyle>;
}

export function RadioGroup({ options, value, onChange, horizontal = true, style }: RadioGroupProps) {
    return (
        <View accessibilityRole="radiogroup" style={[horizontal ? styles.groupRow : styles.groupColumn, style]}>
            {options.map((option) => {
                const active = option.value === value;
                return (
                    <Pressable
                        key={option.value}
                        accessibilityRole="radio"
                        accessibilityState={{ checked: active }}
                        onPress={() => onChange?.(option.value)}
                        style={styles.item}
                    >
                        <View style={[styles.circle, active ? styles.circleActive : styles.circleInactive]}>
                            {active && <View style={styles.dot} />}
                        </View>
                        <Text style={styles.label}>{option.label}</Text>
                    </Pressable>
                );
            })}
        </View>
    );
}

const styles = StyleSheet.create({
    groupRow: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 18,
    },
    groupColumn: {
        flexDirection: 'column',
        alignItems: 'flex-start',
        gap: 13,
    },
    item: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 9,
    },
    circle: {
        width: 18,
        height: 18,
        borderRadius: 9,
        borderWidth: 2,
        alignItems: 'center',
        justifyContent: 'center',
        flexShrink: 0,
    },
    circleActive: {
        borderColor: colors.accent,
    },
    circleInactive: {
        borderColor: colors.faintest,
    },
    dot: {
        width: 8,
        height: 8,
        borderRadius: 4,
        backgroundColor: colors.accent,
    },
    label: {
        fontSize: 13,
        color: colors.ink,
        fontFamily: fonts.sans.native.regular,
    },
});
