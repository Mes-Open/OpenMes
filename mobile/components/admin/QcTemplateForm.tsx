import { zodResolver } from '@hookform/resolvers/zod';
import { useFieldArray, useForm } from 'react-hook-form';
import { Pressable, StyleSheet, View } from 'react-native';
import { z } from 'zod';
import { FontAwesome } from '@expo/vector-icons';

import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { ChipRow, SelectionChip } from '@/components/ui/SelectionChip';
import { ControlledField } from '@/components/ui/ControlledField';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { nonEmpty } from '@/lib/forms/zod';
import type { QualityCheckTemplate } from '@/api/productionControls';

const parameterSchema = z.object({
  name: nonEmpty(),
  type: z.enum(['measurement', 'pass_fail']),
  unit: z.string().trim(),
  min: z.string().trim(),
  max: z.string().trim(),
});

export const qcTemplateSchema = z.object({
  name: nonEmpty(),
  min_checks_per_batch: z.string().trim(),
  min_checks_per_day: z.string().trim(),
  samples_per_check: z.string().trim(),
  parameters: z.array(parameterSchema).min(1, 'Add at least one parameter'),
});

export type QcTemplateFormValues = z.infer<typeof qcTemplateSchema>;

interface Props {
  initial?: Partial<QualityCheckTemplate>;
  mode: 'create' | 'edit';
  onSubmit: (values: QcTemplateFormValues) => void;
  submitting?: boolean;
}

export function QcTemplateForm({ initial, mode, onSubmit, submitting }: Props) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  const { control, handleSubmit, setValue, watch, formState: { isValid } } =
    useForm<QcTemplateFormValues>({
      resolver: zodResolver(qcTemplateSchema),
      mode: 'onChange',
      defaultValues: {
        name: initial?.name ?? '',
        min_checks_per_batch:
          initial?.min_checks_per_batch != null ? String(initial.min_checks_per_batch) : '',
        min_checks_per_day:
          initial?.min_checks_per_day != null ? String(initial.min_checks_per_day) : '',
        samples_per_check:
          initial?.samples_per_check != null ? String(initial.samples_per_check) : '',
        parameters: initial?.parameters?.length
          ? initial.parameters.map((p) => ({
              name: p.name,
              type: p.type,
              unit: p.unit ?? '',
              min: p.min != null ? String(p.min) : '',
              max: p.max != null ? String(p.max) : '',
            }))
          : [{ name: '', type: 'measurement', unit: '', min: '', max: '' }],
      },
    });

  const { fields, append, remove } = useFieldArray({ control, name: 'parameters' });
  const params = watch('parameters');

  return (
    <View style={{ gap: 14 }}>
      <Card style={{ gap: 12 }}>
        <SectionLabel>Template</SectionLabel>
        <ControlledField control={control} name="name" label="Name" placeholder="e.g. Final inspection" />
        <ControlledField
          control={control}
          name="samples_per_check"
          label="Samples per check (optional)"
          keyboardType="number-pad"
        />
        <ControlledField
          control={control}
          name="min_checks_per_batch"
          label="Min checks per batch (optional)"
          keyboardType="number-pad"
        />
        <ControlledField
          control={control}
          name="min_checks_per_day"
          label="Min checks per day (optional)"
          keyboardType="number-pad"
        />
      </Card>

      <Card style={{ gap: 12 }}>
        <SectionLabel
          right={
            <Pressable
              hitSlop={6}
              onPress={() => append({ name: '', type: 'measurement', unit: '', min: '', max: '' })}
              style={styles.addBtn}>
              <FontAwesome name="plus" size={11} color="#1a1208" />
              <Mono size={11} color="#1a1208" weight="700" letterSpacing={0.4}>
                ADD PARAM
              </Mono>
            </Pressable>
          }>
          {`Parameters · ${fields.length}`}
        </SectionLabel>

        {fields.map((f, idx) => {
          const type = params?.[idx]?.type;
          return (
            <View
              key={f.id}
              style={[styles.paramBox, { borderColor: palette.border, backgroundColor: palette.surfaceAlt }]}>
              <View style={styles.paramHeader}>
                <Mono size={10} color={palette.textFaint} letterSpacing={0.6}>
                  PARAM {idx + 1}
                </Mono>
                {fields.length > 1 ? (
                  <Pressable onPress={() => remove(idx)} hitSlop={6}>
                    <Mono size={11} color={palette.danger} weight="700">REMOVE</Mono>
                  </Pressable>
                ) : null}
              </View>
              <ControlledField control={control} name={`parameters.${idx}.name` as const} label="Name" />
              <View style={{ gap: 8 }}>
                <Mono size={10} color={palette.textFaint} letterSpacing={0.8}>TYPE</Mono>
                <ChipRow>
                  {(
                    [
                      { id: 'measurement', label: 'Measurement' },
                      { id: 'pass_fail', label: 'Pass / fail' },
                    ] as const
                  ).map((t) => (
                    <SelectionChip
                      key={t.id}
                      label={t.label}
                      active={type === t.id}
                      onPress={() => setValue(`parameters.${idx}.type`, t.id, { shouldValidate: true })}
                    />
                  ))}
                </ChipRow>
              </View>
              {type === 'measurement' ? (
                <>
                  <ControlledField
                    control={control}
                    name={`parameters.${idx}.unit` as const}
                    label="Unit (optional)"
                    placeholder="e.g. mm, °C"
                  />
                  <ControlledField
                    control={control}
                    name={`parameters.${idx}.min` as const}
                    label="Min (optional)"
                    keyboardType="decimal-pad"
                  />
                  <ControlledField
                    control={control}
                    name={`parameters.${idx}.max` as const}
                    label="Max (optional)"
                    keyboardType="decimal-pad"
                  />
                </>
              ) : null}
            </View>
          );
        })}
      </Card>

      <Button
        title={mode === 'create' ? 'Create QC template' : 'Save changes'}
        size="lg"
        loading={!!submitting}
        disabled={!isValid}
        onPress={handleSubmit(onSubmit)}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  addBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingVertical: 4,
    paddingHorizontal: 8,
    borderRadius: 6,
    backgroundColor: '#fbe9c8',
  },
  paramBox: { borderWidth: 1, borderRadius: 12, padding: 12, gap: 10 },
  paramHeader: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
});
