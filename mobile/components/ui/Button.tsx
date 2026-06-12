import { ActivityIndicator, Pressable, StyleSheet, Text, View, type ViewStyle } from 'react-native';
import { useTranslation } from 'react-i18next';

import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';

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
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  // Auto-translate the title — call sites pass English-as-key, same trick
  // as Field + HubScreen + StatusPill.
  const { t } = useTranslation();

  const bg = (() => {
    switch (variant) {
      case 'primary':
        return BRAND.amber;
      case 'secondary':
        return scheme === 'dark' ? palette.surfaceAlt : palette.surface;
      case 'danger':
        return palette.danger;
      case 'success':
        return palette.success;
      case 'ghost':
      case 'outline':
        return 'transparent';
    }
  })();

  const fg =
    variant === 'primary'
      ? '#1a1208'
      : variant === 'secondary'
      ? palette.text
      : variant === 'ghost'
      ? palette.tint
      : variant === 'outline'
      ? palette.text
      : '#ffffff';

  const border =
    variant === 'secondary'
      ? palette.border
      : variant === 'outline' || variant === 'ghost'
      ? variant === 'ghost'
        ? palette.tint
        : palette.border
      : undefined;

  const isDisabled = disabled || loading;

  return (
    <Pressable
      onPress={onPress}
      disabled={isDisabled}
      style={({ pressed }) => [
        styles.base,
        size === 'lg' && styles.lg,
        size === 'sm' && styles.sm,
        { backgroundColor: bg, opacity: isDisabled ? 0.5 : pressed ? 0.85 : 1 },
        border ? { borderWidth: 1, borderColor: border } : null,
        style,
      ]}>
      {loading ? (
        <ActivityIndicator color={fg} />
      ) : (
        <View style={styles.row}>
          {leftIcon ? <View style={styles.icon}>{leftIcon}</View> : null}
          <Text
            style={[
              styles.text,
              size === 'lg' && styles.textLg,
              size === 'sm' && styles.textSm,
              { color: fg },
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
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: 48,
  },
  sm: { minHeight: 36, paddingVertical: 8, paddingHorizontal: 12, borderRadius: 10 },
  lg: { minHeight: 56, paddingVertical: 16, paddingHorizontal: 20, borderRadius: 14 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  icon: { alignItems: 'center', justifyContent: 'center' },
  text: { fontSize: 15, fontWeight: '600', letterSpacing: 0.1 },
  textSm: { fontSize: 13, fontWeight: '600' },
  textLg: { fontSize: 16, fontWeight: '700' },
});
