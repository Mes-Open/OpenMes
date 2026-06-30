/**
 * FAB — Geist White system (design ref: OpenMES Components.dc.html §10④).
 * Native-only (no web twin): 52px accent circle floating bottom-right above the tab bar.
 */
import React from 'react';
import {
    Pressable,
    StyleSheet,
    Text,
    type StyleProp,
    type ViewStyle,
} from 'react-native';

import { colors, fonts } from '../tokens';

export interface FABProps {
    onPress?: () => void;
    /** Glyph node, or a string rendered as the 26px white glyph. Default '+'. */
    icon?: React.ReactNode;
    /** Distance from the bottom edge. Default 80 (clears the 64px tab bar). */
    bottom?: number;
    accessibilityLabel?: string;
    style?: StyleProp<ViewStyle>;
}

export function FAB({ onPress, icon = '+', bottom = 80, accessibilityLabel, style }: FABProps) {
    return (
        <Pressable
            accessibilityRole="button"
            accessibilityLabel={accessibilityLabel}
            onPress={onPress}
            style={({ pressed }) => [styles.fab, { bottom }, pressed && styles.pressed, style]}
        >
            {typeof icon === 'string' ? <Text style={styles.glyph}>{icon}</Text> : icon}
        </Pressable>
    );
}

const styles = StyleSheet.create({
    fab: {
        position: 'absolute',
        right: 16,
        width: 52,
        height: 52,
        borderRadius: 26,
        backgroundColor: colors.accent,
        alignItems: 'center',
        justifyContent: 'center',
        shadowColor: colors.accent,
        shadowOffset: { width: 0, height: 10 },
        shadowOpacity: 0.6,
        shadowRadius: 12,
        elevation: 8,
    },
    glyph: {
        fontSize: 26,
        fontFamily: fonts.sans.native.semibold,
        color: '#FFFFFF',
        lineHeight: 30,
    },
    pressed: {
        opacity: 0.85,
    },
});
