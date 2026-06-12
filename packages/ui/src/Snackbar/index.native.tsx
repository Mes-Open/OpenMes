/**
 * Snackbar — Geist White system (design ref: OpenMES Components.dc.html §10⑤).
 * Native-only (no web twin): ink pill anchored to the bottom with an optional
 * accent action. `SnackbarHost` + `useSnackbar()` provide an imperative show() API.
 */
import React from 'react';
import {
    Animated,
    Pressable,
    StyleSheet,
    Text,
    View,
    type StyleProp,
    type ViewStyle,
} from 'react-native';

import { colors, fonts, radius } from '../tokens';

export interface SnackbarProps {
    message: string;
    actionLabel?: string;
    onAction?: () => void;
    style?: StyleProp<ViewStyle>;
}

/** Presentational bar — usually rendered for you by SnackbarHost. */
export function Snackbar({ message, actionLabel, onAction, style }: SnackbarProps) {
    return (
        <View accessibilityRole="alert" accessibilityLiveRegion="polite" style={[styles.bar, style]}>
            <Text style={styles.message}>{message}</Text>
            {actionLabel != null && (
                <Pressable accessibilityRole="button" hitSlop={8} onPress={onAction}>
                    <Text style={styles.action}>{actionLabel}</Text>
                </Pressable>
            )}
        </View>
    );
}

export interface SnackbarShowOptions {
    message: string;
    actionLabel?: string;
    onAction?: () => void;
    /** Auto-dismiss delay in ms. Default 4000. */
    duration?: number;
}

interface SnackbarContextValue {
    show: (options: SnackbarShowOptions) => void;
}

const SnackbarContext = React.createContext<SnackbarContextValue | null>(null);

export function useSnackbar(): SnackbarContextValue {
    const ctx = React.useContext(SnackbarContext);
    if (!ctx) throw new Error('useSnackbar must be used inside a <SnackbarHost>');
    return ctx;
}

export interface SnackbarHostProps {
    children: React.ReactNode;
    /** Distance from the bottom edge. Default 14 (raise above tab bars as needed). */
    bottom?: number;
}

export function SnackbarHost({ children, bottom = 14 }: SnackbarHostProps) {
    const [current, setCurrent] = React.useState<SnackbarShowOptions | null>(null);
    const anim = React.useRef(new Animated.Value(0)).current;
    const timer = React.useRef<ReturnType<typeof setTimeout> | null>(null);

    const dismiss = React.useCallback(() => {
        if (timer.current) {
            clearTimeout(timer.current);
            timer.current = null;
        }
        Animated.timing(anim, { toValue: 0, duration: 180, useNativeDriver: true }).start(({ finished }) => {
            if (finished) setCurrent(null);
        });
    }, [anim]);

    const show = React.useCallback(
        (options: SnackbarShowOptions) => {
            if (timer.current) clearTimeout(timer.current);
            setCurrent(options);
            Animated.timing(anim, { toValue: 1, duration: 220, useNativeDriver: true }).start();
            timer.current = setTimeout(dismiss, options.duration ?? 4000);
        },
        [anim, dismiss],
    );

    React.useEffect(
        () => () => {
            if (timer.current) clearTimeout(timer.current);
        },
        [],
    );

    const ctx = React.useMemo(() => ({ show }), [show]);

    return (
        <SnackbarContext.Provider value={ctx}>
            <View style={styles.host}>
                {children}
                {current != null && (
                    <Animated.View
                        pointerEvents="box-none"
                        style={[
                            styles.overlay,
                            {
                                bottom,
                                opacity: anim,
                                transform: [
                                    { translateY: anim.interpolate({ inputRange: [0, 1], outputRange: [16, 0] }) },
                                ],
                            },
                        ]}
                    >
                        <Snackbar
                            message={current.message}
                            actionLabel={current.actionLabel}
                            onAction={() => {
                                current.onAction?.();
                                dismiss();
                            }}
                        />
                    </Animated.View>
                )}
            </View>
        </SnackbarContext.Provider>
    );
}

const styles = StyleSheet.create({
    host: {
        flex: 1,
    },
    overlay: {
        position: 'absolute',
        left: 14,
        right: 14,
    },
    bar: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 12,
        backgroundColor: colors.ink,
        borderRadius: radius.md,
        paddingVertical: 14,
        paddingHorizontal: 16,
    },
    message: {
        flex: 1,
        fontSize: 13,
        fontFamily: fonts.sans.native.regular,
        color: '#FFFFFF',
    },
    action: {
        fontSize: 13,
        fontFamily: fonts.sans.native.semibold,
        color: colors.accent,
    },
});
