/**
 * SegmentedControl — Geist White system (design ref: OpenMES Components.dc.html §05).
 * Native twin of index.web.jsx — identical props API.
 */
import React from 'react';
import { Pressable, StyleSheet, Text, View, type StyleProp, type ViewStyle } from 'react-native';

import { colors, fonts, radius } from '../tokens';

export interface SegmentedControlOption {
    value: string;
    label: string;
}

export interface SegmentedControlProps {
    options: SegmentedControlOption[];
    value: string;
    onChange?: (value: string) => void;
    style?: StyleProp<ViewStyle>;
}

export function SegmentedControl({ options, value, onChange, style }: SegmentedControlProps) {
    return (
        <View accessibilityRole="radiogroup" style={[styles.container, style]}>
            {options.map((option) => {
                const active = option.value === value;
                return (
                    <Pressable
                        key={option.value}
                        accessibilityRole="radio"
                        accessibilityState={{ checked: active }}
                        onPress={() => onChange?.(option.value)}
                        style={[styles.segment, active && styles.segmentActive]}
                    >
                        <Text style={[styles.label, active && styles.labelActive]}>{option.label}</Text>
                    </Pressable>
                );
            })}
        </View>
    );
}

const styles = StyleSheet.create({
    container: {
        flexDirection: 'row',
        gap: 3,
        backgroundColor: colors.bg,
        borderWidth: 1,
        borderColor: colors.line,
        borderRadius: radius.sm,
        padding: 3,
    },
    segment: {
        flex: 1,
        alignItems: 'center',
        paddingVertical: 7,
        borderRadius: 6,
    },
    segmentActive: {
        backgroundColor: colors.ink,
    },
    label: {
        fontSize: 12.5,
        color: colors.muted,
        fontFamily: fonts.sans.native.medium,
    },
    labelActive: {
        color: '#FFFFFF',
    },
});
