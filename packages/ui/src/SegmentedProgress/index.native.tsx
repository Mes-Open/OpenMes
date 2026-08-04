/**
 * SegmentedProgress — native twin of index.web.jsx, identical props API.
 *
 * Same state ramp: the whole filled run shares one colour, and that colour is
 * the status (accent → downtime → running). See the web twin for why a
 * positional ramp was rejected.
 */
import React from 'react';
import { StyleSheet, Text, View, type StyleProp, type ViewStyle } from 'react-native';

import { colors, fonts } from '../tokens';

/**
 * Work that has started but not yet earned a visible sliver still gets one:
 * an untouched meter means "nobody has begun", which is a different fact.
 */
const MIN_VISIBLE_FRACTION = 0.25;

function partialFraction(value: number, fraction: number, fullBars: number) {
    if (fraction <= 0) return 0;
    if (fullBars === 0 && value > 0) return Math.max(fraction, MIN_VISIBLE_FRACTION);
    return fraction;
}

/** Ramp stops, highest first — the first stop the ratio reaches wins. */
const RAMP: { from: number; fill: string }[] = [
    { from: 0.85, fill: colors.running },
    { from: 0.45, fill: colors.downtime },
    { from: 0, fill: colors.accent },
];

export interface SegmentedProgressProps {
    /** Current amount, in the same unit as `max`. */
    value?: number;
    /** Value that counts as complete. */
    max?: number;
    /** How many bars to draw — independent of `max`, so percentages work too. */
    segments?: number;
    /** Render a compact "6/8" after the bars (green alone is not readable for
     *  everyone). */
    showValue?: boolean;
    /** Accessible name — a bare meter announces a number with no subject. */
    label?: string;
    style?: StyleProp<ViewStyle>;
}

export function SegmentedProgress({
    value = 0,
    max = 8,
    segments = 8,
    showValue = false,
    label,
    style,
}: SegmentedProgressProps) {
    const ratio = max > 0 ? Math.min(1, Math.max(0, value / max)) : 0;
    // The bar the value lands *inside* is filled by the remainder, so eight
    // segments can read a value that isn't a multiple of an eighth (1/3 lights
    // two bars and two-thirds of the third). Mirrors the web twin.
    const exact = ratio * segments;
    const full = Math.floor(exact);
    const partial = partialFraction(value, exact - full, full);
    const fill = (RAMP.find((stop) => ratio >= stop.from) ?? RAMP[RAMP.length - 1]).fill;

    return (
        <View style={[styles.row, style]}>
            <View
                accessibilityRole="progressbar"
                accessibilityLabel={label}
                accessibilityValue={{ min: 0, max, now: value }}
                style={styles.bars}
            >
                {Array.from({ length: segments }, (_, i) => (
                    <View
                        key={i}
                        style={[styles.bar, { backgroundColor: i < full ? fill : colors.faintest }]}
                    >
                        {i === full && partial > 0 && (
                            // Fills from the bottom, as on web: a row of them reads
                            // as one rising meter rather than eight gauges.
                            <View
                                style={[
                                    styles.partial,
                                    { backgroundColor: fill, height: `${partial * 100}%` as `${number}%` },
                                ]}
                            />
                        )}
                    </View>
                ))}
            </View>
            {showValue && (
                <Text style={styles.value}>
                    {value}/{max}
                </Text>
            )}
        </View>
    );
}

const styles = StyleSheet.create({
    row: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 8,
    },
    bars: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 3,
    },
    bar: {
        width: 3,
        height: 16,
        borderRadius: 99,
        overflow: 'hidden',
        justifyContent: 'flex-end',
    },
    partial: {
        width: '100%',
        borderRadius: 99,
    },
    value: {
        fontSize: 11,
        color: colors.muted,
        fontFamily: fonts.mono.native.regular,
    },
});

export default SegmentedProgress;
