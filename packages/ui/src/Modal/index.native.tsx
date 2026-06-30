/**
 * Modal — Geist White system (design ref: OpenMES Components.dc.html §09
 * form-modal specimen). Native twin of index.web.jsx — identical props API:
 * RN Modal (transparent) + centered card over scrim.
 */
import React from 'react';
import { Modal as RNModal, Pressable, StyleSheet, Text, View, type StyleProp, type ViewStyle } from 'react-native';

import { colors, fonts, radius } from '../tokens';

export interface ModalProps {
    open: boolean;
    onClose?: () => void;
    title: string;
    subtitle?: string;
    footer?: React.ReactNode;
    children?: React.ReactNode;
    closeLabel?: string;
    style?: StyleProp<ViewStyle>;
}

export function Modal({ open, onClose, title, subtitle, footer, children, closeLabel, style }: ModalProps) {
    return (
        <RNModal visible={open} transparent animationType="fade" onRequestClose={onClose}>
            <View style={styles.scrim}>
                <View accessibilityViewIsModal style={[styles.card, style]}>
                    <View style={styles.header}>
                        <View style={styles.headerText}>
                            <Text accessibilityRole="header" style={styles.title}>
                                {title}
                            </Text>
                            {subtitle != null && <Text style={styles.subtitle}>{subtitle}</Text>}
                        </View>
                        <Pressable accessibilityRole="button" accessibilityLabel={closeLabel} hitSlop={8} onPress={onClose}>
                            <Text style={styles.close}>×</Text>
                        </Pressable>
                    </View>
                    <View style={styles.body}>{children}</View>
                    {footer != null && <View style={styles.footer}>{footer}</View>}
                </View>
            </View>
        </RNModal>
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
        maxWidth: 440,
        backgroundColor: colors.card,
        borderWidth: 1,
        borderColor: colors.line,
        borderRadius: radius.md,
        overflow: 'hidden',
    },
    header: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        paddingVertical: 16,
        paddingHorizontal: 18,
        borderBottomWidth: 1,
        borderBottomColor: colors.line2,
    },
    headerText: {
        flex: 1,
        minWidth: 0,
    },
    title: {
        fontSize: 15,
        fontFamily: fonts.sans.native.semibold,
        color: colors.ink,
    },
    subtitle: {
        marginTop: 3,
        fontSize: 9.5,
        fontFamily: fonts.mono.native.regular,
        color: colors.faint,
    },
    close: {
        fontSize: 18,
        lineHeight: 19,
        color: colors.faint,
    },
    body: {
        paddingVertical: 16,
        paddingHorizontal: 18,
    },
    footer: {
        flexDirection: 'row',
        justifyContent: 'flex-end',
        gap: 9,
        paddingVertical: 14,
        paddingHorizontal: 18,
        borderTopWidth: 1,
        borderTopColor: colors.line2,
        backgroundColor: colors.panel,
    },
});
