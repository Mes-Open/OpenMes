/**
 * Mobile Field — thin i18n adapter over the shared `@openmes/ui` TextField.
 *
 * The shared TextField (native twin, rendered via react-native-web on Expo web —
 * see mobile/metro.config.js) owns the input chrome (label, focus ring, error/
 * hint, mono, suffix, required asterisk) and passes through all RN
 * TextInputProps (keyboardType, secureTextEntry, value/onChangeText, …). This
 * wrapper only auto-translates the human-readable strings (label/error/hint/
 * labelHint/placeholder are i18n keys, English phrase = key per Laravel __()).
 */
import { type TextInputProps } from 'react-native';
import { useTranslation } from 'react-i18next';

import { TextField } from '@openmes/ui';

interface Props extends TextInputProps {
  label: string;
  error?: string;
  hint?: string;
  /** Render the input value in monospace (codes / IDs / EANs). */
  mono?: boolean;
  /** Trailing affordance shown inside the input (unit, icon, etc.). */
  suffix?: React.ReactNode;
  /** Mono hint shown on the right of the label row (e.g. "13 OR 14 DIGITS"). */
  labelHint?: string;
  /** Marks the label with a red asterisk. */
  required?: boolean;
}

export function Field({ label, error, hint, labelHint, placeholder, ...rest }: Props) {
  const { t } = useTranslation();

  return (
    <TextField
      label={t(label)}
      error={error ? t(error) : undefined}
      hint={hint ? t(hint) : undefined}
      labelHint={labelHint ? t(labelHint) : undefined}
      placeholder={placeholder ? t(placeholder) : undefined}
      {...rest}
    />
  );
}
