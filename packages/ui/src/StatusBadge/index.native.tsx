/**
 * StatusBadge — native twin of index.web.jsx, identical props API.
 *
 * Same rule: green means exactly one thing (successfully finished), every tone
 * carries its own icon so colour is never the only cue, and `ghost` varies the
 * treatment instead of inventing a ninth hue. See the web twin for the full
 * reasoning.
 */
import React from 'react';
import { StyleSheet, Text, View, type StyleProp, type ViewStyle } from 'react-native';

import { Icon } from '../Icon';
import { colors, fonts } from '../tokens';

export type StatusTone = 'neutral' | 'info' | 'active' | 'warn' | 'danger' | 'success' | 'critical' | 'ghost';

/** Tone → token pair. Mirrors the web twin's TONES table. */
const TONES: Record<StatusTone, { fg: string; bg: string; outlined?: boolean }> = {
    neutral: { fg: colors.pending, bg: colors.pendingBg },
    info: { fg: colors.accepted, bg: colors.acceptedBg },
    active: { fg: colors.maint, bg: colors.maintBg },
    warn: { fg: colors.downtime, bg: colors.downtimeBg },
    danger: { fg: colors.blocked, bg: colors.blockedBg },
    success: { fg: colors.running, bg: colors.runningBg },
    critical: { fg: colors.rejected, bg: colors.rejectedBg },
    ghost: { fg: colors.faint, bg: 'transparent', outlined: true },
};

const SIZES = {
    sm: { padV: 2, padH: 8, font: 11, icon: 12, gap: 4 },
    md: { padV: 4, padH: 10, font: 12, icon: 14, gap: 6 },
    lg: { padV: 6, padH: 14, font: 13, icon: 16, gap: 6 },
} as const;

export interface StatusBadgeProps {
    /** Visible text. Callers pass an already-translated label. */
    label?: string;
    /** kebab-case Lucide name, e.g. 'circle-check'. */
    icon?: string;
    tone?: StatusTone;
    /** 'soft' tints the background; 'solid' fills it. */
    variant?: 'soft' | 'solid';
    size?: keyof typeof SIZES;
    showIcon?: boolean;
    style?: StyleProp<ViewStyle>;
}

export function StatusBadge({
    label,
    icon,
    tone = 'neutral',
    variant = 'soft',
    size = 'md',
    showIcon = true,
    style,
}: StatusBadgeProps) {
    const t = TONES[tone] ?? TONES.neutral;
    const s = SIZES[size] ?? SIZES.md;
    const solid = variant === 'solid' && !t.outlined;
    const fg = solid ? '#FFFFFF' : t.fg;

    return (
        <View
            style={[
                styles.chip,
                {
                    paddingVertical: s.padV,
                    paddingHorizontal: s.padH,
                    gap: s.gap,
                    backgroundColor: solid ? t.fg : t.bg,
                    borderColor: t.outlined ? colors.faintest : 'transparent',
                },
                style,
            ]}
        >
            {showIcon && icon && <Icon name={icon} size={s.icon} color={fg} />}
            {label != null && <Text style={[styles.label, { fontSize: s.font, color: fg }]}>{label}</Text>}
        </View>
    );
}

/**
 * StatusDot — the quiet form: a coloured dot plus plain text, for lists where
 * most rows share one status and full chips would bury the exceptions.
 */
export function StatusDot({
    label,
    tone = 'neutral',
    withLabel = true,
    style,
}: {
    label?: string;
    tone?: StatusTone;
    withLabel?: boolean;
    style?: StyleProp<ViewStyle>;
}) {
    const t = TONES[tone] ?? TONES.neutral;

    return (
        <View style={[styles.dotRow, style]}>
            <View
                style={[
                    styles.dot,
                    {
                        backgroundColor: t.outlined ? 'transparent' : t.fg,
                        borderWidth: t.outlined ? 1.5 : 0,
                        borderColor: colors.faintest,
                    },
                ]}
            />
            {withLabel && label != null && <Text style={styles.dotLabel}>{label}</Text>}
        </View>
    );
}

const styles = StyleSheet.create({
    chip: {
        flexDirection: 'row',
        alignItems: 'center',
        alignSelf: 'flex-start',
        borderRadius: 999,
        borderWidth: 1,
    },
    label: {
        fontFamily: fonts.sans.native.medium,
        lineHeight: 17,
    },
    dotRow: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 8,
    },
    dot: {
        width: 8,
        height: 8,
        borderRadius: 4,
    },
    dotLabel: {
        fontSize: 13,
        color: colors.ink,
        fontFamily: fonts.sans.native.regular,
    },
});

export default StatusBadge;
