/**
 * Toast — Geist White system (design ref: OpenMES Components.dc.html §09).
 * Native twin of index.web.jsx — identical props API. The provider renders an
 * absolute top-right stack inside its view; pass `topInset` for the safe area.
 */
import React, { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react';
import { Pressable, StyleSheet, Text, View, type StyleProp, type ViewStyle } from 'react-native';

import { colors, fonts, radius } from '../tokens';

export type ToastSeverity = 'success' | 'warning' | 'error';

const severityColor: Record<ToastSeverity, string> = {
    success: colors.running,
    warning: colors.downtime,
    error: colors.blocked,
};

export interface ToastProps {
    severity: ToastSeverity;
    title: string;
    body?: string;
    onDismiss?: () => void;
    dismissLabel?: string;
    style?: StyleProp<ViewStyle>;
}

export function Toast({ severity, title, body, onDismiss, dismissLabel, style }: ToastProps) {
    const clr = severityColor[severity];
    return (
        <View accessibilityRole="alert" style={[styles.card, { borderLeftColor: clr }, style]}>
            <View style={[styles.dot, { backgroundColor: clr }]} />
            <View style={styles.content}>
                <Text style={styles.title}>{title}</Text>
                {body != null && <Text style={styles.body}>{body}</Text>}
            </View>
            <Pressable accessibilityRole="button" accessibilityLabel={dismissLabel} hitSlop={8} onPress={onDismiss}>
                <Text style={styles.close}>×</Text>
            </Pressable>
        </View>
    );
}

export interface ToastOptions {
    severity: ToastSeverity;
    title: string;
    body?: string;
    duration?: number;
}

type ToastFn = (options: ToastOptions) => void;

interface ToastEntry {
    id: number;
    severity: ToastSeverity;
    title: string;
    body?: string;
}

const ToastContext = createContext<ToastFn | null>(null);

export interface ToastProviderProps {
    children: React.ReactNode;
    /** Distance of the stack from the top — bump for the safe area. */
    topInset?: number;
    dismissLabel?: string;
}

export function ToastProvider({ children, topInset = 18, dismissLabel }: ToastProviderProps) {
    const [toasts, setToasts] = useState<ToastEntry[]>([]);
    const idRef = useRef(0);
    const timersRef = useRef(new Map<number, ReturnType<typeof setTimeout>>());

    const dismiss = useCallback((id: number) => {
        const timer = timersRef.current.get(id);
        if (timer) {
            clearTimeout(timer);
            timersRef.current.delete(id);
        }
        setToasts((list) => list.filter((t) => t.id !== id));
    }, []);

    const toast = useCallback<ToastFn>(
        ({ severity, title, body, duration = 4000 }) => {
            const id = ++idRef.current;
            setToasts((list) => [...list, { id, severity, title, body }]);
            if (duration > 0) timersRef.current.set(id, setTimeout(() => dismiss(id), duration));
        },
        [dismiss],
    );

    useEffect(() => {
        const timers = timersRef.current;
        return () => timers.forEach((timer) => clearTimeout(timer));
    }, []);

    return (
        <ToastContext.Provider value={toast}>
            <View style={styles.root}>
                {children}
                <View pointerEvents="box-none" style={[styles.stack, { top: topInset }]}>
                    {toasts.map((t) => (
                        <Toast
                            key={t.id}
                            severity={t.severity}
                            title={t.title}
                            body={t.body}
                            dismissLabel={dismissLabel}
                            onDismiss={() => dismiss(t.id)}
                        />
                    ))}
                </View>
            </View>
        </ToastContext.Provider>
    );
}

/** Returns the `toast({ severity, title, body, duration })` function. */
export function useToast(): ToastFn {
    const toast = useContext(ToastContext);
    if (!toast) throw new Error('useToast must be used within a <ToastProvider>');
    return toast;
}

const styles = StyleSheet.create({
    root: {
        flex: 1,
    },
    stack: {
        position: 'absolute',
        right: 18,
        width: 300,
        maxWidth: '90%',
        gap: 11,
    },
    card: {
        flexDirection: 'row',
        alignItems: 'flex-start',
        gap: 11,
        backgroundColor: colors.card,
        borderWidth: 1,
        borderColor: colors.line,
        borderLeftWidth: 3,
        borderRadius: radius.md,
        paddingVertical: 13,
        paddingHorizontal: 15,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 14 },
        shadowRadius: 24,
        shadowOpacity: 0.18,
        elevation: 8,
    },
    dot: {
        marginTop: 2,
        width: 10,
        height: 10,
        borderRadius: 5,
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
        fontSize: 10,
        fontFamily: fonts.mono.native.regular,
        color: colors.muted,
    },
    close: {
        fontSize: 15,
        lineHeight: 16,
        color: colors.faint,
    },
});
