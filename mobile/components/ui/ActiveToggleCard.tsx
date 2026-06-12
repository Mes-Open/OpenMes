import { Controller, type Control, type FieldPath, type FieldValues } from 'react-hook-form';
import { StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Card } from '@/components/ui/Card';
import { Mono } from '@/components/ui/Mono';
import { Switch } from '@/components/ui/Switch';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';

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
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  return (
    <Card>
      <View style={styles.row}>
        <View style={{ flex: 1 }}>
          <Text style={[styles.title, { color: palette.text }]}>{t(title)}</Text>
          {description ? (
            <Mono size={11} color={palette.textFaint} style={{ marginTop: 3 }}>
              {t(description).toUpperCase()}
            </Mono>
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
    </Card>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  title: { fontSize: 14, fontWeight: '600', letterSpacing: -0.2 },
});
