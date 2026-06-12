import { StyleSheet, View } from 'react-native';

import { Mono } from '@/components/ui/Mono';
import { Switch } from '@/components/ui/Switch';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';

interface Props {
  value: boolean;
  onValueChange: (v: boolean) => void;
  label?: string;
}

export function InactiveToggle({ value, onValueChange, label = 'SHOW INACTIVE' }: Props) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  return (
    <View style={[styles.wrap, { borderColor: palette.border }]}>
      <Mono size={11} color={palette.textFaint} letterSpacing={0.6}>
        {label.toUpperCase()}
      </Mono>
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
    borderRadius: 12,
    borderWidth: 1,
  },
});
