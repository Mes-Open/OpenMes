import { Pressable, StyleSheet, Text, View } from 'react-native';

import { Mono } from '@/components/ui/Mono';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';

interface Props {
  label: string;
  active: boolean;
  onPress: () => void;
  count?: number | string;
}

export function SelectionChip({ label, active, onPress, count }: Props) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  return (
    <Pressable
      onPress={onPress}
      style={[
        styles.chip,
        {
          backgroundColor: active ? '#fbe9c8' : palette.surface,
          borderColor: active ? BRAND.amber : palette.border,
        },
      ]}>
      <Text
        style={{
          fontSize: 12,
          fontWeight: '600',
          color: active ? '#8a5a0e' : palette.text,
        }}>
        {label}
      </Text>
      {count != null ? (
        <Mono size={10} color={active ? '#8a5a0e' : palette.textFaint}>{count}</Mono>
      ) : null}
    </Pressable>
  );
}

interface RowProps {
  children: React.ReactNode;
}

export function ChipRow({ children }: RowProps) {
  return <View style={styles.row}>{children}</View>;
}

const styles = StyleSheet.create({
  chip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 8,
    paddingHorizontal: 12,
    borderRadius: 999,
    borderWidth: 1,
  },
  row: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
});
