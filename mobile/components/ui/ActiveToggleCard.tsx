// Light-only v1: Colors[scheme] switching dropped — Geist White tokens; dark shop-floor theming returns via token theming later.
import { Controller, type Control, type FieldPath, type FieldValues } from 'react-hook-form';
import { StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts, monoLabel, radius } from '@openmes/ui';

import { Switch } from '@/components/ui/Switch';

interface Props<T extends FieldValues> {
  control: Control<T>;
  name: FieldPath<T>;
  title?: string;
  description?: string;
}

export function ActiveToggleCard<T extends FieldValues>({
  control,
  name,
  title = 'Active',
  description = 'INACTIVE ENTITIES ARE HIDDEN BY DEFAULT',
}: Props<T>) {
  const { t } = useTranslation();
  return (
    <View style={styles.card}>
      <View style={styles.row}>
        <View style={{ flex: 1 }}>
          <Text style={styles.title}>{t(title)}</Text>
          {description ? (
            <Text style={styles.description}>{t(description).toUpperCase()}</Text>
          ) : null}
        </View>
        <Controller
          control={control}
          name={name}
          render={({ field: { value, onChange } }) => (
            <Switch value={!!value} onValueChange={onChange} />
          )}
        />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.md,
    padding: 14,
  },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  title: {
    fontSize: 14,
    fontFamily: fonts.sans.native.semibold,
    letterSpacing: -0.2,
    color: colors.ink,
  },
  description: {
    ...monoLabel,
    fontFamily: fonts.mono.native.regular,
    color: colors.faint,
    marginTop: 3,
  },
});
