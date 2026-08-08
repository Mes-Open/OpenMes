/**
 * Stepper — native twin of index.web.jsx, identical props API.
 *
 * Same reading: a read-only run of steps, with the connector above a step
 * coloured by the step before it (the line is progress between two points, and
 * it has only been travelled once the earlier one is done).
 */
import React from 'react';
import { StyleSheet, Text, View, type StyleProp, type ViewStyle } from 'react-native';

import { Icon } from '../Icon';
import { colors, fonts } from '../tokens';

export type StepStatus = 'done' | 'active' | 'pending' | 'blocked';

/** Filled discs, as on web: a ring of hairline circles reads as bullets. */
const TONE: Record<StepStatus, { bg: string; fg: string; line: string; title: string }> = {
    done: { bg: colors.running, fg: '#FFFFFF', line: colors.running, title: colors.ink },
    active: { bg: colors.accent, fg: '#FFFFFF', line: colors.line2, title: colors.ink },
    pending: { bg: colors.chip, fg: colors.faint, line: colors.line2, title: colors.muted },
    blocked: { bg: colors.blocked, fg: '#FFFFFF', line: colors.line2, title: colors.ink },
};

const SIZES = {
    sm: { dot: 24, gap: 20, text: 12.5, desc: 11.5, icon: 13, line: 2 },
    md: { dot: 28, gap: 24, text: 13.5, desc: 12, icon: 15, line: 2 },
} as const;

export interface StepperStep {
    key?: string;
    title: string;
    description?: string;
    status?: StepStatus;
    /** Lucide name shown instead of a tick on a done step. */
    icon?: string;
    /** Replaces the ordinal inside the indicator. */
    label?: string;
    meta?: React.ReactNode;
}

export interface StepperProps {
    steps?: StepperStep[];
    size?: keyof typeof SIZES;
    style?: StyleProp<ViewStyle>;
}

export function Stepper({ steps = [], size = 'md', style }: StepperProps) {
    const s = SIZES[size] ?? SIZES.md;

    return (
        <View style={style}>
            {steps.map((step, i) => {
                const tone = TONE[step.status ?? 'pending'] ?? TONE.pending;
                const isLast = i === steps.length - 1;

                return (
                    <View key={step.key ?? String(i)} style={[styles.row, { paddingBottom: isLast ? 0 : s.gap }]}>
                        {!isLast && (
                            <View
                                style={[
                                    styles.line,
                                    { left: (s.dot - s.line) / 2, top: s.dot + 2, width: s.line, backgroundColor: tone.line },
                                ]}
                            />
                        )}
                        <View
                            style={[
                                styles.dot,
                                { width: s.dot, height: s.dot, borderRadius: s.dot / 2, backgroundColor: tone.bg },
                            ]}
                        >
                            {step.status === 'done' ? (
                                <Icon name={step.icon ?? 'check'} size={s.icon} color={tone.fg} />
                            ) : (
                                <Text style={[styles.ordinal, { color: tone.fg }]}>{step.label ?? i + 1}</Text>
                            )}
                        </View>
                        <View style={styles.body}>
                            <Text numberOfLines={1} style={[styles.title, { fontSize: s.text, color: tone.title }]}>
                                {step.title}
                            </Text>
                            {step.description != null && (
                                <Text numberOfLines={1} style={[styles.desc, { fontSize: s.desc }]}>
                                    {step.description}
                                </Text>
                            )}
                        </View>
                        {step.meta}
                    </View>
                );
            })}
        </View>
    );
}

const styles = StyleSheet.create({
    row: {
        flexDirection: 'row',
        alignItems: 'flex-start',
        gap: 12,
        position: 'relative',
    },
    line: {
        position: 'absolute',
        bottom: 0,
        width: 1,
    },
    dot: {
        alignItems: 'center',
        justifyContent: 'center',
    },
    ordinal: {
        fontSize: 11,
        fontFamily: fonts.mono.native.semibold,
    },
    body: {
        flex: 1,
        minWidth: 0,
    },
    title: {
        fontFamily: fonts.sans.native.semibold,
    },
    desc: {
        color: colors.faint,
        fontFamily: fonts.sans.native.regular,
    },
});

export default Stepper;
