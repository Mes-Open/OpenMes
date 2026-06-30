/**
 * Drawer — Geist White system (design ref: OpenMES Components.dc.html §11③).
 * Native-only (no web twin): left slide-in panel (74% width) over a scrim with
 * ink-active nav items and optional blocked-red badges.
 */
import React from 'react';
import {
    Animated,
    Modal,
    Pressable,
    StyleSheet,
    Text,
    View,
    useWindowDimensions,
} from 'react-native';

import { colors, fonts, radius } from '../tokens';

export interface DrawerItem {
    key: string;
    label: string;
    icon?: React.ReactNode;
    active?: boolean;
    /** Right-aligned pill badge, e.g. an unread count. */
    badge?: string | number;
    onSelect?: () => void;
}

export interface DrawerProps {
    open: boolean;
    onClose: () => void;
    items: DrawerItem[];
    /** Logo row content at the top of the panel. */
    header?: React.ReactNode;
}

export function Drawer({ open, onClose, items, header }: DrawerProps) {
    const { width: windowWidth } = useWindowDimensions();
    const panelWidth = Math.round(windowWidth * 0.74);
    const [visible, setVisible] = React.useState(open);
    const translateX = React.useRef(new Animated.Value(-panelWidth)).current;
    const scrimOpacity = React.useRef(new Animated.Value(0)).current;

    React.useEffect(() => {
        if (open) {
            setVisible(true);
            Animated.parallel([
                Animated.timing(translateX, { toValue: 0, duration: 220, useNativeDriver: true }),
                Animated.timing(scrimOpacity, { toValue: 1, duration: 220, useNativeDriver: true }),
            ]).start();
        } else if (visible) {
            Animated.parallel([
                Animated.timing(translateX, { toValue: -panelWidth, duration: 180, useNativeDriver: true }),
                Animated.timing(scrimOpacity, { toValue: 0, duration: 180, useNativeDriver: true }),
            ]).start(({ finished }) => {
                if (finished) setVisible(false);
            });
        }
    }, [open, visible, panelWidth, translateX, scrimOpacity]);

    const select = (item: DrawerItem) => {
        item.onSelect?.();
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
                    style={[styles.panel, { width: panelWidth, transform: [{ translateX }] }]}
                >
                    {header != null && <View style={styles.header}>{header}</View>}
                    {items.map((item) => (
                        <Pressable
                            key={item.key}
                            accessibilityRole="button"
                            accessibilityState={{ selected: item.active === true }}
                            onPress={() => select(item)}
                            style={({ pressed }) => [
                                styles.item,
                                item.active && styles.itemActive,
                                pressed && styles.pressed,
                            ]}
                        >
                            {item.icon}
                            <Text style={[styles.itemLabel, item.active && styles.itemLabelActive]}>
                                {item.label}
                            </Text>
                            {item.badge != null && (
                                <View style={styles.badge}>
                                    <Text style={styles.badgeLabel}>{item.badge}</Text>
                                </View>
                            )}
                        </Pressable>
                    ))}
                </Animated.View>
            </View>
        </Modal>
    );
}

const styles = StyleSheet.create({
    root: {
        flex: 1,
    },
    scrim: {
        ...StyleSheet.absoluteFillObject,
        backgroundColor: colors.scrim,
    },
    panel: {
        position: 'absolute',
        left: 0,
        top: 0,
        bottom: 0,
        backgroundColor: colors.card,
        paddingVertical: 18,
        paddingHorizontal: 14,
        shadowColor: '#000000',
        shadowOffset: { width: 14, height: 0 },
        shadowOpacity: 0.4,
        shadowRadius: 20,
        elevation: 18,
    },
    header: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 9,
        paddingTop: 4,
        paddingHorizontal: 6,
        paddingBottom: 16,
    },
    item: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 10,
        paddingVertical: 9,
        paddingHorizontal: 10,
        borderRadius: radius.sm,
        marginBottom: 3,
    },
    itemActive: {
        backgroundColor: colors.ink,
    },
    itemLabel: {
        fontSize: 13,
        fontFamily: fonts.sans.native.regular,
        color: colors.muted,
    },
    itemLabelActive: {
        fontFamily: fonts.sans.native.medium,
        color: '#FFFFFF',
    },
    badge: {
        marginLeft: 'auto',
        backgroundColor: colors.blocked,
        borderRadius: radius.pill,
        paddingVertical: 1,
        paddingHorizontal: 7,
    },
    badgeLabel: {
        fontSize: 10,
        fontFamily: fonts.mono.native.regular,
        color: '#FFFFFF',
    },
    pressed: {
        opacity: 0.8,
    },
});
