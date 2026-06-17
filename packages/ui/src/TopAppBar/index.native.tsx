/**
 * TopAppBar — Geist White system (design ref: OpenMES Components.dc.html §11①).
 * Native-only (no web twin): card header with back chevron, title + mono subtitle,
 * and a chip ⋯ action (or custom right node).
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

import { colors, fonts, radius } from '../tokens';

export interface TopAppBarProps {
    title: string;
    subtitle?: string;
    /** Renders the ‹ back chevron when set. */
    onBack?: () => void;
    /** Renders the ⋯ chip when set (ignored when `right` is provided). */
    onMenu?: () => void;
    /** Custom right-side node, replaces the ⋯ chip. */
    right?: React.ReactNode;
    backAccessibilityLabel?: string;
    menuAccessibilityLabel?: string;
    style?: StyleProp<ViewStyle>;
}

export function TopAppBar({
    title,
    subtitle,
    onBack,
    onMenu,
    right,
    backAccessibilityLabel = 'Back',
    menuAccessibilityLabel = 'More actions',
    style,
}: TopAppBarProps) {
    return (
        <View accessibilityRole="header" style={[styles.bar, style]}>
            {onBack != null && (
                <Pressable
                    accessibilityRole="button"
                    accessibilityLabel={backAccessibilityLabel}
                    hitSlop={10}
                    onPress={onBack}
                    style={({ pressed }) => pressed && styles.pressed}
                >
                    <Text style={styles.back}>{'‹'}</Text>
                </Pressable>
            )}
            <View style={styles.titles}>
                <Text numberOfLines={1} style={styles.title}>
                    {title}
                </Text>
                {subtitle != null && (
                    <Text numberOfLines={1} style={styles.subtitle}>
                        {subtitle}
                    </Text>
                )}
            </View>
            {right != null
                ? right
                : onMenu != null && (
                      <Pressable
                          accessibilityRole="button"
                          accessibilityLabel={menuAccessibilityLabel}
                          onPress={onMenu}
                          style={({ pressed }) => [styles.menu, pressed && styles.pressed]}
                      >
                          <Text style={styles.menuGlyph}>{'⋯'}</Text>
                      </Pressable>
                  )}
        </View>
    );
}

const styles = StyleSheet.create({
    bar: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 12,
        paddingTop: 16,
        paddingHorizontal: 16,
        paddingBottom: 14,
        backgroundColor: colors.card,
        borderBottomWidth: 1,
        borderBottomColor: colors.line2,
    },
    back: {
        fontSize: 20,
        lineHeight: 20,
        fontFamily: fonts.sans.native.regular,
        color: colors.ink,
    },
    titles: {
        flex: 1,
        minWidth: 0,
    },
    title: {
        fontSize: 15,
        fontFamily: fonts.sans.native.semibold,
        color: colors.ink,
    },
    subtitle: {
        fontSize: 9.5,
        fontFamily: fonts.mono.native.regular,
        color: colors.faint,
        marginTop: 1,
    },
    menu: {
        width: 30,
        height: 30,
        borderRadius: radius.sm,
        backgroundColor: colors.chip,
        alignItems: 'center',
        justifyContent: 'center',
    },
    menuGlyph: {
        fontSize: 15,
        fontFamily: fonts.sans.native.regular,
        color: colors.ink,
    },
    pressed: {
        opacity: 0.7,
    },
});
