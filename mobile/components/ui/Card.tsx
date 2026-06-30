// Light-only v1: Colors[scheme] switching dropped — Geist White tokens.
import { Pressable, StyleSheet, View, type ViewProps } from 'react-native';

import { colors, radius } from '@openmes/ui';

interface Props extends ViewProps {
  onPress?: () => void;
  variant?: 'default' | 'flat' | 'inverse';
  accent?: string;
  leftAccent?: string;
}

/** Surface per variant — white card, flat panel, or ink inverse. */
const VARIANT_BG: Record<NonNullable<Props['variant']>, string> = {
  default: colors.card,
  flat: colors.panel,
  inverse: colors.ink,
};

export function Card({ onPress, style, children, variant = 'default', accent, leftAccent, ...rest }: Props) {
  const content = (
    <View
      {...rest}
      style={[
        styles.card,
        {
          backgroundColor: VARIANT_BG[variant],
          borderColor: variant === 'inverse' ? colors.ink : colors.line2,
          overflow: accent || leftAccent ? 'hidden' : undefined,
        },
        leftAccent ? { borderLeftWidth: 4, borderLeftColor: leftAccent } : null,
        style,
      ]}>
      {accent ? (
        <View
          style={{
            position: 'absolute',
            top: 0,
            left: 0,
            right: 0,
            height: 2,
            backgroundColor: accent,
          }}
        />
      ) : null}
      {children}
    </View>
  );

  if (onPress) {
    return (
      <Pressable
        onPress={onPress}
        style={({ pressed }) => ({ opacity: pressed ? 0.85 : 1, transform: [{ scale: pressed ? 0.997 : 1 }] })}>
        {content}
      </Pressable>
    );
  }
  return content;
}

const styles = StyleSheet.create({
  card: {
    borderRadius: radius.md,
    padding: 14,
    borderWidth: 1,
  },
});
