/**
 * ConfirmDialog — Geist White system (design ref: OpenMES Components.dc.html
 * §10 "Alert dialog" specimen). Native twin of index.web.jsx — identical props
 * API: centered radius-16 card over scrim, circular 42px icon, centered text,
 * split bottom buttons separated by hairlines (cancel muted / confirm
 * blocked when `destructive`, accent otherwise).
 */
import React from 'react';
import { Modal, Pressable, StyleSheet, Text, View, type StyleProp, type ViewStyle } from 'react-native';

import { colors, fonts } from '../tokens';

export interface ConfirmDialogProps {
    open: boolean;
    onClose?: () => void;
    onConfirm?: () => void;
    title: string;
    children?: React.ReactNode;
    confirmLabel: string;
    cancelLabel: string;
    destructive?: boolean;
    icon?: React.ReactNode;
    style?: StyleProp<ViewStyle>;
}

export function ConfirmDialog({
    open,
    onClose,
    onConfirm,
    title,
    children,
    confirmLabel,
    cancelLabel,
    destructive = true,
    icon = '!',
    style,
}: ConfirmDialogProps) {
    return (
        <Modal visible={open} transparent animationType="fade" onRequestClose={onClose}>
            <View style={styles.scrim}>
                <View accessibilityViewIsModal style={[styles.card, style]}>
                    <View style={styles.bodyWrap}>
                        <View style={styles.iconCircle}>
                            {typeof icon === 'string' ? <Text style={styles.iconGlyph}>{icon}</Text> : icon}
                        </View>
                        <Text accessibilityRole="header" style={styles.title}>
                            {title}
                        </Text>
                        {children != null && <Text style={styles.body}>{children}</Text>}
                    </View>
                    <View style={styles.buttonRow}>
                        <Pressable
                            accessibilityRole="button"
                            onPress={onClose}
                            style={({ pressed }) => [styles.button, styles.cancelButton, pressed && styles.pressed]}
                        >
                            <Text style={styles.cancelLabel}>{cancelLabel}</Text>
                        </Pressable>
                        <Pressable
                            accessibilityRole="button"
                            onPress={onConfirm}
                            style={({ pressed }) => [styles.button, pressed && styles.pressed]}
                        >
                            <Text style={[styles.confirmLabel, { color: destructive ? colors.blocked : colors.accent }]}>
                                {confirmLabel}
                            </Text>
                        </Pressable>
                    </View>
                </View>
            </View>
        </Modal>
    );
}

const styles = StyleSheet.create({
    scrim: {
        flex: 1,
        backgroundColor: colors.scrim,
        alignItems: 'center',
        justifyContent: 'center',
        padding: 24,
    },
    card: {
        width: '100%',
        maxWidth: 320,
        backgroundColor: colors.card,
        borderRadius: 16,
        overflow: 'hidden',
    },
    bodyWrap: {
        paddingTop: 22,
        paddingHorizontal: 20,
        paddingBottom: 18,
        alignItems: 'center',
    },
    iconCircle: {
        width: 42,
        height: 42,
        borderRadius: 21,
        backgroundColor: colors.blockedBg,
        alignItems: 'center',
        justifyContent: 'center',
        marginBottom: 14,
    },
    iconGlyph: {
        fontSize: 21,
        fontFamily: fonts.sans.native.bold,
        color: colors.blocked,
    },
    title: {
        fontSize: 16,
        fontFamily: fonts.sans.native.semibold,
        color: colors.ink,
        textAlign: 'center',
        marginBottom: 7,
    },
    body: {
        fontSize: 12.5,
        lineHeight: 18.75, // 1.5
        fontFamily: fonts.sans.native.regular,
        color: colors.muted,
        textAlign: 'center',
    },
    buttonRow: {
        flexDirection: 'row',
        borderTopWidth: 1,
        borderTopColor: colors.line2,
    },
    button: {
        flex: 1,
        paddingVertical: 14,
        alignItems: 'center',
    },
    cancelButton: {
        borderRightWidth: 1,
        borderRightColor: colors.line2,
    },
    cancelLabel: {
        fontSize: 14.5,
        fontFamily: fonts.sans.native.medium,
        color: colors.muted,
    },
    confirmLabel: {
        fontSize: 14.5,
        fontFamily: fonts.sans.native.semibold,
    },
    pressed: {
        backgroundColor: colors.chip,
    },
});
