/**
 * StatusPill — Geist White system (design ref: OpenMES Components.dc.html §06).
 * Native twin of index.web.jsx — identical props API.
 */
import React, { useEffect, useRef } from 'react';
import { Animated, StyleSheet, Text, View, type StyleProp, type ViewStyle } from 'react-native';

import { fonts, radius, statusColors, type StatusKey } from '../tokens';

export interface StatusPillProps {
    status: StatusKey;
    /** Display text (already translated), e.g. "RUNNING". */
    label: string;
    pulse?: boolean;
    style?: StyleProp<ViewStyle>;
}

export function StatusPill({ status, label, pulse = status === 'running', style }: StatusPillProps) {
    const { fg, bg } = statusColors[status];
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
        <View accessibilityRole="text" style={[styles.pill, { backgroundColor: bg }, style]}>
            {pulse && <Animated.View style={[styles.dot, { backgroundColor: fg, opacity }]} />}
            <Text style={[styles.label, { color: fg }]}>{label}</Text>
        </View>
    );
}

const styles = StyleSheet.create({
    pill: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 5,
        borderRadius: radius.pill,
        paddingVertical: 4,
        paddingHorizontal: 10,
        alignSelf: 'flex-start',
    },
    dot: {
        width: 6,
        height: 6,
        borderRadius: 3,
    },
    label: {
        fontFamily: fonts.mono.native.regular,
        fontSize: 9.5,
        letterSpacing: 0.57, // 0.06em at 9.5px
        textTransform: 'uppercase',
    },
});
