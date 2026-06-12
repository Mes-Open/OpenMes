// Light-only v1: Colors[scheme] switching dropped — Geist White tokens; dark shop-floor theming returns via token theming later.
import { useState } from 'react';
import { StyleSheet, Text, TextInput, type TextInputProps, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts, monoLabel, radius } from '@openmes/ui';

interface Props extends TextInputProps {
  label: string;
  error?: string;
  hint?: string;
  /** Render the input value in monospace (codes / IDs / EANs). */
  mono?: boolean;
  /** Trailing affordance shown inside the input (unit, icon, etc.). */
  suffix?: React.ReactNode;
  /** Mono label hint shown on the right of the label row (e.g. "13 OR 14 DIGITS"). */
  labelHint?: string;
  /** Marks the label with a red asterisk. */
  required?: boolean;
}

export function Field({
  label,
  error,
  hint,
  mono,
  suffix,
  labelHint,
  required,
  style,
  placeholder,
  onFocus,
  onBlur,
  ...rest
}: Props) {
  // Auto-translate label, hint, labelHint, placeholder, and error so every
  // form screen picks up i18n without per-screen edits. Strings are i18n
  // keys (English phrase = key, per Laravel __() convention). If a key is
  // missing the value returned by t() is the key itself — same as today.
  const { t } = useTranslation();
  // Focus state drives the accent border + focusRing halo (TextField idiom).
  const [focused, setFocused] = useState(false);

  return (
    <View style={styles.wrap}>
      <View style={styles.labelRow}>
        <Text style={styles.label}>
          {t(label)}
          {required ? <Text style={{ color: colors.blocked }}>{' *'}</Text> : null}
        </Text>
        {labelHint ? <Text style={styles.labelHint}>{t(labelHint)}</Text> : null}
      </View>
      <View style={[styles.ring, focused && styles.ringFocused]}>
        <View
          style={[
            styles.inputWrap,
            focused && styles.inputWrapFocused,
            error ? styles.inputWrapError : null,
          ]}>
          <TextInput
            placeholderTextColor={colors.faint}
            placeholder={placeholder ? t(placeholder) : undefined}
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
      {error ? (
        <Text style={styles.error}>{t(error)}</Text>
      ) : hint ? (
        <Text style={styles.hint}>{t(hint)}</Text>
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
  ringFocused: {
    backgroundColor: colors.focusRing,
  },
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
  inputWrapFocused: {
    borderColor: colors.accent,
    backgroundColor: colors.card,
  },
  inputWrapError: {
    borderColor: colors.blocked,
  },
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
