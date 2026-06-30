/**
 * Dropdown — Geist White system (design ref: OpenMES Components.dc.html §13).
 * Native twin of index.web.jsx — identical props API. Options open in an RN
 * Modal over the scrim token as a centered menu card.
 */
import React, { useState } from 'react';
import {
    Modal,
    Pressable,
    ScrollView,
    StyleSheet,
    Text,
    View,
    type StyleProp,
    type ViewStyle,
} from 'react-native';

import { colors, fonts, radius } from '../tokens';

export interface DropdownOption {
    value: string;
    label: string;
}

export interface DropdownProps {
    options: DropdownOption[];
    /** Selected value (single mode). */
    value?: string;
    /** Selected values (multi mode). */
    values?: string[];
    multiple?: boolean;
    onChange?: (next: string | string[]) => void;
    /** Computed trigger-label override (e.g. "3 selected" in multi mode). */
    label?: string;
    placeholder?: string;
    disabled?: boolean;
    style?: StyleProp<ViewStyle>;
}

export function Dropdown({
    options,
    value,
    values,
    multiple = false,
    onChange,
    label,
    placeholder,
    disabled = false,
    style,
}: DropdownProps) {
    const [open, setOpen] = useState(false);

    const selectedValues = multiple ? (values ?? []) : [];
    const single = !multiple ? options.find((o) => o.value === value) : undefined;
    const isPlaceholder = label == null && (multiple ? selectedValues.length === 0 : !single);
    const triggerLabel = label ?? (multiple ? placeholder : (single?.label ?? placeholder));

    const pick = (option: DropdownOption) => {
        if (multiple) {
            const next = selectedValues.includes(option.value)
                ? selectedValues.filter((v) => v !== option.value)
                : [...selectedValues, option.value];
            onChange?.(next);
        } else {
            onChange?.(option.value);
            setOpen(false);
        }
    };

    return (
        <View style={style}>
            <Pressable
                accessibilityRole="button"
                accessibilityState={{ disabled, expanded: open }}
                disabled={disabled}
                onPress={() => setOpen(true)}
                style={[styles.trigger, disabled && styles.triggerDisabled]}
            >
                <Text style={[styles.triggerLabel, isPlaceholder && styles.triggerPlaceholder]}>{triggerLabel}</Text>
                <Text style={styles.caret}>▾</Text>
            </Pressable>
            <Modal visible={open} transparent animationType="fade" onRequestClose={() => setOpen(false)}>
                <Pressable style={styles.scrim} onPress={() => setOpen(false)}>
                    <Pressable style={styles.menu}>
                        <ScrollView bounces={false} style={styles.menuScroll}>
                            {options.map((o) => {
                                if (multiple) {
                                    const on = selectedValues.includes(o.value);
                                    return (
                                        <Pressable
                                            key={o.value}
                                            accessibilityRole="checkbox"
                                            accessibilityState={{ checked: on }}
                                            onPress={() => pick(o)}
                                            style={({ pressed }) => [styles.row, pressed && styles.rowPressed]}
                                        >
                                            <View style={[styles.checkbox, on ? styles.checkboxOn : styles.checkboxOff]}>
                                                {on && <Text style={styles.checkboxMark}>✓</Text>}
                                            </View>
                                            <Text style={styles.multiLabel}>{o.label}</Text>
                                        </Pressable>
                                    );
                                }
                                const selected = o.value === value;
                                return (
                                    <Pressable
                                        key={o.value}
                                        accessibilityRole="button"
                                        accessibilityState={{ selected }}
                                        onPress={() => pick(o)}
                                        style={({ pressed }) => [
                                            styles.row,
                                            styles.singleRow,
                                            (selected || pressed) && styles.rowPressed,
                                        ]}
                                    >
                                        <Text style={[styles.singleLabel, selected && styles.singleLabelSelected]}>
                                            {o.label}
                                        </Text>
                                        <Text style={styles.checkMark}>{selected ? '✓' : ''}</Text>
                                    </Pressable>
                                );
                            })}
                        </ScrollView>
                    </Pressable>
                </Pressable>
            </Modal>
        </View>
    );
}

const styles = StyleSheet.create({
    trigger: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        gap: 10,
        backgroundColor: colors.bg,
        borderWidth: 1,
        borderColor: colors.line,
        borderRadius: radius.sm,
        paddingVertical: 10,
        paddingHorizontal: 13,
    },
    triggerDisabled: {
        opacity: 0.6,
    },
    triggerLabel: {
        fontSize: 13.5,
        fontFamily: fonts.sans.native.regular,
        color: colors.ink,
        flexShrink: 1,
    },
    triggerPlaceholder: {
        color: colors.faint,
    },
    caret: {
        fontSize: 11,
        color: colors.faint,
    },
    scrim: {
        flex: 1,
        backgroundColor: colors.scrim,
        alignItems: 'center',
        justifyContent: 'center',
        padding: 24,
    },
    menu: {
        width: '100%',
        maxWidth: 320,
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
    menuScroll: {
        maxHeight: 380,
    },
    row: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 11,
        paddingVertical: 9,
        paddingHorizontal: 11,
        borderRadius: 6,
    },
    singleRow: {
        justifyContent: 'space-between',
        gap: 10,
    },
    rowPressed: {
        backgroundColor: colors.chip,
    },
    singleLabel: {
        fontSize: 13,
        fontFamily: fonts.sans.native.regular,
        color: colors.muted,
    },
    singleLabelSelected: {
        fontFamily: fonts.sans.native.semibold,
        color: colors.ink,
    },
    checkMark: {
        width: 14,
        textAlign: 'right',
        fontSize: 13,
        fontFamily: fonts.sans.native.bold,
        color: colors.accent,
    },
    multiLabel: {
        fontSize: 13,
        fontFamily: fonts.sans.native.regular,
        color: colors.ink,
    },
    checkbox: {
        width: 17,
        height: 17,
        borderRadius: 5,
        alignItems: 'center',
        justifyContent: 'center',
        flexShrink: 0,
    },
    checkboxOn: {
        backgroundColor: colors.accent,
    },
    checkboxOff: {
        borderWidth: 2,
        borderColor: colors.faintest,
    },
    checkboxMark: {
        fontSize: 10,
        lineHeight: 11,
        fontFamily: fonts.sans.native.bold,
        color: '#FFFFFF',
    },
});
