/**
 * ActionSheet — Geist White system (design ref: OpenMES Components.dc.html §10②).
 * Native-only (no web twin): iOS-style bottom-floating translucent option group
 * with hairline separators and a separate Cancel card.
 */
import React from 'react';
import {
    Animated,
    Modal,
    Pressable,
    StyleSheet,
    Text,
    View,
} from 'react-native';

import { colors, fonts } from '../tokens';

export interface ActionSheetOption {
    key: string;
    label: string;
    destructive?: boolean;
    onSelect?: () => void;
}

export interface ActionSheetProps {
    open: boolean;
    onClose: () => void;
    /** Mono header above the options, e.g. "WO-2026-007 ACTIONS". */
    title?: string;
    options: ActionSheetOption[];
    cancelLabel?: string;
}

export function ActionSheet({ open, onClose, title, options, cancelLabel = 'Cancel' }: ActionSheetProps) {
    const [visible, setVisible] = React.useState(open);
    const translateY = React.useRef(new Animated.Value(320)).current;
    const scrimOpacity = React.useRef(new Animated.Value(0)).current;

    React.useEffect(() => {
        if (open) {
            setVisible(true);
            Animated.parallel([
                Animated.timing(translateY, { toValue: 0, duration: 220, useNativeDriver: true }),
                Animated.timing(scrimOpacity, { toValue: 1, duration: 220, useNativeDriver: true }),
            ]).start();
        } else if (visible) {
            Animated.parallel([
                Animated.timing(translateY, { toValue: 320, duration: 180, useNativeDriver: true }),
                Animated.timing(scrimOpacity, { toValue: 0, duration: 180, useNativeDriver: true }),
            ]).start(({ finished }) => {
                if (finished) setVisible(false);
            });
        }
    }, [open, visible, translateY, scrimOpacity]);

    const select = (option: ActionSheetOption) => {
        option.onSelect?.();
        onClose();
    };

    return (
        <Modal transparent statusBarTranslucent visible={visible} animationType="none" onRequestClose={onClose}>
            <View style={styles.root}>
                <Animated.View style={[styles.scrim, { opacity: scrimOpacity }]}>
                    <Pressable accessibilityRole="button" style={StyleSheet.absoluteFill} onPress={onClose} />
                </Animated.View>
                <Animated.View
                    accessibilityViewIsModal
                    style={[styles.stack, { transform: [{ translateY }] }]}
                >
                    <View style={styles.group}>
                        {title != null && (
                            <>
                                <Text style={styles.header}>{title}</Text>
                                <View style={styles.separator} />
                            </>
                        )}
                        {options.map((option, index) => (
                            <React.Fragment key={option.key}>
                                {index > 0 && <View style={styles.separator} />}
                                <Pressable
                                    accessibilityRole="button"
                                    onPress={() => select(option)}
                                    style={({ pressed }) => [styles.option, pressed && styles.pressed]}
                                >
                                    <Text style={[styles.optionLabel, option.destructive && styles.destructiveLabel]}>
                                        {option.label}
                                    </Text>
                                </Pressable>
                            </React.Fragment>
                        ))}
                    </View>
                    <Pressable
                        accessibilityRole="button"
                        onPress={onClose}
                        style={({ pressed }) => [styles.cancel, pressed && styles.pressed]}
                    >
                        <Text style={styles.cancelLabel}>{cancelLabel}</Text>
                    </Pressable>
                </Animated.View>
            </View>
        </Modal>
    );
}

const styles = StyleSheet.create({
    root: {
        flex: 1,
        justifyContent: 'flex-end',
    },
    scrim: {
        ...StyleSheet.absoluteFillObject,
        backgroundColor: colors.scrim,
    },
    stack: {
        marginHorizontal: 10,
        marginBottom: 10,
    },
    group: {
        backgroundColor: 'rgba(255, 255, 255, 0.86)',
        borderRadius: 14,
        overflow: 'hidden',
        marginBottom: 8,
    },
    header: {
        textAlign: 'center',
        fontSize: 10,
        fontFamily: fonts.mono.native.regular,
        letterSpacing: 0.6,
        color: colors.faint,
        padding: 13,
    },
    separator: {
        height: 1,
        backgroundColor: colors.line2,
    },
    option: {
        padding: 15,
        alignItems: 'center',
    },
    optionLabel: {
        fontSize: 15,
        fontFamily: fonts.sans.native.regular,
        color: colors.ink,
    },
    destructiveLabel: {
        fontFamily: fonts.sans.native.semibold,
        color: colors.blocked,
    },
    cancel: {
        backgroundColor: colors.card,
        borderRadius: 14,
        padding: 15,
        alignItems: 'center',
    },
    cancelLabel: {
        fontSize: 15,
        fontFamily: fonts.sans.native.semibold,
        color: colors.ink,
    },
    pressed: {
        opacity: 0.7,
    },
});
