import { useMemo, useState } from 'react';
import { Alert, StyleSheet, Text, View } from 'react-native';
import { FontAwesome } from '@expo/vector-icons';

import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Field } from '@/components/ui/Field';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import { Switch } from '@/components/ui/Switch';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import {
  useCreateQualityCheck,
  useQcStatus,
  useQualityChecks,
} from '@/hooks/queries/useProductionControls';
import type { QcSampleInput } from '@/api/productionControls';

interface Props {
  batchId: number;
}

export function QualityChecksCard({ batchId }: Props) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  const status = useQcStatus(batchId);
  const list = useQualityChecks(batchId);
  const create = useCreateQualityCheck(batchId);

  const template = status.data?.template ?? null;
  const required = !!status.data?.required;
  const checksDone = status.data?.checks_done ?? list.data?.length ?? 0;
  const checksRequired = status.data?.checks_required ?? template?.min_checks_per_batch ?? 0;

  const [open, setOpen] = useState(false);
  const [productionQty, setProductionQty] = useState('');
  const [notes, setNotes] = useState('');

  const initialSamples: QcSampleInput[] = useMemo(() => {
    if (template?.parameters?.length) {
      return template.parameters.map((p) => ({
        sample_number: 1,
        parameter_name: p.name,
        parameter_type: p.type,
        value_numeric: null,
        value_boolean: null,
        is_passed: null,
      }));
    }
    return [
      {
        sample_number: 1,
        parameter_name: 'Visual',
        parameter_type: 'pass_fail',
        is_passed: true,
      },
    ];
  }, [template?.parameters]);

  const [samples, setSamples] = useState<QcSampleInput[]>(initialSamples);

  const updateSample = (idx: number, patch: Partial<QcSampleInput>) =>
    setSamples((arr) => arr.map((s, i) => (i === idx ? { ...s, ...patch } : s)));

  const reset = () => {
    setOpen(false);
    setProductionQty('');
    setNotes('');
    setSamples(initialSamples);
  };

  const submit = () => {
    create.mutate(
      {
        samples,
        production_quantity: productionQty ? Number(productionQty) : null,
        quality_check_template_id: template?.id ?? null,
        notes: notes || null,
      },
      {
        onSuccess: reset,
        onError: (e: Error) => Alert.alert('Quality check failed', e.message),
      },
    );
  };

  const meetsRequired = !required || checksDone >= checksRequired;
  const tone = !meetsRequired ? palette.warning : checksDone > 0 ? palette.success : palette.textFaint;

  return (
    <Card style={{ gap: 12 }}>
      <SectionLabel
        right={
          <Mono size={11} color={tone} weight="700" letterSpacing={0.6}>
            {checksDone}
            {checksRequired ? `/${checksRequired}` : ''}
            {required ? ' REQUIRED' : ''}
          </Mono>
        }>
        Quality checks
      </SectionLabel>

      {template ? (
        <Mono size={11} color={palette.textFaint}>
          TEMPLATE · {template.name.toUpperCase()} · {template.parameters.length} PARAM
          {template.parameters.length === 1 ? '' : 'S'}
        </Mono>
      ) : null}

      {open ? (
        <View style={{ gap: 10 }}>
          <Field
            label="Production qty (optional)"
            value={productionQty}
            onChangeText={setProductionQty}
            keyboardType="decimal-pad"
          />
          {samples.map((s, idx) => (
            <View
              key={idx}
              style={[styles.sampleBox, { borderColor: palette.border, backgroundColor: palette.surface }]}>
              <View style={styles.sampleHeader}>
                <Mono size={10} color={palette.textFaint} letterSpacing={0.6}>
                  PARAM {idx + 1}
                </Mono>
                <Text style={[styles.sampleLabel, { color: palette.text }]}>{s.parameter_name}</Text>
              </View>
              {s.parameter_type === 'measurement' ? (
                <Field
                  label="Value"
                  value={s.value_numeric != null ? String(s.value_numeric) : ''}
                  onChangeText={(v) =>
                    updateSample(idx, { value_numeric: v === '' ? null : Number(v) })
                  }
                  keyboardType="decimal-pad"
                />
              ) : (
                <View style={styles.passFailRow}>
                  <Mono size={11} color={palette.textMuted}>PASS</Mono>
                  <Switch
                    value={!!s.is_passed}
                    onValueChange={(v) => updateSample(idx, { is_passed: v, value_boolean: v })}
                  />
                </View>
              )}
            </View>
          ))}
          <Field
            label="Notes (optional)"
            value={notes}
            onChangeText={setNotes}
            multiline
            numberOfLines={2}
            style={{ minHeight: 60, textAlignVertical: 'top' }}
          />
          <View style={{ flexDirection: 'row', gap: 8 }}>
            <Button
              title="Submit"
              onPress={submit}
              loading={create.isPending}
              style={{ flex: 1 }}
              leftIcon={<FontAwesome name="check" size={13} color="#1a1208" />}
            />
            <Button title="Cancel" variant="outline" onPress={reset} style={{ flex: 1 }} />
          </View>
        </View>
      ) : (
        <Button
          title="Record QC"
          variant="outline"
          onPress={() => setOpen(true)}
          leftIcon={<FontAwesome name="check-square-o" size={13} color={palette.text} />}
        />
      )}
    </Card>
  );
}

const styles = StyleSheet.create({
  sampleBox: { borderWidth: 1, borderRadius: 12, padding: 12, gap: 10 },
  sampleHeader: { gap: 2 },
  sampleLabel: { fontSize: 14, fontWeight: '600', letterSpacing: -0.2 },
  passFailRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
});
