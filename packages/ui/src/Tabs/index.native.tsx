/**
 * Tabs — Geist White system (design ref: OpenMES Components.dc.html §05).
 * Native twin of index.web.jsx — identical props API.
 */
import React from 'react';
import { Pressable, StyleSheet, Text, View, type StyleProp, type ViewStyle } from 'react-native';

import { colors, fonts } from '../tokens';

export interface Tab {
    value: string;
    label: string;
}

export interface TabsProps {
    tabs: Tab[];
    value: string;
    onChange?: (value: string) => void;
    style?: StyleProp<ViewStyle>;
}

export function Tabs({ tabs, value, onChange, style }: TabsProps) {
    return (
        <View accessibilityRole="tablist" style={[styles.row, style]}>
            {tabs.map((tab) => {
                const active = tab.value === value;
                return (
                    <Pressable
                        key={tab.value}
                        accessibilityRole="tab"
                        accessibilityState={{ selected: active }}
                        onPress={() => onChange?.(tab.value)}
                        style={[styles.tab, active && styles.tabActive]}
                    >
                        <Text style={[styles.label, active && styles.labelActive]}>{tab.label}</Text>
                    </Pressable>
                );
            })}
        </View>
    );
}

const styles = StyleSheet.create({
    row: {
        flexDirection: 'row',
        gap: 22,
        borderBottomWidth: 1,
        borderBottomColor: colors.line2,
    },
    tab: {
        paddingVertical: 9,
        paddingHorizontal: 2,
        // Underline overlaps the row hairline (design: margin-bottom -1px).
        marginBottom: -1,
        borderBottomWidth: 2,
        borderBottomColor: 'transparent',
    },
    tabActive: {
        borderBottomColor: colors.accent,
    },
    label: {
        fontSize: 13.5,
        color: colors.muted,
        fontFamily: fonts.sans.native.medium,
    },
    labelActive: {
        color: colors.ink,
    },
});
