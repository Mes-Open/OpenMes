/**
 * Button — Geist White system (design ref: OpenMES Components.dc.html §02).
 * Native twin of index.web.jsx — identical props API.
 */
import React from 'react';
import {
    ActivityIndicator,
    Pressable,
    StyleSheet,
    Text,
    View,
    type StyleProp,
    type ViewStyle,
} from 'react-native';

import { colors, fonts, radius } from '../tokens';

export type ButtonVariant = 'primary' | 'accent' | 'secondary' | 'ghost' | 'danger';

export interface ButtonProps {
    variant?: ButtonVariant;
    disabled?: boolean;
    loading?: boolean;
    onPress?: () => void;
    style?: StyleProp<ViewStyle>;
    children: React.ReactNode;
}

const variantStyles: Record<ButtonVariant, { container: ViewStyle; text: { color: string } }> = {
    primary: { container: { backgroundColor: colors.ink }, text: { color: '#FFFFFF' } },
    accent: { container: { backgroundColor: colors.accent }, text: { color: '#FFFFFF' } },
    secondary: { container: { backgroundColor: colors.chip }, text: { color: colors.ink } },
    ghost: {
        container: { backgroundColor: 'transparent', borderWidth: 1, borderColor: colors.line, paddingVertical: 9 },
        text: { color: colors.ink },
    },
    danger: { container: { backgroundColor: colors.blockedBg }, text: { color: colors.blocked } },
};

export function Button({ variant = 'primary', disabled = false, loading = false, onPress, style, children }: ButtonProps) {
    const v = variantStyles[variant];
    const inactive = disabled || loading;
    return (
        <Pressable
            accessibilityRole="button"
            accessibilityState={{ disabled: inactive, busy: loading }}
            disabled={inactive}
            onPress={onPress}
            style={({ pressed }) => [
                styles.base,
                v.container,
                inactive && styles.disabled,
                pressed && !inactive && styles.pressed,
                style,
            ]}
        >
            {loading && <ActivityIndicator size="small" color={colors.accent} style={styles.spinner} />}
            <Text style={[styles.label, v.text, inactive && styles.disabledLabel]}>{children}</Text>
        </Pressable>
    );
}

export type IconButtonVariant = 'primary' | 'danger' | 'default';

export interface IconButtonProps {
    variant?: IconButtonVariant;
    onPress?: () => void;
    accessibilityLabel?: string;
    style?: StyleProp<ViewStyle>;
    children: React.ReactNode;
}

const iconVariantStyles: Record<IconButtonVariant, { container: ViewStyle; color: string }> = {
    primary: { container: { backgroundColor: colors.ink }, color: '#FFFFFF' },
    danger: { container: { backgroundColor: colors.blockedBg }, color: colors.blocked },
    default: { container: { backgroundColor: colors.chip }, color: colors.ink },
};

export function IconButton({ variant = 'default', onPress, accessibilityLabel, style, children }: IconButtonProps) {
    const v = iconVariantStyles[variant];
    return (
        <Pressable
            accessibilityRole="button"
            accessibilityLabel={accessibilityLabel}
            onPress={onPress}
            style={({ pressed }) => [styles.icon, v.container, pressed && styles.pressed, style]}
        >
            <View>
                {typeof children === 'string' ? (
                    <Text style={[styles.iconGlyph, { color: v.color }]}>{children}</Text>
                ) : (
                    children
                )}
            </View>
        </Pressable>
    );
}

const styles = StyleSheet.create({
    base: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'center',
        gap: 8,
        paddingVertical: 10,
        paddingHorizontal: 16,
        borderRadius: radius.sm,
        alignSelf: 'flex-start',
    },
    label: {
        fontSize: 13,
        fontFamily: fonts.sans.native.semibold,
    },
    spinner: {
        transform: [{ scale: 0.7 }],
    },
    pressed: {
        opacity: 0.85,
    },
    disabled: {
        backgroundColor: colors.chip,
        opacity: 0.6,
        borderWidth: 0,
    },
    disabledLabel: {
        color: colors.faint,
    },
    icon: {
        width: 38,
        height: 38,
        borderRadius: radius.sm,
        alignItems: 'center',
        justifyContent: 'center',
    },
    iconGlyph: {
        fontSize: 17,
        fontFamily: fonts.sans.native.semibold,
    },
});
