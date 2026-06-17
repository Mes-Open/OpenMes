/**
 * OnlineDot — Geist White system (design ref: OpenMES Components.dc.html §06).
 * Native twin of index.web.jsx — identical props API.
 */
import React, { useEffect, useRef } from 'react';
import { Animated, StyleSheet, Text, View, type StyleProp, type ViewStyle } from 'react-native';

import { colors, fonts } from '../tokens';

export interface OnlineDotProps {
    /** Display text (already translated), e.g. "ONLINE". */
    label: string;
    pulse?: boolean;
    style?: StyleProp<ViewStyle>;
}

export function OnlineDot({ label, pulse = false, style }: OnlineDotProps) {
    const opacity = useRef(new Animated.Value(1)).current;

    useEffect(() => {
        if (!pulse) return;
        const loop = Animated.loop(
            Animated.sequence([
                Animated.timing(opacity, { toValue: 0.3, duration: 1000, useNativeDriver: true }),
                Animated.timing(opacity, { toValue: 1, duration: 1000, useNativeDriver: true }),
            ]),
        );
        loop.start();
        return () => loop.stop();
    }, [pulse, opacity]);

    return (
        <View accessibilityRole="text" style={[styles.row, style]}>
            <Animated.View style={[styles.dot, pulse && { opacity }]} />
            <Text style={styles.label}>{label}</Text>
        </View>
    );
}

const styles = StyleSheet.create({
    row: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 5,
        alignSelf: 'flex-start',
    },
    dot: {
        width: 7,
        height: 7,
        borderRadius: 3.5,
        backgroundColor: colors.running,
    },
    label: {
        fontFamily: fonts.mono.native.regular,
        fontSize: 10,
        color: colors.running,
    },
});
