import { Pressable, StyleSheet, View, type ViewProps } from 'react-native';

import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';

interface Props extends ViewProps {
  onPress?: () => void;
  variant?: 'default' | 'flat' | 'inverse';
  accent?: string;
  leftAccent?: string;
}

export function Card({ onPress, style, children, variant = 'default', accent, leftAccent, ...rest }: Props) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  const bg =
    variant === 'inverse'
      ? palette.surfaceInverse
      : variant === 'flat'
      ? palette.surfaceAlt
      : palette.surface;

  const content = (
    <View
      {...rest}
      style={[
        styles.card,
        {
          backgroundColor: bg,
          borderColor: palette.border,
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
    borderRadius: 14,
    padding: 14,
    borderWidth: 1,
  },
});
