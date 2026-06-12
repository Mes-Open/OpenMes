import { FontAwesome } from '@expo/vector-icons';
import { ActivityIndicator, Pressable, StyleSheet, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Mono } from '@/components/ui/Mono';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';

interface Props {
  primary: string;
  secondary?: string;
  onPrimary: () => void;
  onSecondary?: () => void;
  onDestructive?: () => void;
  destructiveLabel?: string;
  loading?: boolean;
  disabled?: boolean;
}

export function FormSubmitBar({
  primary,
  secondary = 'Cancel',
  onPrimary,
  onSecondary,
  onDestructive,
  destructiveLabel,
  loading,
  disabled,
}: Props) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();

  return (
    <View style={[styles.bar, { backgroundColor: palette.background }]}>
      {onDestructive ? (
        <Pressable
          onPress={onDestructive}
          accessibilityLabel={destructiveLabel ?? 'Delete'}
          style={({ pressed }) => [
            styles.iconBtn,
            {
              borderColor: palette.danger,
              backgroundColor: palette.surface,
              opacity: pressed ? 0.7 : 1,
            },
          ]}>
          <FontAwesome name="trash" size={16} color={palette.danger} />
        </Pressable>
      ) : null}
      {onSecondary ? (
        <Pressable
          onPress={onSecondary}
          style={({ pressed }) => [
            styles.secondary,
            {
              backgroundColor: palette.surface,
              borderColor: palette.border,
              opacity: pressed ? 0.85 : 1,
            },
          ]}>
          <Mono size={12} color={palette.text} weight="700" letterSpacing={0.5}>
            {t(secondary).toUpperCase()}
          </Mono>
        </Pressable>
      ) : null}
      <Pressable
        onPress={onPrimary}
        disabled={!!disabled || !!loading}
        style={({ pressed }) => [
          styles.primary,
          {
            backgroundColor: BRAND.amber,
            opacity: disabled ? 0.5 : pressed ? 0.9 : 1,
          },
        ]}>
        {loading ? (
          <ActivityIndicator color="#1a1208" />
        ) : (
          <Mono size={12} color="#1a1208" weight="700" letterSpacing={0.6}>
            {t(primary).toUpperCase()}
          </Mono>
        )}
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  bar: {
    flexDirection: 'row',
    gap: 8,
    paddingTop: 12,
    paddingBottom: 12,
  },
  iconBtn: {
    width: 48,
    height: 48,
    borderRadius: 12,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  secondary: {
    flex: 1,
    height: 48,
    borderRadius: 12,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  primary: {
    flex: 2,
    height: 48,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
