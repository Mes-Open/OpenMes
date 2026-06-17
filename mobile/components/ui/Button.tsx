// Light-only v1: Colors[scheme] switching dropped — Geist White tokens; dark shop-floor theming returns via token theming later.
import { ActivityIndicator, Pressable, StyleSheet, Text, View, type ViewStyle } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts, radius } from '@openmes/ui';

type Variant = 'primary' | 'secondary' | 'danger' | 'success' | 'ghost' | 'outline';

interface Props {
  title: string;
  onPress?: () => void;
  variant?: Variant;
  loading?: boolean;
  disabled?: boolean;
  size?: 'sm' | 'md' | 'lg';
  style?: ViewStyle;
  leftIcon?: React.ReactNode;
  rightIcon?: React.ReactNode;
}

/** bg / fg / optional border per variant — mapped onto the Geist White button recipes. */
const VARIANTS: Record<Variant, { bg: string; fg: string; border?: string }> = {
  primary: { bg: colors.ink, fg: '#FFFFFF' },
  secondary: { bg: colors.chip, fg: colors.ink },
  danger: { bg: colors.blockedBg, fg: colors.blocked },
  success: { bg: colors.runningBg, fg: colors.running },
  ghost: { bg: 'transparent', fg: colors.accent, border: colors.line },
  outline: { bg: 'transparent', fg: colors.ink, border: colors.line },
};

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
  // Auto-translate the title — call sites pass English-as-key, same trick
  // as Field + HubScreen + StatusPill.
  const { t } = useTranslation();

  const v = VARIANTS[variant];
  const isDisabled = disabled || loading;

  return (
    <Pressable
      onPress={onPress}
      disabled={isDisabled}
      accessibilityRole="button"
      accessibilityState={{ disabled: !!isDisabled, busy: !!loading }}
      style={({ pressed }) => [
        styles.base,
        size === 'lg' && styles.lg,
        size === 'sm' && styles.sm,
        { backgroundColor: v.bg, opacity: isDisabled ? 0.5 : pressed ? 0.85 : 1 },
        v.border ? { borderWidth: 1, borderColor: v.border } : null,
        style,
      ]}>
      {loading ? (
        <ActivityIndicator color={v.fg} />
      ) : (
        <View style={styles.row}>
          {leftIcon ? <View style={styles.icon}>{leftIcon}</View> : null}
          <Text
            style={[
              styles.text,
              size === 'lg' && styles.textLg,
              size === 'sm' && styles.textSm,
              { color: v.fg },
            ]}>
            {t(title)}
          </Text>
          {rightIcon ? <View style={styles.icon}>{rightIcon}</View> : null}
        </View>
      )}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  base: {
    paddingVertical: 12,
    paddingHorizontal: 16,
    borderRadius: radius.sm,
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: 48,
  },
  sm: { minHeight: 36, paddingVertical: 8, paddingHorizontal: 12 },
  lg: { minHeight: 56, paddingVertical: 16, paddingHorizontal: 20 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  icon: { alignItems: 'center', justifyContent: 'center' },
  text: { fontSize: 14, fontFamily: fonts.sans.native.semibold, letterSpacing: 0.1 },
  textSm: { fontSize: 13 },
  textLg: { fontSize: 15, fontFamily: fonts.sans.native.bold },
});
