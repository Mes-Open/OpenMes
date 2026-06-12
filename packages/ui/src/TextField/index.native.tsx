/**
 * TextField — Geist White system (design ref: OpenMES Components.dc.html §04).
 * Native twin of index.web.jsx — identical props API. Focus state is tracked
 * via onFocus/onBlur; the 3px web focus ring is emulated by a focusRing-tinted
 * wrapper pad so the field itself keeps its layout.
 */
import React from 'react';
import { StyleSheet, Text, TextInput, View, type StyleProp, type ViewStyle } from 'react-native';

import { colors, fonts, monoLabel, radius } from '../tokens';

export interface TextFieldProps {
    label?: string;
    value: string;
    onChange?: (text: string) => void;
    placeholder?: string;
    mono?: boolean;
    multiline?: boolean;
    error?: string;
    style?: StyleProp<ViewStyle>;
}

export function TextField({
    label,
    value,
    onChange,
    placeholder,
    mono = false,
    multiline = false,
    error,
    style,
}: TextFieldProps) {
    const [focused, setFocused] = React.useState(false);

    return (
        <View style={style}>
            {label != null && <Text style={styles.label}>{label}</Text>}
            <View style={[styles.ring, focused && styles.ringFocused]}>
                <TextInput
                    accessibilityLabel={label}
                    value={value}
                    placeholder={placeholder}
                    placeholderTextColor={colors.faint}
                    multiline={multiline}
                    onChangeText={onChange}
                    onFocus={() => setFocused(true)}
                    onBlur={() => setFocused(false)}
                    style={[
                        styles.input,
                        mono && styles.inputMono,
                        multiline && styles.inputMultiline,
                        error != null && styles.inputError,
                        focused && styles.inputFocused,
                    ]}
                />
            </View>
            {error != null && <Text style={styles.error}>{error}</Text>}
        </View>
    );
}

const styles = StyleSheet.create({
    label: {
        ...monoLabel,
        fontFamily: fonts.mono.native.regular,
        color: colors.faint,
        marginBottom: 7,
    },
    /** Always-mounted ring wrapper — 3px pad pulled back by margin so layout is stable. */
    ring: {
        padding: 3,
        margin: -3,
        borderRadius: radius.sm + 3,
        backgroundColor: 'transparent',
    },
    ringFocused: {
        backgroundColor: colors.focusRing,
    },
    input: {
        fontSize: 13,
        color: colors.ink,
        fontFamily: fonts.sans.native.regular,
        backgroundColor: colors.bg,
        borderWidth: 1,
        borderColor: colors.line,
        borderRadius: radius.sm,
        paddingVertical: 10,
        paddingHorizontal: 12,
    },
    inputMono: {
        fontFamily: fonts.mono.native.regular,
    },
    inputMultiline: {
        minHeight: 74,
        textAlignVertical: 'top',
    },
    inputError: {
        borderColor: colors.blocked,
    },
    // Border thickens 1 → 1.5; padding gives the 0.5px back so text doesn't shift.
    inputFocused: {
        borderWidth: 1.5,
        borderColor: colors.accent,
        backgroundColor: colors.card,
        paddingVertical: 9.5,
        paddingHorizontal: 11.5,
    },
    error: {
        marginTop: 5,
        fontSize: 11.5,
        color: colors.blocked,
        fontFamily: fonts.sans.native.regular,
    },
});
