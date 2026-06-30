// Light-only v1: Colors[scheme] switching dropped — Geist White tokens; dark shop-floor theming returns via token theming later.
import { StyleSheet, Text, View } from 'react-native';

import { colors, fonts, monoLabel, radius } from '@openmes/ui';

import { Switch } from '@/components/ui/Switch';

interface Props {
  value: boolean;
  onValueChange: (v: boolean) => void;
  label?: string;
}

export function InactiveToggle({ value, onValueChange, label = 'SHOW INACTIVE' }: Props) {
  return (
    <View style={styles.wrap}>
      <Text style={styles.label}>{label.toUpperCase()}</Text>
      <Switch value={value} onValueChange={onValueChange} />
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 6,
    paddingHorizontal: 14,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.line2,
    backgroundColor: colors.card,
  },
  label: {
    ...monoLabel,
    fontFamily: fonts.mono.native.regular,
    color: colors.faint,
  },
});
