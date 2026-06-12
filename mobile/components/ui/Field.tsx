import { StyleSheet, Text, TextInput, type TextInputProps, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import Colors, { MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';

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
  ...rest
}: Props) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  // Auto-translate label, hint, labelHint, placeholder, and error so every
  // form screen picks up i18n without per-screen edits. Strings are i18n
  // keys (English phrase = key, per Laravel __() convention). If a key is
  // missing the value returned by t() is the key itself — same as today.
  const { t } = useTranslation();

  return (
    <View style={styles.wrap}>
      <View style={styles.labelRow}>
        <Text style={[styles.label, { color: palette.textFaint }]}>
          {t(label)}
          {required ? <Text style={{ color: palette.danger }}>{' *'}</Text> : null}
        </Text>
        {labelHint ? (
          <Text style={[styles.labelHint, { color: palette.textFaint }]}>{t(labelHint)}</Text>
        ) : null}
      </View>
      <View
        style={[
          styles.inputWrap,
          {
            backgroundColor: palette.surface,
            borderColor: error ? palette.danger : palette.border,
          },
        ]}>
        <TextInput
          placeholderTextColor={palette.textFaint}
          placeholder={placeholder ? t(placeholder) : undefined}
          {...rest}
          style={[
            styles.input,
            { color: palette.text, fontFamily: mono ? MONO : undefined },
            style,
          ]}
        />
        {suffix ? <View style={styles.suffix}>{suffix}</View> : null}
      </View>
      {error ? (
        <Text style={[styles.error, { color: palette.danger }]}>{t(error)}</Text>
      ) : hint ? (
        <Text style={[styles.hint, { color: palette.textFaint }]}>{t(hint)}</Text>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { gap: 6 },
  labelRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'baseline' },
  label: { fontSize: 10.5, fontWeight: '600', letterSpacing: 0.7, textTransform: 'uppercase', fontFamily: MONO },
  labelHint: { fontSize: 10, fontWeight: '400', fontFamily: MONO, letterSpacing: 0.4 },
  inputWrap: {
    borderWidth: 1,
    borderRadius: 10,
    paddingHorizontal: 14,
    minHeight: 48,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  input: {
    flex: 1,
    fontSize: 14,
    paddingVertical: 12,
  },
  suffix: { alignItems: 'center', justifyContent: 'center' },
  error: { fontSize: 11, fontFamily: MONO, letterSpacing: 0.4, marginTop: 2 },
  hint: { fontSize: 10.5, fontFamily: MONO, letterSpacing: 0.3, marginTop: 2 },
});
