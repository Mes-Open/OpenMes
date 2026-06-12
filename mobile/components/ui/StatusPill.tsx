import { StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { MONO, statusKindFor, statusPalette, statusPaletteDark } from '@/constants/Colors';
import { statusLabel } from '@/lib/statusLabels';

interface Props {
  status: string | undefined | null;
  label?: string;
  dark?: boolean;
}

export function StatusPill({ status, label, dark }: Props) {
  // Subscribe to language changes — statusLabel() reads from i18n.t() which is
  // otherwise unreactive.
  useTranslation();
  const kind = statusKindFor(status);
  const palette = (dark ? statusPaletteDark : statusPalette)[kind];
  const display = (label ?? statusLabel(status)).toString().toUpperCase();

  return (
    <View style={[styles.pill, { backgroundColor: palette.bg }]}>
      <View style={[styles.dot, { backgroundColor: palette.dot }]} />
      <Text style={[styles.text, { color: palette.fg }]}>{display}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  pill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 3,
    paddingHorizontal: 8,
    borderRadius: 4,
    alignSelf: 'flex-start',
  },
  dot: { width: 6, height: 6, borderRadius: 1 },
  text: { fontSize: 10, fontWeight: '700', letterSpacing: 0.6, fontFamily: MONO },
});
