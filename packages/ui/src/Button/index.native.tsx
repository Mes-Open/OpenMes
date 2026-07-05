/**
 * Button — Geist White system (design ref: OpenMES Components.dc.html §02).
 * Native twin of index.web.jsx — identical props API.
 *
 * Variants: primary (ink) · accent (orange) · secondary (chip) · ghost / outline
 * (hairline) · danger (soft red) · success (soft green). `size` sm/md/lg sets the
 * touch target; `leftIcon`/`rightIcon` flank the label; `loading` swaps a spinner.
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

export type ButtonVariant =
    | 'primary'
    | 'accent'
    | 'secondary'
    | 'ghost'
    | 'outline'
    | 'danger'
    | 'success';
export type ButtonSize = 'sm' | 'md' | 'lg';

export interface ButtonProps {
    variant?: ButtonVariant;
    size?: ButtonSize;
    disabled?: boolean;
    loading?: boolean;
    onPress?: () => void;
    leftIcon?: React.ReactNode;
    rightIcon?: React.ReactNode;
    style?: StyleProp<ViewStyle>;
    children: React.ReactNode;
}

const variantStyles: Record<ButtonVariant, { container: ViewStyle; text: { color: string } }> = {
    primary: { container: { backgroundColor: colors.ink }, text: { color: colors.onInk } },
    accent: { container: { backgroundColor: colors.accent }, text: { color: '#FFFFFF' } },
    secondary: { container: { backgroundColor: colors.chip }, text: { color: colors.ink } },
    ghost: {
        container: { backgroundColor: 'transparent', borderWidth: 1, borderColor: colors.line },
        text: { color: colors.ink },
    },
    outline: {
        container: { backgroundColor: 'transparent', borderWidth: 1, borderColor: colors.line },
        text: { color: colors.ink },
    },
    danger: { container: { backgroundColor: colors.blockedBg }, text: { color: colors.blocked } },
    success: { container: { backgroundColor: colors.runningBg }, text: { color: colors.running } },
};

const sizeStyles: Record<ButtonSize, { container: ViewStyle; fontSize: number; bold?: boolean }> = {
    sm: { container: { minHeight: 40, paddingVertical: 8, paddingHorizontal: 14 }, fontSize: 13 },
    md: { container: { minHeight: 48, paddingVertical: 12, paddingHorizontal: 16 }, fontSize: 14 },
    lg: { container: { minHeight: 56, paddingVertical: 16, paddingHorizontal: 20 }, fontSize: 15, bold: true },
};

export function Button({
    variant = 'primary',
    size = 'md',
    disabled = false,
    loading = false,
    onPress,
    leftIcon,
    rightIcon,
    style,
    children,
}: ButtonProps) {
    const v = variantStyles[variant];
    const s = sizeStyles[size];
    const inactive = disabled || loading;
    return (
        <Pressable
            accessibilityRole="button"
            accessibilityState={{ disabled: inactive, busy: loading }}
            disabled={inactive}
            onPress={onPress}
            style={({ pressed }) => [
                styles.base,
                s.container,
                v.container,
                inactive && styles.disabled,
                pressed && !inactive && styles.pressed,
                style,
            ]}
        >
            {loading ? (
                <ActivityIndicator size="small" color={v.text.color} />
            ) : (
                <View style={styles.row}>
                    {leftIcon != null && <View style={styles.icon}>{leftIcon}</View>}
                    <Text
                        style={[
                            styles.label,
                            { fontSize: s.fontSize, fontFamily: s.bold ? fonts.sans.native.bold : fonts.sans.native.semibold },
                            v.text,
                            inactive && styles.disabledLabel,
                        ]}
                    >
                        {children}
                    </Text>
                    {rightIcon != null && <View style={styles.icon}>{rightIcon}</View>}
                </View>
            )}
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
    primary: { container: { backgroundColor: colors.ink }, color: colors.onInk },
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
            style={({ pressed }) => [styles.icon38, v.container, pressed && styles.pressed, style]}
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
        borderRadius: radius.sm,
        alignSelf: 'flex-start',
    },
    row: { flexDirection: 'row', alignItems: 'center', gap: 8 },
    icon: { alignItems: 'center', justifyContent: 'center' },
    label: {
        fontFamily: fonts.sans.native.semibold,
        letterSpacing: 0.1,
    },
    pressed: { opacity: 0.85 },
    disabled: { opacity: 0.5 },
    disabledLabel: {},
    icon38: {
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
