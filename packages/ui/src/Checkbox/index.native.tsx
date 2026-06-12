/**
 * Checkbox — Geist White system (design ref: OpenMES Components.dc.html §03).
 * Native twin of index.web.jsx — identical props API.
 */
import React from 'react';
import { Pressable, StyleSheet, Text, View, type StyleProp, type ViewStyle } from 'react-native';

import { colors, fonts } from '../tokens';

export interface CheckboxProps {
    checked: boolean;
    onChange?: (checked: boolean) => void;
    disabled?: boolean;
    label?: string;
    style?: StyleProp<ViewStyle>;
}

export function Checkbox({ checked, onChange, disabled = false, label, style }: CheckboxProps) {
    return (
        <Pressable
            accessibilityRole="checkbox"
            accessibilityState={{ checked, disabled }}
            accessibilityLabel={label}
            disabled={disabled}
            onPress={() => onChange?.(!checked)}
            style={[styles.row, disabled && styles.disabled, style]}
        >
            <View style={[styles.box, checked ? styles.boxChecked : styles.boxUnchecked]}>
                {checked && <Text style={styles.check}>✓</Text>}
            </View>
            {label != null && <Text style={styles.label}>{label}</Text>}
        </Pressable>
    );
}

const styles = StyleSheet.create({
    row: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 9,
        alignSelf: 'flex-start',
    },
    box: {
        width: 18,
        height: 18,
        borderRadius: 5,
        alignItems: 'center',
        justifyContent: 'center',
        flexShrink: 0,
    },
    boxChecked: {
        backgroundColor: colors.accent,
    },
    boxUnchecked: {
        borderWidth: 2,
        borderColor: colors.faintest,
    },
    check: {
        color: '#FFFFFF',
        fontSize: 12,
        fontFamily: fonts.sans.native.bold,
        lineHeight: 14,
    },
    label: {
        fontSize: 13,
        color: colors.ink,
        fontFamily: fonts.sans.native.regular,
    },
    disabled: {
        opacity: 0.6,
    },
});
