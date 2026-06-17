/**
 * BottomSheet — Geist White system (design ref: OpenMES Components.dc.html §10①).
 * Native-only (no web twin): RN Modal sheet sliding from the bottom over a scrim,
 * with a drag handle that dismisses on pull-down.
 */
import React from 'react';
import {
    Animated,
    Modal,
    PanResponder,
    Pressable,
    StyleSheet,
    Text,
    View,
    useWindowDimensions,
} from 'react-native';

import { colors, fonts, radius } from '../tokens';

export interface BottomSheetProps {
    open: boolean;
    onClose: () => void;
    title?: string;
    subtitle?: string;
    children?: React.ReactNode;
    /** Pinned area under the content, e.g. a confirm button. */
    footer?: React.ReactNode;
}

export function BottomSheet({ open, onClose, title, subtitle, children, footer }: BottomSheetProps) {
    const { height: windowHeight } = useWindowDimensions();
    const [visible, setVisible] = React.useState(open);
    const translateY = React.useRef(new Animated.Value(windowHeight)).current;
    const scrimOpacity = React.useRef(new Animated.Value(0)).current;
    const onCloseRef = React.useRef(onClose);
    onCloseRef.current = onClose;

    React.useEffect(() => {
        if (open) {
            setVisible(true);
            Animated.parallel([
                Animated.timing(translateY, { toValue: 0, duration: 240, useNativeDriver: false }),
                Animated.timing(scrimOpacity, { toValue: 1, duration: 240, useNativeDriver: false }),
            ]).start();
        } else if (visible) {
            Animated.parallel([
                Animated.timing(translateY, { toValue: windowHeight, duration: 200, useNativeDriver: false }),
                Animated.timing(scrimOpacity, { toValue: 0, duration: 200, useNativeDriver: false }),
            ]).start(({ finished }) => {
                if (finished) setVisible(false);
            });
        }
    }, [open, visible, windowHeight, translateY, scrimOpacity]);

    const panResponder = React.useRef(
        PanResponder.create({
            onStartShouldSetPanResponder: () => true,
            onMoveShouldSetPanResponder: (_evt, gesture) => Math.abs(gesture.dy) > 4,
            onPanResponderMove: (_evt, gesture) => {
                if (gesture.dy > 0) translateY.setValue(gesture.dy);
            },
            onPanResponderRelease: (_evt, gesture) => {
                if (gesture.dy > 80 || gesture.vy > 0.5) {
                    onCloseRef.current();
                } else {
                    Animated.spring(translateY, { toValue: 0, bounciness: 4, useNativeDriver: false }).start();
                }
            },
            onPanResponderTerminate: () => {
                Animated.spring(translateY, { toValue: 0, bounciness: 4, useNativeDriver: false }).start();
            },
        }),
    ).current;

    return (
        <Modal transparent statusBarTranslucent visible={visible} animationType="none" onRequestClose={onClose}>
            <View style={styles.root}>
                <Animated.View style={[styles.scrim, { opacity: scrimOpacity }]}>
                    <Pressable accessibilityRole="button" style={StyleSheet.absoluteFill} onPress={onClose} />
                </Animated.View>
                <Animated.View
                    accessibilityViewIsModal
                    style={[styles.sheet, { transform: [{ translateY }] }]}
                >
                    <View style={styles.handleArea} {...panResponder.panHandlers}>
                        <View style={styles.handle} />
                    </View>
                    {title != null && <Text style={styles.title}>{title}</Text>}
                    {subtitle != null && <Text style={styles.subtitle}>{subtitle}</Text>}
                    {children}
                    {footer != null && <View style={styles.footer}>{footer}</View>}
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
    sheet: {
        backgroundColor: colors.card,
        borderTopLeftRadius: radius.sheet,
        borderTopRightRadius: radius.sheet,
        paddingTop: 10,
        paddingHorizontal: 18,
        paddingBottom: 20,
        shadowColor: '#000000',
        shadowOffset: { width: 0, height: -12 },
        shadowOpacity: 0.4,
        shadowRadius: 20,
        elevation: 18,
    },
    handleArea: {
        alignItems: 'center',
        paddingBottom: 16,
    },
    handle: {
        width: 38,
        height: 4,
        borderRadius: 4,
        backgroundColor: colors.faintest,
    },
    title: {
        fontSize: 15,
        fontFamily: fonts.sans.native.semibold,
        color: colors.ink,
        marginBottom: 3,
    },
    subtitle: {
        fontSize: 10,
        fontFamily: fonts.mono.native.regular,
        color: colors.faint,
        marginBottom: 16,
    },
    footer: {
        marginTop: 16,
    },
});
