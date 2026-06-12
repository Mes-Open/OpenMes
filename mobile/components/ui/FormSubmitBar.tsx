// Light-only v1: Colors[scheme] switching dropped — Geist White tokens; dark shop-floor theming returns via token theming later.
import { FontAwesome } from '@expo/vector-icons';
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts, radius } from '@openmes/ui';

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
  const { t } = useTranslation();

  return (
    <View style={styles.bar}>
      {onDestructive ? (
        <Pressable
          onPress={onDestructive}
          accessibilityRole="button"
          accessibilityLabel={destructiveLabel ?? 'Delete'}
          style={({ pressed }) => [styles.iconBtn, { opacity: pressed ? 0.7 : 1 }]}>
          <FontAwesome name="trash" size={16} color={colors.blocked} />
        </Pressable>
      ) : null}
      {onSecondary ? (
        <Pressable
          onPress={onSecondary}
          accessibilityRole="button"
          style={({ pressed }) => [styles.secondary, { opacity: pressed ? 0.85 : 1 }]}>
          <Text style={[styles.btnLabel, { color: colors.ink }]}>
            {t(secondary).toUpperCase()}
          </Text>
        </Pressable>
      ) : null}
      <Pressable
        onPress={onPrimary}
        disabled={!!disabled || !!loading}
        accessibilityRole="button"
        accessibilityState={{ disabled: !!disabled || !!loading, busy: !!loading }}
        style={({ pressed }) => [
          styles.primary,
          { opacity: disabled ? 0.5 : pressed ? 0.9 : 1 },
        ]}>
        {loading ? (
          <ActivityIndicator color="#FFFFFF" />
        ) : (
          <Text style={[styles.btnLabel, { color: '#FFFFFF' }]}>{t(primary).toUpperCase()}</Text>
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
    backgroundColor: colors.bg,
  },
  iconBtn: {
    width: 48,
    height: 48,
    borderRadius: radius.sm,
    borderWidth: 1,
    borderColor: colors.blocked,
    backgroundColor: colors.blockedBg,
    alignItems: 'center',
    justifyContent: 'center',
  },
  secondary: {
    flex: 1,
    height: 48,
    borderRadius: radius.sm,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.card,
    alignItems: 'center',
    justifyContent: 'center',
  },
  primary: {
    flex: 2,
    height: 48,
    borderRadius: radius.sm,
    backgroundColor: colors.ink,
    alignItems: 'center',
    justifyContent: 'center',
  },
  btnLabel: {
    fontSize: 12,
    fontFamily: fonts.mono.native.semibold,
    letterSpacing: 0.6,
  },
});
