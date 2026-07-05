/**
 * Mobile Button — thin i18n adapter over the shared `@openmes/ui` Button.
 *
 * The shared Button (native twin, rendered via react-native-web on Expo web —
 * see mobile/metro.config.js) owns the variants (primary/accent/secondary/
 * ghost/outline/danger/success), sizes (sm/md/lg) and icon slots. This wrapper
 * only adds the app conventions: an auto-translated `title` (call sites pass an
 * English i18n key) and full-width stretch (mobile buttons fill their form
 * column, vs. the shared Button's content-hugging default).
 */
import { type StyleProp, type ViewStyle } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Button as UIButton, type ButtonSize, type ButtonVariant } from '@openmes/ui';

interface Props {
  title: string;
  onPress?: () => void;
  variant?: ButtonVariant;
  loading?: boolean;
  disabled?: boolean;
  size?: ButtonSize;
  style?: StyleProp<ViewStyle>;
  leftIcon?: React.ReactNode;
  rightIcon?: React.ReactNode;
}

export function Button({
  title,
  onPress,
  variant = 'primary',
  loading,
  disabled,
  size = 'md',
  style,
  leftIcon,
  rightIcon,
}: Props) {
  // Call sites pass English-as-key; translate here (same trick as Field/HubScreen).
  const { t } = useTranslation();

  return (
    <UIButton
      variant={variant}
      size={size}
      loading={loading}
      disabled={disabled}
      onPress={onPress}
      leftIcon={leftIcon}
      rightIcon={rightIcon}
      style={[{ alignSelf: 'stretch' }, style]}>
      {t(title)}
    </UIButton>
  );
}
