import { zodResolver } from '@hookform/resolvers/zod';
import { Controller, useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { View } from 'react-native';
import { z } from 'zod';

import { ActiveToggleCard } from '@/components/ui/ActiveToggleCard';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { ChipRow, SelectionChip } from '@/components/ui/SelectionChip';
import { ControlledField } from '@/components/ui/ControlledField';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import { nonEmpty } from '@/lib/forms/zod';
import type {
  CreateProcessSegmentPayload,
  ProcessSegment,
  ProcessSegmentType,
} from '@/api/processSegments';

const SEGMENT_TYPES: { id: ProcessSegmentType; label: string }[] = [
  { id: 'production', label: 'Production' },
  { id: 'inspection', label: 'Inspection' },
  { id: 'maintenance', label: 'Maintenance' },
  { id: 'setup', label: 'Setup' },
  { id: 'cleaning', label: 'Cleaning' },
  { id: 'transport', label: 'Transport' },
  { id: 'other', label: 'Other' },
];

const optInt = (min = 0, max = 100_000) =>
  z.union([z.literal(''), z.coerce.number().int().min(min).max(max)])
    .transform((v) => (v === '' ? null : v));

export const segmentSchema = z.object({
  code: nonEmpty('Code is required'),
  name: nonEmpty('Name is required'),
  description: z.string().trim(),
  segment_type: z.enum([
    'production',
    'inspection',
    'maintenance',
    'setup',
    'cleaning',
    'transport',
    'other',
  ]),
  workstation_type_id: z.union([z.literal(''), z.coerce.number().int().positive()])
    .transform((v) => (v === '' ? null : v)),
  estimated_duration_minutes: optInt(0, 100_000),
  required_operators: optInt(0, 100),
  standard_instruction: z.string().trim(),
  is_active: z.boolean(),
});

export type SegmentFormInput = z.input<typeof segmentSchema>;
export type SegmentFormValues = z.output<typeof segmentSchema>;

interface Props {
  initial?: Partial<ProcessSegment>;
  mode: 'create' | 'edit';
  submitting?: boolean;
  workstationTypes: { id: number; name: string }[];
  onSubmit: (input: CreateProcessSegmentPayload) => void;
}

export function ProcessSegmentForm({
  initial,
  mode,
  submitting,
  workstationTypes,
  onSubmit,
}: Props) {
  const { t } = useTranslation();
  const { control, handleSubmit, formState: { isValid } } = useForm<
    SegmentFormInput,
    unknown,
    SegmentFormValues
  >({
    resolver: zodResolver(segmentSchema),
    mode: 'onChange',
    defaultValues: {
      code: initial?.code ?? '',
      name: initial?.name ?? '',
      description: initial?.description ?? '',
      segment_type: (initial?.segment_type as ProcessSegmentType) ?? 'production',
      workstation_type_id:
        initial?.workstation_type_id != null ? String(initial.workstation_type_id) : '',
      estimated_duration_minutes:
        initial?.estimated_duration_minutes != null
          ? String(initial.estimated_duration_minutes)
          : '',
      required_operators:
        initial?.required_operators != null ? String(initial.required_operators) : '',
      standard_instruction: initial?.standard_instruction ?? '',
      is_active: initial?.is_active ?? true,
    },
  });

  return (
    <View style={{ gap: 14 }}>
      <Card style={{ gap: 12 }}>
        <SectionLabel>Identity</SectionLabel>
        <ControlledField
          control={control}
          name="code"
          label="Code"
          autoCapitalize="characters"
          autoCorrect={false}
        />
        <ControlledField control={control} name="name" label="Name" />
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
        <SectionLabel>Segment type</SectionLabel>
        <Controller
          control={control}
          name="segment_type"
          render={({ field: { value, onChange } }) => (
            <ChipRow>
              {SEGMENT_TYPES.map((s) => (
                <SelectionChip
                  key={s.id}
                  label={t(s.label)}
                  active={s.id === value}
                  onPress={() => onChange(s.id)}
                />
              ))}
            </ChipRow>
          )}
        />
      </Card>

      <Card style={{ gap: 12 }}>
        <SectionLabel>Capacity</SectionLabel>
        <Mono size={10.5}>{t('Workstation type').toUpperCase()}</Mono>
        <Controller
          control={control}
          name="workstation_type_id"
          render={({ field: { value, onChange } }) => (
            <ChipRow>
              <SelectionChip
                label={t('Any')}
                active={!value || value === ''}
                onPress={() => onChange('')}
              />
              {workstationTypes.map((w) => (
                <SelectionChip
                  key={w.id}
                  label={w.name}
                  active={String(w.id) === String(value)}
                  onPress={() => onChange(w.id)}
                />
              ))}
            </ChipRow>
          )}
        />
        <ControlledField
          control={control}
          name="estimated_duration_minutes"
          label="Estimated duration (minutes)"
          keyboardType="number-pad"
        />
        <ControlledField
          control={control}
          name="required_operators"
          label="Required operators"
          keyboardType="number-pad"
        />
        <ControlledField
          control={control}
          name="standard_instruction"
          label="Standard instruction"
          multiline
          numberOfLines={3}
          style={{ minHeight: 80, textAlignVertical: 'top' }}
        />
      </Card>

      <ActiveToggleCard control={control} name="is_active" />

      <Button
        title={mode === 'create' ? 'Create segment' : 'Save changes'}
        size="lg"
        loading={!!submitting}
        disabled={!isValid}
        onPress={handleSubmit((v) => {
          onSubmit({
            code: v.code,
            name: v.name,
            description: v.description || undefined,
            segment_type: v.segment_type,
            workstation_type_id: v.workstation_type_id ?? undefined,
            estimated_duration_minutes: v.estimated_duration_minutes ?? undefined,
            required_operators: v.required_operators ?? undefined,
            standard_instruction: v.standard_instruction || undefined,
            is_active: v.is_active,
          });
        })}
      />
    </View>
  );
}
