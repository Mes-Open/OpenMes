/**
 * TextField — Geist White system (design ref: OpenMES Components.dc.html §04).
 * Native twin of index.web.jsx. Extends RN TextInputProps, so keyboardType,
 * secureTextEntry, autoCapitalize, multiline, value/onChangeText, etc. all pass
 * through. Adds label (+ optional required asterisk / labelHint), error, hint,
 * mono, and a trailing `suffix`. Focus tints a 3px focusRing halo (web parity).
 */
import React, { useState } from 'react';
import {
    StyleSheet,
    Text,
    TextInput,
    View,
    type StyleProp,
    type TextInputProps,
    type ViewStyle,
    Platform,
} from 'react-native';

import { colors, fonts, monoLabel, radius } from '../tokens';

// react-native-web draws the browser's default focus outline on <input>;
// suppress it — the design's own focus ring (accent border/halo) replaces it.
const WEB_OUTLINE_RESET = Platform.OS === 'web' ? ({ outlineStyle: 'none' } as unknown as object) : null;

export interface TextFieldProps extends TextInputProps {
    label?: string;
    error?: string;
    hint?: string;
    /** Mono hint shown at the right of the label row (e.g. "13 OR 14 DIGITS"). */
    labelHint?: string;
    /** Marks the label with a red asterisk. */
    required?: boolean;
    /** Render the value in monospace (codes / IDs / EANs). */
    mono?: boolean;
    /** Trailing affordance shown inside the field (unit, icon, etc.). */
    suffix?: React.ReactNode;
    /** Style for the outer wrapper (the `style` prop lands on the input itself). */
    containerStyle?: StyleProp<ViewStyle>;
}

export function TextField({
    label,
    error,
    hint,
    labelHint,
    required,
    mono = false,
    suffix,
    placeholder,
    style,
    onFocus,
    onBlur,
    containerStyle,
    ...rest
}: TextFieldProps) {
    const [focused, setFocused] = useState(false);

    return (
        <View style={[styles.wrap, containerStyle]}>
            {label != null && (
                <View style={styles.labelRow}>
                    <Text style={styles.label}>
                        {label}
                        {required ? <Text style={{ color: colors.blocked }}>{' *'}</Text> : null}
                    </Text>
                    {labelHint ? <Text style={styles.labelHint}>{labelHint}</Text> : null}
                </View>
            )}
            <View style={[styles.ring, focused && styles.ringFocused]}>
                <View style={[styles.inputWrap, focused && styles.inputWrapFocused, error != null && styles.inputWrapError]}>
                    <TextInput
                        placeholder={placeholder}
                        placeholderTextColor={colors.faint}
                        accessibilityLabel={label}
                        {...rest}
                        onFocus={(e) => {
                            setFocused(true);
                            onFocus?.(e);
                        }}
                        onBlur={(e) => {
                            setFocused(false);
                            onBlur?.(e);
                        }}
                        style={[
                            styles.input,
                            { fontFamily: mono ? fonts.mono.native.regular : fonts.sans.native.regular },
                            style,
                        ]}
                    />
                    {suffix ? <View style={styles.suffix}>{suffix}</View> : null}
                </View>
            </View>
            {error != null ? (
                <Text style={styles.error}>{error}</Text>
            ) : hint != null ? (
                <Text style={styles.hint}>{hint}</Text>
            ) : null}
        </View>
    );
}

const styles = StyleSheet.create({
    wrap: { gap: 6 },
    labelRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'baseline' },
    label: {
        ...monoLabel,
        fontFamily: fonts.mono.native.medium,
        color: colors.faint,
    },
    labelHint: {
        fontSize: 10,
        fontFamily: fonts.mono.native.regular,
        letterSpacing: 0.4,
        color: colors.faint,
    },
    /** Always-mounted focus halo — 3px pad pulled back by margin so layout is stable. */
    ring: {
        padding: 3,
        margin: -3,
        borderRadius: radius.sm + 3,
        backgroundColor: 'transparent',
    },
    ringFocused: { backgroundColor: colors.focusRing },
    inputWrap: {
        backgroundColor: colors.bg,
        borderWidth: 1,
        borderColor: colors.line,
        borderRadius: radius.sm,
        paddingHorizontal: 14,
        minHeight: 48,
        flexDirection: 'row',
        alignItems: 'center',
        gap: 8,
    },
    inputWrapFocused: { borderColor: colors.accent, backgroundColor: colors.card },
    inputWrapError: { borderColor: colors.blocked },
    input: {
        flex: 1,
        fontSize: 14,
        color: colors.ink,
        paddingVertical: 12,
    },
    suffix: { alignItems: 'center', justifyContent: 'center' },
    error: {
        fontSize: 11,
        fontFamily: fonts.mono.native.regular,
        letterSpacing: 0.4,
        marginTop: 2,
        color: colors.blocked,
    },
    hint: {
        fontSize: 10.5,
        fontFamily: fonts.mono.native.regular,
        letterSpacing: 0.3,
        marginTop: 2,
        color: colors.faint,
    },
});
