// Light-only v1: Colors[scheme] switching dropped — Geist White tokens; dark shop-floor theming returns via token theming later.
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { colors, fonts } from '@openmes/ui';

interface Props {
  label: string;
  active: boolean;
  onPress: () => void;
  count?: number | string;
}

export function SelectionChip({ label, active, onPress, count }: Props) {
  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="button"
      accessibilityState={{ selected: active }}
      style={[
        styles.chip,
        {
          backgroundColor: active ? colors.ink : colors.card,
          borderColor: active ? colors.ink : colors.line2,
        },
      ]}>
      <Text style={[styles.label, { color: active ? '#FFFFFF' : colors.muted }]}>{label}</Text>
      {count != null ? (
        <Text style={[styles.count, { color: active ? '#FFFFFF' : colors.faint }]}>{count}</Text>
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
  label: {
    fontSize: 12,
    fontFamily: fonts.sans.native.semibold,
  },
  count: {
    fontSize: 10,
    fontFamily: fonts.mono.native.regular,
    letterSpacing: 0.5,
  },
  row: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
});
