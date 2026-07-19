import { useMemo, useState } from 'react';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { FontAwesome } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';

import { colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { ChipRow, SelectionChip } from '@/components/ui/SelectionChip';
import { useCreateQcTemplate } from '@/hooks/queries/useProductionControls';

type ParamType = 'measurement' | 'pass_fail';
interface ParamRow { name: string; type: ParamType; unit: string; min: string; max: string }
const emptyParam = (): ParamRow => ({ name: '', type: 'measurement', unit: '', min: '', max: '' });

export function NewQcTemplateScreen() {
  const { t } = useTranslation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const processTemplateId = Number(id);
  const router = useRouter();
  const m = useCreateQcTemplate(processTemplateId);

  const [name, setName] = useState('');
  const [samplesPerCheck, setSamplesPerCheck] = useState('');
  const [minChecksPerBatch, setMinChecksPerBatch] = useState('');
  const [minChecksPerDay, setMinChecksPerDay] = useState('');
  const [params, setParams] = useState<ParamRow[]>([emptyParam()]);

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!name.trim()) e.name = 'Required';
    if (params.filter((p) => p.name.trim()).length === 0) e.params = 'Add at least one parameter';
    return e;
  }, [name, params]);

  const setParam = (i: number, patch: Partial<ParamRow>) =>
    setParams((cur) => cur.map((p, j) => (j === i ? { ...p, ...patch } : p)));

  const onSave = () => {
    if (Object.keys(errors).length > 0) return;
    m.mutate(
      {
        name: name.trim(),
        parameters: params
          .filter((p) => p.name.trim())
          .map((p) => ({
            name: p.name.trim(),
            type: p.type,
            unit: p.unit || null,
            min: p.min ? Number(p.min) : null,
            max: p.max ? Number(p.max) : null,
          })),
        min_checks_per_batch: minChecksPerBatch ? Number(minChecksPerBatch) : null,
        min_checks_per_day: minChecksPerDay ? Number(minChecksPerDay) : null,
        samples_per_check: samplesPerCheck ? Number(samplesPerCheck) : null,
      },
      { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert(t('Could not create'), e.message) },
    );
  };

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{t('New QC template')}</Text>

      <Field label="Name" value={name} onChangeText={setName} error={errors.name} required placeholder="e.g. Final inspection" />
      <Field label="Samples per check (optional)" value={samplesPerCheck} onChangeText={setSamplesPerCheck} keyboardType="number-pad" mono />
      <Field label="Min checks per batch (optional)" value={minChecksPerBatch} onChangeText={setMinChecksPerBatch} keyboardType="number-pad" mono />
      <Field label="Min checks per day (optional)" value={minChecksPerDay} onChangeText={setMinChecksPerDay} keyboardType="number-pad" mono />

      <View style={styles.paramsHead}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{`${t('Parameters').toUpperCase()} · ${params.length}`}</Mono>
        <View style={{ flex: 1 }} />
        <Button title={t('Add parameter')} size="sm" variant="ghost" onPress={() => setParams((p) => [...p, emptyParam()])} />
      </View>

      {params.map((p, i) => (
        <View key={i} style={styles.paramBox}>
          <View style={styles.paramHead}>
            <Mono size={9} color={colors.faint} letterSpacing={0.6}>{`${t('Parameter').toUpperCase()} ${i + 1}`}</Mono>
            <View style={{ flex: 1 }} />
            {params.length > 1 ? (
              <Pressable onPress={() => setParams((cur) => cur.filter((_, j) => j !== i))} hitSlop={8}>
                <FontAwesome name="trash-o" size={16} color={colors.blocked} />
              </Pressable>
            ) : null}
          </View>
          <Field label="Name" value={p.name} onChangeText={(v) => setParam(i, { name: v })} />
          <View style={{ gap: 8 }}>
            <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Type').toUpperCase()}</Mono>
            <ChipRow>
              <SelectionChip label={t('Measurement')} active={p.type === 'measurement'} onPress={() => setParam(i, { type: 'measurement' })} />
              <SelectionChip label={t('Pass / fail')} active={p.type === 'pass_fail'} onPress={() => setParam(i, { type: 'pass_fail' })} />
            </ChipRow>
          </View>
          {p.type === 'measurement' ? (
            <>
              <Field label="Unit (optional)" value={p.unit} onChangeText={(v) => setParam(i, { unit: v })} placeholder="e.g. mm, °C" />
              <Field label="Min (optional)" value={p.min} onChangeText={(v) => setParam(i, { min: v })} keyboardType="decimal-pad" mono />
              <Field label="Max (optional)" value={p.max} onChangeText={(v) => setParam(i, { max: v })} keyboardType="decimal-pad" mono />
            </>
          ) : null}
        </View>
      ))}
      {errors.params ? <Text style={styles.error}>{t(errors.params)}</Text> : null}

      <View style={styles.actions}>
        <Button title={t('Cancel')} variant="ghost" onPress={() => router.back()} />
        <View style={{ flex: 1 }} />
        <Button title={t('Save')} onPress={onSave} loading={m.isPending} disabled={m.isPending} />
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  paramsHead: { flexDirection: 'row', alignItems: 'center', marginBottom: -4 },
  paramBox: { gap: 12, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  paramHead: { flexDirection: 'row', alignItems: 'center' },
  error: { fontSize: 12, color: colors.blocked, fontFamily: fonts.sans.native.regular },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
});
