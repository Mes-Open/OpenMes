/**
 * Skeleton — Geist White system. Native twin of index.web.jsx — identical props
 * API. The web `om-pulse` CSS animation (opacity 1 → .3 → 1 over 2s) is mirrored
 * with an Animated loop so placeholders breathe the same on both platforms.
 */
import React, { useEffect, useRef } from 'react';
import { Animated, type DimensionValue, type StyleProp, type ViewStyle } from 'react-native';

import { colors } from '../tokens';

export interface SkeletonProps {
    width?: DimensionValue;
    height?: number;
    radius?: number;
    circle?: boolean;
    style?: StyleProp<ViewStyle>;
}

export function Skeleton({ width, height = 12, radius = 6, circle = false, style }: SkeletonProps) {
    const opacity = useRef(new Animated.Value(1)).current;

    useEffect(() => {
        const loop = Animated.loop(
            Animated.sequence([
                Animated.timing(opacity, { toValue: 0.3, duration: 1000, useNativeDriver: true }),
                Animated.timing(opacity, { toValue: 1, duration: 1000, useNativeDriver: true }),
            ]),
        );
        loop.start();
        return () => loop.stop();
    }, [opacity]);

    const resolvedWidth: DimensionValue = circle ? height : (width ?? '100%');

    return (
        <Animated.View
            style={[
                {
                    width: resolvedWidth,
                    height,
                    borderRadius: circle ? height / 2 : radius,
                    backgroundColor: colors.line2,
                    opacity,
                },
                style,
            ]}
        />
    );
}
