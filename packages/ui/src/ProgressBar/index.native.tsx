/**
 * ProgressBar — Geist White system (design ref: OpenMES Components.dc.html §06).
 * Native twin of index.web.jsx — identical props API.
 */
import React from 'react';
import { StyleSheet, View, type StyleProp, type ViewStyle } from 'react-native';

import { colors, radius } from '../tokens';

export interface ProgressBarProps {
    /** 0–100 */
    value: number;
    /** Optional fill-color override (defaults to accent). */
    color?: string;
    style?: StyleProp<ViewStyle>;
}

export function ProgressBar({ value, color, style }: ProgressBarProps) {
    const pct = Math.min(100, Math.max(0, value));
    return (
        <View
            accessibilityRole="progressbar"
            accessibilityValue={{ min: 0, max: 100, now: pct }}
            style={[styles.track, style]}
        >
            <View style={[styles.fill, { width: `${pct}%` as `${number}%`, backgroundColor: color ?? colors.accent }]} />
        </View>
    );
}

const styles = StyleSheet.create({
    track: {
        height: 7,
        borderRadius: radius.pill,
        backgroundColor: colors.chip,
        overflow: 'hidden',
    },
    fill: {
        height: '100%',
        borderRadius: radius.pill,
    },
});
