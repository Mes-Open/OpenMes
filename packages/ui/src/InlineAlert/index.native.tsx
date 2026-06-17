/**
 * InlineAlert — Geist White system (design ref: OpenMES Components.dc.html §08).
 * Native twin of index.web.jsx — identical props API.
 */
import React from 'react';
import { StyleSheet, Text, View, type StyleProp, type ViewStyle } from 'react-native';

import { colors, fonts, radius } from '../tokens';

export type InlineAlertSeverity = 'success' | 'info' | 'warning' | 'error';

export interface InlineAlertProps {
    severity: InlineAlertSeverity;
    title: string;
    children?: React.ReactNode;
    style?: StyleProp<ViewStyle>;
}

const severities: Record<InlineAlertSeverity, { bg: string; dot: string }> = {
    success: { bg: colors.runningBg, dot: colors.running },
    info: { bg: colors.chip, dot: colors.accent },
    warning: { bg: colors.downtimeBg, dot: colors.downtime },
    error: { bg: colors.blockedBg, dot: colors.blocked },
};

export function InlineAlert({ severity, title, children, style }: InlineAlertProps) {
    const s = severities[severity];
    return (
        <View accessibilityRole="alert" style={[styles.alert, { backgroundColor: s.bg }, style]}>
            <View style={[styles.dot, { backgroundColor: s.dot }]} />
            <View style={styles.content}>
                <Text style={styles.title}>{title}</Text>
                {children != null && <Text style={styles.body}>{children}</Text>}
            </View>
        </View>
    );
}

const styles = StyleSheet.create({
    alert: {
        flexDirection: 'row',
        alignItems: 'flex-start',
        gap: 11,
        borderRadius: radius.md,
        paddingVertical: 14,
        paddingHorizontal: 15,
    },
    dot: {
        marginTop: 3,
        width: 9,
        height: 9,
        borderRadius: 4.5,
        flexShrink: 0,
    },
    content: {
        flex: 1,
        minWidth: 0,
    },
    title: {
        fontSize: 13,
        fontFamily: fonts.sans.native.semibold,
        color: colors.ink,
    },
    body: {
        marginTop: 3,
        fontSize: 11.5,
        lineHeight: 16.5, // ~1.45
        fontFamily: fonts.sans.native.regular,
        color: colors.muted,
    },
});
