/**
 * New wage group — inline form mirroring the web wage-group form (Code / Name /
 * Base Hourly Rate / Currency / Description / Active). Writes through
 * useCreateWageGroup.
 */
import { useMemo, useState } from 'react';
import { useRouter } from 'expo-router';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Button, colors, fonts, radius } from '@openmes/ui';

import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { Switch } from '@/components/ui/Switch';
import { useCreateWageGroup } from '@/hooks/mutations/hr';

export function NewWageGroupScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const create = useCreateWageGroup();

  const [code, setCode] = useState('');
  const [name, setName] = useState('');
  const [rate, setRate] = useState('');
  const [currency, setCurrency] = useState('');
  const [description, setDescription] = useState('');
  const [isActive, setIsActive] = useState(true);

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!code.trim()) e.code = 'Required';
    if (!name.trim()) e.name = 'Required';
    return e;
  }, [code, name]);

  const onSave = () => {
    if (Object.keys(errors).length > 0) return;
    create.mutate(
      {
        code: code.trim(),
        name: name.trim(),
        description: description.trim() || undefined,
        base_hourly_rate: rate.trim() ? Number(rate) : undefined,
        currency: currency.trim() || undefined,
        is_active: isActive,
      },
      { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert(t('Could not create'), e.message) },
    );
  };

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
      <Text style={styles.h1}>{t('New wage group')}</Text>

      <Field label="Code" value={code} onChangeText={setCode} error={errors.code} required autoCapitalize="characters" autoCorrect={false} mono />
      <Field label="Name" value={name} onChangeText={setName} error={errors.name} required />
      <Field label="Base Hourly Rate" value={rate} onChangeText={setRate} keyboardType="decimal-pad" placeholder="e.g. 25.50" mono />
      <Field label="Currency" value={currency} onChangeText={setCurrency} autoCapitalize="characters" placeholder="EUR" mono />
      <Field label="Description" value={description} onChangeText={setDescription} multiline numberOfLines={3} style={{ minHeight: 80, textAlignVertical: 'top' }} />

      <View style={styles.toggleRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.toggleLabel}>{t('Active')}</Text>
          <Mono size={9} color={colors.faint}>{t('Show in operator dropdowns and crew rosters')}</Mono>
        </View>
        <Switch value={isActive} onValueChange={setIsActive} />
      </View>

      <View style={styles.actions}>
        <Button variant="ghost" onPress={() => router.back()}>{t('Cancel')}</Button>
        <View style={{ flex: 1 }} />
        <Button onPress={onSave} loading={create.isPending} disabled={create.isPending}>{t('Save')}</Button>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  toggleRow: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  toggleLabel: { fontSize: 14, fontFamily: fonts.sans.native.medium, color: colors.ink },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
});
