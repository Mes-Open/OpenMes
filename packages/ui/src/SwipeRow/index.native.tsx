/**
 * SwipeRow — Geist White system (design ref: OpenMES Components.dc.html §10⑤).
 * Native-only (no web twin): row that swipes left (PanResponder + Animated) to
 * reveal 58px-wide action buttons; taps pass through unless horizontal drag dominates.
 */
import React from 'react';
import {
    Animated,
    PanResponder,
    Pressable,
    StyleSheet,
    Text,
    View,
    type StyleProp,
    type ViewStyle,
} from 'react-native';

import { colors, fonts, radius } from '../tokens';

const ACTION_WIDTH = 58;

export interface SwipeRowAction {
    key: string;
    label: string;
    /** Button background, e.g. colors.downtime (Hold) or colors.blocked (Block). */
    color: string;
    onPress?: () => void;
}

export interface SwipeRowProps {
    /** Row content; rendered on a card surface that slides over the actions. */
    children: React.ReactNode;
    actions: SwipeRowAction[];
    onOpen?: () => void;
    onClose?: () => void;
    style?: StyleProp<ViewStyle>;
}

function clamp(value: number, min: number, max: number): number {
    return Math.min(max, Math.max(min, value));
}

export function SwipeRow({ children, actions, onOpen, onClose, style }: SwipeRowProps) {
    const actionsWidth = actions.length * ACTION_WIDTH;
    const translateX = React.useRef(new Animated.Value(0)).current;
    const currentX = React.useRef(0);
    const startX = React.useRef(0);
    const isOpen = React.useRef(false);
    const onOpenRef = React.useRef(onOpen);
    const onCloseRef = React.useRef(onClose);
    onOpenRef.current = onOpen;
    onCloseRef.current = onClose;

    React.useEffect(() => {
        const id = translateX.addListener(({ value }) => {
            currentX.current = value;
        });
        return () => translateX.removeListener(id);
    }, [translateX]);

    const settle = React.useCallback(
        (toOpen: boolean) => {
            Animated.spring(translateX, {
                toValue: toOpen ? -actionsWidth : 0,
                bounciness: 0,
                useNativeDriver: false,
            }).start();
            if (toOpen !== isOpen.current) {
                isOpen.current = toOpen;
                if (toOpen) onOpenRef.current?.();
                else onCloseRef.current?.();
            }
        },
        [actionsWidth, translateX],
    );

    const panResponder = React.useMemo(
        () =>
            PanResponder.create({
                onMoveShouldSetPanResponder: (_evt, gesture) =>
                    Math.abs(gesture.dx) > 6 && Math.abs(gesture.dx) > Math.abs(gesture.dy),
                onPanResponderGrant: () => {
                    startX.current = currentX.current;
                },
                onPanResponderMove: (_evt, gesture) => {
                    translateX.setValue(clamp(startX.current + gesture.dx, -actionsWidth, 0));
                },
                onPanResponderRelease: (_evt, gesture) => {
                    const end = startX.current + gesture.dx;
                    const toOpen =
                        gesture.vx < -0.25 ? true : gesture.vx > 0.25 ? false : end < -actionsWidth / 2;
                    settle(toOpen);
                },
                onPanResponderTerminate: () => {
                    settle(currentX.current < -actionsWidth / 2);
                },
            }),
        [actionsWidth, settle, translateX],
    );

    return (
        <View style={[styles.container, style]}>
            <View style={styles.actions}>
                {actions.map((action) => (
                    <Pressable
                        key={action.key}
                        accessibilityRole="button"
                        accessibilityLabel={action.label}
                        onPress={() => {
                            action.onPress?.();
                            settle(false);
                        }}
                        style={({ pressed }) => [
                            styles.action,
                            { backgroundColor: action.color },
                            pressed && styles.actionPressed,
                        ]}
                    >
                        <Text style={styles.actionLabel}>{action.label}</Text>
                    </Pressable>
                ))}
            </View>
            <Animated.View style={[styles.row, { transform: [{ translateX }] }]} {...panResponder.panHandlers}>
                <Pressable
                    onPress={() => {
                        if (isOpen.current) settle(false);
                    }}
                >
                    {children}
                </Pressable>
            </Animated.View>
        </View>
    );
}

const styles = StyleSheet.create({
    container: {
        borderRadius: radius.md,
        borderWidth: 1,
        borderColor: colors.line2,
        overflow: 'hidden',
    },
    actions: {
        position: 'absolute',
        top: 0,
        bottom: 0,
        right: 0,
        flexDirection: 'row',
    },
    action: {
        width: ACTION_WIDTH,
        alignItems: 'center',
        justifyContent: 'center',
    },
    actionPressed: {
        opacity: 0.85,
    },
    actionLabel: {
        fontSize: 11,
        fontFamily: fonts.sans.native.semibold,
        color: '#FFFFFF',
    },
    row: {
        backgroundColor: colors.card,
    },
});
