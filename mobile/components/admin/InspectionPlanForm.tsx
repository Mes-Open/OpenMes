import { FontAwesome } from '@expo/vector-icons';
import { zodResolver } from '@hookform/resolvers/zod';
import { Controller, useFieldArray, useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { Pressable, StyleSheet, View } from 'react-native';
import { z } from 'zod';

import { ActiveToggleCard } from '@/components/ui/ActiveToggleCard';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { ChipRow, SelectionChip } from '@/components/ui/SelectionChip';
import { ControlledField } from '@/components/ui/ControlledField';
import { ControlledSwitch } from '@/components/ui/ControlledSwitch';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { nonEmpty } from '@/lib/forms/zod';
import type {
  CreateInspectionPlanPayload,
  InspectionCriterionType,
  InspectionPlan,
} from '@/api/inspections';

const TYPES: { id: InspectionCriterionType; label: string }[] = [
  { id: 'visual', label: 'Visual' },
  { id: 'measurement', label: 'Measurement' },
  { id: 'functional', label: 'Functional' },
  { id: 'pass_fail', label: 'Pass / Fail' },
];

// Loose schema for the criterion rows — spec_min/max are kept as strings on
// the form (number-pad input) and serialized to either string or null on
// submit. The backend accepts either.
const criterionSchema = z.object({
  name: nonEmpty('Criterion name is required'),
  type: z.enum(['visual', 'measurement', 'functional', 'pass_fail']),
  required: z.boolean(),
  unit: z.string().trim(),
  spec_min: z.string().trim(),
  spec_max: z.string().trim(),
});

export const planSchema = z.object({
  name: nonEmpty('Plan name is required'),
  description: z.string().trim(),
  material_id: z.union([z.literal(''), z.coerce.number().int().positive()])
    .transform((v) => (v === '' ? null : v)),
  material_type_id: z.union([z.literal(''), z.coerce.number().int().positive()])
    .transform((v) => (v === '' ? null : v)),
  is_active: z.boolean(),
  criteria: z.array(criterionSchema).min(1, 'Add at least one criterion'),
});

export type PlanFormInput = z.input<typeof planSchema>;
export type PlanFormValues = z.output<typeof planSchema>;

interface Props {
  initial?: Partial<InspectionPlan>;
  mode: 'create' | 'edit';
  submitting?: boolean;
  materials: { id: number; name: string }[];
  materialTypes: { id: number; name: string }[];
  onSubmit: (input: CreateInspectionPlanPayload) => void;
}

export function InspectionPlanForm({
  initial,
  mode,
  submitting,
  materials,
  materialTypes,
  onSubmit,
}: Props) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();

  const initialCriteria = (initial?.criteria ?? []).map((c) => ({
    name: c.name ?? '',
    type: (c.type as InspectionCriterionType) ?? 'measurement',
    required: c.required ?? true,
    unit: c.unit ?? '',
    spec_min: c.spec_min != null ? String(c.spec_min) : '',
    spec_max: c.spec_max != null ? String(c.spec_max) : '',
  }));

  const { control, handleSubmit, formState: { isValid } } = useForm<
    PlanFormInput,
    unknown,
    PlanFormValues
  >({
    resolver: zodResolver(planSchema),
    mode: 'onChange',
    defaultValues: {
      name: initial?.name ?? '',
      description: initial?.description ?? '',
      material_id: initial?.material_id != null ? String(initial.material_id) : '',
      material_type_id:
        initial?.material_type_id != null ? String(initial.material_type_id) : '',
      is_active: initial?.is_active ?? true,
      criteria:
        initialCriteria.length > 0
          ? initialCriteria
          : [{ name: '', type: 'measurement', required: true, unit: '', spec_min: '', spec_max: '' }],
    },
  });

  const { fields, append, remove } = useFieldArray({ control, name: 'criteria' });

  return (
    <View style={{ gap: 14 }}>
      <Card style={{ gap: 12 }}>
        <SectionLabel>Plan</SectionLabel>
        <ControlledField control={control} name="name" label="Plan name" />
        <ControlledField
          control={control}
          name="description"
          label="Description"
          multiline
          numberOfLines={2}
          style={{ minHeight: 60, textAlignVertical: 'top' }}
        />
      </Card>

      <Card style={{ gap: 12 }}>
        <SectionLabel>Applies to</SectionLabel>
        <Mono size={10.5}>{t('Material (optional)').toUpperCase()}</Mono>
        <Controller
          control={control}
          name="material_id"
          render={({ field: { value, onChange } }) => (
            <ChipRow>
              <SelectionChip
                label={t('Any')}
                active={!value || value === ''}
                onPress={() => onChange('')}
              />
              {materials.map((m) => (
                <SelectionChip
                  key={m.id}
                  label={m.name}
                  active={String(m.id) === String(value)}
                  onPress={() => onChange(m.id)}
                />
              ))}
            </ChipRow>
          )}
        />
        <Mono size={10.5}>{t('Material type (optional)').toUpperCase()}</Mono>
        <Controller
          control={control}
          name="material_type_id"
          render={({ field: { value, onChange } }) => (
            <ChipRow>
              <SelectionChip
                label={t('Any')}
                active={!value || value === ''}
                onPress={() => onChange('')}
              />
              {materialTypes.map((mt) => (
                <SelectionChip
                  key={mt.id}
                  label={mt.name}
                  active={String(mt.id) === String(value)}
                  onPress={() => onChange(mt.id)}
                />
              ))}
            </ChipRow>
          )}
        />
        <Mono size={10.5} color={palette.textFaint}>
          {t('Leave both blank for a plan that applies to anything.').toUpperCase()}
        </Mono>
      </Card>

      <Card style={{ gap: 12 }}>
        <SectionLabel>Criteria</SectionLabel>
        {fields.map((f, idx) => (
          <View
            key={f.id}
            style={[
              styles.criterion,
              { backgroundColor: palette.surfaceAlt, borderColor: palette.border },
            ]}>
            <View style={styles.criterionHead}>
              <Mono size={10.5} color={palette.textFaint} letterSpacing={0.6} weight="700">
                {t('CRITERION')} #{idx + 1}
              </Mono>
              {fields.length > 1 ? (
                <Pressable
                  onPress={() => remove(idx)}
                  hitSlop={8}
                  style={({ pressed }) => [styles.removeBtn, { opacity: pressed ? 0.5 : 1 }]}>
                  <FontAwesome name="trash" size={12} color={palette.danger} />
                  <Mono size={10} color={palette.danger} weight="700" letterSpacing={0.5}>
                    {t('REMOVE')}
                  </Mono>
                </Pressable>
              ) : null}
            </View>
            <ControlledField
              control={control}
              name={`criteria.${idx}.name` as const}
              label="Name"
              placeholder="Tensile strength"
            />
            <Mono size={10.5}>{t('Type').toUpperCase()}</Mono>
            <Controller
              control={control}
              name={`criteria.${idx}.type` as const}
              render={({ field: { value, onChange } }) => (
                <ChipRow>
                  {TYPES.map((t2) => (
                    <SelectionChip
                      key={t2.id}
                      label={t(t2.label)}
                      active={t2.id === value}
                      onPress={() => onChange(t2.id)}
                    />
                  ))}
                </ChipRow>
              )}
            />
            <View style={styles.row}>
              <View style={{ flex: 1 }}>
                <ControlledField
                  control={control}
                  name={`criteria.${idx}.unit` as const}
                  label="Unit"
                  placeholder="N"
                  autoCapitalize="none"
                  autoCorrect={false}
                />
              </View>
              <View style={{ flex: 1 }}>
                <ControlledField
                  control={control}
                  name={`criteria.${idx}.spec_min` as const}
                  label="Spec min"
                  keyboardType="decimal-pad"
                />
              </View>
              <View style={{ flex: 1 }}>
                <ControlledField
                  control={control}
                  name={`criteria.${idx}.spec_max` as const}
                  label="Spec max"
                  keyboardType="decimal-pad"
                />
              </View>
            </View>
            <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
              <Mono size={11}>{t('REQUIRED')}</Mono>
              <ControlledSwitch
                control={control}
                name={`criteria.${idx}.required` as const}
              />
            </View>
          </View>
        ))}

        <Pressable
          onPress={() =>
            append({
              name: '',
              type: 'measurement',
              required: true,
              unit: '',
              spec_min: '',
              spec_max: '',
            })
          }
          style={({ pressed }) => [
            styles.addBtn,
            { borderColor: palette.border, opacity: pressed ? 0.7 : 1 },
          ]}>
          <FontAwesome name="plus" size={12} color={palette.text} />
          <Mono size={11} color={palette.text} weight="700" letterSpacing={0.5}>
            {t('ADD CRITERION')}
          </Mono>
        </Pressable>
      </Card>

      <ActiveToggleCard control={control} name="is_active" />

      <Button
        title={mode === 'create' ? 'Create plan' : 'Save changes'}
        size="lg"
        loading={!!submitting}
        disabled={!isValid}
        onPress={handleSubmit((v) => {
          onSubmit({
            name: v.name,
            description: v.description || undefined,
            material_id: v.material_id ?? undefined,
            material_type_id: v.material_type_id ?? undefined,
            is_active: v.is_active,
            criteria: v.criteria.map((c) => ({
              name: c.name,
              type: c.type,
              required: c.required,
              unit: c.unit || null,
              spec_min: c.spec_min ? Number(c.spec_min) : null,
              spec_max: c.spec_max ? Number(c.spec_max) : null,
            })),
          });
        })}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  criterion: {
    padding: 12,
    borderRadius: 10,
    borderWidth: 1,
    gap: 10,
  },
  criterionHead: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  removeBtn: { flexDirection: 'row', alignItems: 'center', gap: 5, padding: 4 },
  row: { flexDirection: 'row', gap: 10 },
  addBtn: {
    marginTop: 4,
    height: 40,
    borderRadius: 10,
    borderWidth: 1,
    borderStyle: 'dashed',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
});
