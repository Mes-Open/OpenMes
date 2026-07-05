/**
 * SearchField — Geist White system (design ref: OpenMES Components.dc.html §10⑥).
 * Native-only (no web twin): card row with circle search glyph and 13.5px input.
 */
import React from 'react';
import {
    StyleSheet,
    TextInput,
    View,
    type StyleProp,
    type ViewStyle,
    Platform,
} from 'react-native';

import { colors, fonts } from '../tokens';

// react-native-web draws the browser's default focus outline on <input>;
// suppress it — the design's own focus ring (accent border/halo) replaces it.
const WEB_OUTLINE_RESET = Platform.OS === 'web' ? ({ outlineStyle: 'none' } as unknown as object) : null;

export interface SearchFieldProps {
    value: string;
    onChange: (text: string) => void;
    placeholder?: string;
    autoFocus?: boolean;
    style?: StyleProp<ViewStyle>;
}

export function SearchField({ value, onChange, placeholder, autoFocus = false, style }: SearchFieldProps) {
    return (
        <View style={[styles.field, style]}>
            <View style={styles.icon} />
            <TextInput
                accessibilityRole="search"
                value={value}
                onChangeText={onChange}
                placeholder={placeholder}
                placeholderTextColor={colors.faint}
                autoFocus={autoFocus}
                returnKeyType="search"
                autoCapitalize="none"
                autoCorrect={false}
                style={[styles.input, WEB_OUTLINE_RESET]}
            />
        </View>
    );
}

const styles = StyleSheet.create({
    field: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 9,
        backgroundColor: colors.card,
        borderWidth: 1,
        borderColor: colors.line2,
        borderRadius: 11,
        paddingVertical: 11,
        paddingHorizontal: 13,
    },
    icon: {
        width: 13,
        height: 13,
        borderRadius: 6.5,
        borderWidth: 2,
        borderColor: colors.faint,
    },
    input: {
        flex: 1,
        fontSize: 13.5,
        fontFamily: fonts.sans.native.regular,
        color: colors.ink,
        padding: 0,
        paddingVertical: 0,
    },
});
