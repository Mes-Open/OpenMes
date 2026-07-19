import { useMemo, useState } from 'react';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { Switch } from '@/components/ui/Switch';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useCostSource, useDeleteCostSource, useUpdateCostSource } from '@/hooks/queries/useOps';

export function EditCostSourceScreen() {
  const { t } = useTranslation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();

  const query = useCostSource(numericId);
  const updateMutation = useUpdateCostSource();
  const deleteMutation = useDeleteCostSource();
  const cs = query.data;

  const [code, setCode] = useState(cs?.code ?? '');
  const [name, setName] = useState(cs?.name ?? '');
  const [description, setDescription] = useState(cs?.description ?? '');
  const [unitCost, setUnitCost] = useState(cs?.unit_cost != null ? String(cs.unit_cost) : '');
  const [unit, setUnit] = useState(cs?.unit ?? '');
  const [currency, setCurrency] = useState(cs?.currency ?? '');
  const [isActive, setIsActive] = useState(cs?.is_active ?? true);
  const [seeded, setSeeded] = useState(false);

  if (!seeded && cs) {
    setCode(cs.code);
    setName(cs.name);
    setDescription(cs.description ?? '');
    setUnitCost(cs.unit_cost != null ? String(cs.unit_cost) : '');
    setUnit(cs.unit ?? '');
    setCurrency(cs.currency ?? '');
    setIsActive(cs.is_active);
    setSeeded(true);
  }

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!code.trim()) e.code = 'Required';
    if (!name.trim()) e.name = 'Required';
    return e;
  }, [code, name]);

  if (query.isLoading && !cs) return <LoadingState />;
  if (query.isError || !cs) return <ErrorState error={query.error} onRetry={query.refetch} />;

  const onSave = () => {
    if (Object.keys(errors).length > 0) return;
    updateMutation.mutate(
      {
        id: cs.id,
        payload: {
          code: code.trim(),
          name: name.trim(),
          description: description.trim() || undefined,
          unit_cost: unitCost.trim() ? Number(unitCost) : undefined,
          unit: unit.trim() || undefined,
          currency: currency.trim() || undefined,
          is_active: isActive,
        },
      },
      { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert(t('Could not update'), e.message) },
    );
  };

  const onDelete = () =>
    Alert.alert(t('Delete cost source'), cs.name, [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () =>
          deleteMutation.mutate(cs.id, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert(t('Could not delete'), e.message),
          }),
      },
    ]);

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{t('Edit Cost Source')}</Text>

      <View style={styles.codeRow}>
        <View style={{ width: 130 }}>
          <Field label="Code" value={code} onChangeText={setCode} error={errors.code} required mono autoCapitalize="characters" autoCorrect={false} />
        </View>
        <View style={{ flex: 1 }}>
          <Field label="Name" value={name} onChangeText={setName} error={errors.name} required />
        </View>
      </View>
      <Field label="Description" value={description} onChangeText={setDescription} multiline numberOfLines={3} style={styles.textarea} />

      <View style={styles.codeRow}>
        <View style={{ flex: 1 }}>
          <Field label="Unit Cost" value={unitCost} onChangeText={setUnitCost} keyboardType="decimal-pad" mono placeholder="e.g. 0.20" />
        </View>
        <View style={{ flex: 1 }}>
          <Field label="Unit" value={unit} onChangeText={setUnit} placeholder="e.g. kWh, hour" />
        </View>
        <View style={{ width: 100 }}>
          <Field label="Currency" value={currency} onChangeText={setCurrency} autoCapitalize="characters" mono placeholder="EUR" />
        </View>
      </View>

      <View style={styles.toggleRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.toggleLabel}>{t('Active')}</Text>
          <Mono size={9} color={colors.faint}>{t('INACTIVE ENTITIES ARE HIDDEN BY DEFAULT')}</Mono>
        </View>
        <Switch value={isActive} onValueChange={setIsActive} />
      </View>

      <View style={styles.actions}>
        <Button title={t('Cancel')} variant="ghost" onPress={() => router.back()} />
        <View style={{ flex: 1 }} />
        <Button title={t('Save changes')} onPress={onSave} loading={updateMutation.isPending} disabled={updateMutation.isPending} />
      </View>

      <View style={styles.danger}>
        <Button title={t('Delete cost source')} variant="danger" onPress={onDelete} loading={deleteMutation.isPending} />
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  codeRow: { flexDirection: 'row', gap: 10 },
  textarea: { minHeight: 80, textAlignVertical: 'top', paddingTop: 12 },
  toggleRow: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  toggleLabel: { fontSize: 14, fontFamily: fonts.sans.native.medium, color: colors.ink },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
  danger: { marginTop: 8, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: colors.line2, paddingTop: 16 },
});
