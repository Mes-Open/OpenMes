/**
 * Badge — Geist White system (design ref: OpenMES Components.dc.html §06).
 * Native twin of index.web.jsx — identical props API.
 */
import React from 'react';
import { StyleSheet, Text, View, type StyleProp, type TextStyle, type ViewStyle } from 'react-native';

import { colors, fonts, radius } from '../tokens';

export type BadgeVariant = 'danger' | 'neutral' | 'outline';

export interface BadgeProps {
    variant?: BadgeVariant;
    style?: StyleProp<ViewStyle>;
    children: React.ReactNode;
}

const variantStyles: Record<BadgeVariant, { container: ViewStyle; text: TextStyle }> = {
    danger: { container: { backgroundColor: colors.blocked }, text: { color: '#FFFFFF' } },
    neutral: { container: { backgroundColor: colors.chip }, text: { color: colors.muted } },
    outline: { container: { borderWidth: 1, borderColor: colors.accent }, text: { color: colors.accent } },
};

export function Badge({ variant = 'neutral', style, children }: BadgeProps) {
    const v = variantStyles[variant];
    return (
        <View accessibilityRole="text" style={[styles.badge, v.container, style]}>
            <Text style={[styles.label, v.text]}>{children}</Text>
        </View>
    );
}

const styles = StyleSheet.create({
    badge: {
        flexDirection: 'row',
        alignItems: 'center',
        borderRadius: radius.pill,
        paddingVertical: 2,
        paddingHorizontal: 9,
        alignSelf: 'flex-start',
    },
    label: {
        fontFamily: fonts.mono.native.regular,
        fontSize: 11,
    },
});
