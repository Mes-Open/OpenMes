/**
 * ActionMenu — Geist White system (design ref: OpenMES Components.dc.html §07).
 * Native twin of index.web.jsx — identical props API. Opens a transparent
 * full-screen RN Modal with the menu card anchored below the measured trigger.
 */
import React, { useRef, useState } from 'react';
import {
    Dimensions,
    Modal,
    Pressable,
    StyleSheet,
    Text,
    View,
    type StyleProp,
    type ViewStyle,
} from 'react-native';

import { colors, fonts, radius } from '../tokens';

export interface ActionMenuItem {
    key?: string;
    label?: string;
    destructive?: boolean;
    disabled?: boolean;
    onSelect?: () => void;
    /** Renders a hairline divider instead of a row. */
    divider?: boolean;
}

export interface ActionMenuProps {
    trigger: React.ReactNode;
    items: ActionMenuItem[];
    style?: StyleProp<ViewStyle>;
}

const MENU_WIDTH = 184;

export function ActionMenu({ trigger, items, style }: ActionMenuProps) {
    const [open, setOpen] = useState(false);
    const [pos, setPos] = useState({ top: 0, left: 0 });
    const anchorRef = useRef<View>(null);

    const openMenu = () => {
        const node = anchorRef.current;
        if (!node) return;
        node.measureInWindow((x, y, _width, height) => {
            const winW = Dimensions.get('window').width;
            setPos({ top: y + height + 6, left: Math.max(12, Math.min(x, winW - MENU_WIDTH - 12)) });
            setOpen(true);
        });
    };

    const select = (item: ActionMenuItem) => {
        setOpen(false);
        item.onSelect?.();
    };

    return (
        <View ref={anchorRef} collapsable={false} style={style}>
            <Pressable accessibilityRole="button" onPress={openMenu}>
                {trigger}
            </Pressable>
            <Modal visible={open} transparent animationType="fade" onRequestClose={() => setOpen(false)}>
                <Pressable style={styles.backdrop} onPress={() => setOpen(false)}>
                    <View accessibilityRole="menu" style={[styles.menu, { top: pos.top, left: pos.left }]}>
                        {items.map((item, i) =>
                            item.divider ? (
                                <View key={item.key ?? `divider-${i}`} style={styles.divider} />
                            ) : (
                                <Pressable
                                    key={item.key ?? `item-${i}`}
                                    accessibilityRole="menuitem"
                                    accessibilityState={{ disabled: !!item.disabled }}
                                    disabled={item.disabled}
                                    onPress={() => select(item)}
                                    style={({ pressed }) => [styles.item, pressed && styles.itemPressed]}
                                >
                                    <Text
                                        style={[
                                            styles.itemLabel,
                                            item.destructive && styles.itemDestructive,
                                            item.disabled && styles.itemDisabled,
                                        ]}
                                    >
                                        {item.label}
                                    </Text>
                                </Pressable>
                            ),
                        )}
                    </View>
                </Pressable>
            </Modal>
        </View>
    );
}

const styles = StyleSheet.create({
    backdrop: {
        flex: 1,
    },
    menu: {
        position: 'absolute',
        width: MENU_WIDTH,
        backgroundColor: colors.card,
        borderWidth: 1,
        borderColor: colors.line,
        borderRadius: radius.md,
        padding: 6,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 18 },
        shadowRadius: 28,
        shadowOpacity: 0.2,
        elevation: 12,
    },
    item: {
        paddingVertical: 9,
        paddingHorizontal: 11,
        borderRadius: radius.sm,
    },
    itemPressed: {
        backgroundColor: colors.chip,
    },
    itemLabel: {
        fontSize: 13,
        fontFamily: fonts.sans.native.regular,
        color: colors.ink,
    },
    itemDestructive: {
        color: colors.blocked,
    },
    itemDisabled: {
        color: colors.faint,
    },
    divider: {
        height: 1,
        backgroundColor: colors.line2,
        marginVertical: 5,
    },
});
