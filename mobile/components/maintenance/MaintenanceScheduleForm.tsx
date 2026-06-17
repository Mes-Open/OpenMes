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
  MaintenanceEventType,
  MaintenanceSchedule,
  MaintenanceScheduleInput,
  ScheduleFrequency,
} from '@/api/maintenanceSchedules';

const intField = (label = 'Required', min = 1, max = 9999) =>
  z.coerce.number({ message: label }).int(label).min(min, label).max(max, label);

const FREQS: { id: ScheduleFrequency; label: string }[] = [
  { id: 'daily', label: 'Daily' },
  { id: 'weekly', label: 'Weekly' },
  { id: 'monthly', label: 'Monthly' },
  { id: 'quarterly', label: 'Quarterly' },
  { id: 'annually', label: 'Annually' },
  { id: 'by_hours', label: 'By hours' },
];

const EVENT_TYPES: { id: MaintenanceEventType; label: string }[] = [
  { id: 'planned', label: 'Planned' },
  { id: 'corrective', label: 'Corrective' },
  { id: 'inspection', label: 'Inspection' },
];

export const scheduleSchema = z
  .object({
    name: nonEmpty('Name is required'),
    description: z.string().trim(),
    event_type: z.enum(['planned', 'corrective', 'inspection']),
    frequency: z.enum(['daily', 'weekly', 'monthly', 'quarterly', 'annually', 'by_hours']),
    interval_value: intField('Interval is required', 1, 9999),
    preferred_time: z.string().trim(),
    lead_time_days: z
      .union([z.literal(''), z.coerce.number().int().min(0).max(30)])
      .transform((v) => (v === '' ? null : v)),
    next_due_at: nonEmpty('Next due date/time is required'),
    target_kind: z.enum(['tool', 'line', 'workstation']),
    target_id: z.coerce.number({ message: 'Pick a target' }).int().positive('Pick a target'),
    assigned_to_id: z.union([z.literal(''), z.coerce.number().int().positive()])
      .transform((v) => (v === '' ? null : v)),
    cost_source_id: z.union([z.literal(''), z.coerce.number().int().positive()])
      .transform((v) => (v === '' ? null : v)),
    is_active: z.boolean(),
  })
  .refine((d) => /^\d{2}:\d{2}$/.test(d.preferred_time) || d.preferred_time === '', {
    message: 'Use HH:mm',
    path: ['preferred_time'],
  });

export type ScheduleFormInput = z.input<typeof scheduleSchema>;
export type ScheduleFormValues = z.output<typeof scheduleSchema>;

interface PickItem {
  id: number;
  name: string;
}

interface Props {
  initial?: Partial<MaintenanceSchedule>;
  mode: 'create' | 'edit';
  submitting?: boolean;
  tools: PickItem[];
  lines: PickItem[];
  workstations: PickItem[];
  users: PickItem[];
  costSources: PickItem[];
  onSubmit: (input: MaintenanceScheduleInput) => void;
}

export function MaintenanceScheduleForm({
  initial,
  mode,
  submitting,
  tools,
  lines,
  workstations,
  users,
  costSources,
  onSubmit,
}: Props) {
  const { t } = useTranslation();

  // Determine which target the existing record uses (default to whichever
  // collection has options; tool first).
  const initialTargetKind: 'tool' | 'line' | 'workstation' = initial?.tool_id
    ? 'tool'
    : initial?.line_id
      ? 'line'
      : initial?.workstation_id
        ? 'workstation'
        : tools.length
          ? 'tool'
          : lines.length
            ? 'line'
            : 'workstation';
  const initialTargetId =
    initial?.tool_id ?? initial?.line_id ?? initial?.workstation_id ?? 0;

  const { control, handleSubmit, watch, formState: { isValid } } = useForm<
    ScheduleFormInput,
    unknown,
    ScheduleFormValues
  >({
    resolver: zodResolver(scheduleSchema),
    mode: 'onChange',
    defaultValues: {
      name: initial?.name ?? '',
      description: initial?.description ?? '',
      event_type: (initial?.event_type as MaintenanceEventType) ?? 'planned',
      frequency: (initial?.frequency as ScheduleFrequency) ?? 'monthly',
      interval_value: String(initial?.interval_value ?? 1),
      preferred_time: initial?.preferred_time ?? '',
      lead_time_days:
        initial?.lead_time_days != null ? String(initial.lead_time_days) : '',
      next_due_at: initial?.next_due_at ?? defaultNextDueIso(),
      target_kind: initialTargetKind,
      target_id: initialTargetId || ('' as unknown as number),
      assigned_to_id:
        initial?.assigned_to_id != null ? String(initial.assigned_to_id) : '',
      cost_source_id:
        initial?.cost_source_id != null ? String(initial.cost_source_id) : '',
      is_active: initial?.is_active ?? true,
    },
  });

  const targetKind = watch('target_kind');
  const targetOptions =
    targetKind === 'tool' ? tools : targetKind === 'line' ? lines : workstations;

  return (
    <View style={{ gap: 14 }}>
      <Card style={{ gap: 12 }}>
        <SectionLabel>What</SectionLabel>
        <ControlledField control={control} name="name" label="Name" autoCorrect={false} />
        <ControlledField
          control={control}
          name="description"
          label="Description"
          multiline
          numberOfLines={2}
          style={{ minHeight: 60, textAlignVertical: 'top' }}
        />
        <Mono size={10.5}>{t('Event type').toUpperCase()}</Mono>
        <Controller
          control={control}
          name="event_type"
          render={({ field: { value, onChange } }) => (
            <ChipRow>
              {EVENT_TYPES.map((e) => (
                <SelectionChip
                  key={e.id}
                  label={t(e.label)}
                  active={e.id === value}
                  onPress={() => onChange(e.id)}
                />
              ))}
            </ChipRow>
          )}
        />
      </Card>

      <Card style={{ gap: 12 }}>
        <SectionLabel>Where</SectionLabel>
        <Mono size={10.5}>{t('Target kind').toUpperCase()}</Mono>
        <Controller
          control={control}
          name="target_kind"
          render={({ field: { value, onChange } }) => (
            <ChipRow>
              {(['tool', 'line', 'workstation'] as const).map((k) => (
                <SelectionChip
                  key={k}
                  label={t(k.charAt(0).toUpperCase() + k.slice(1))}
                  active={k === value}
                  onPress={() => onChange(k)}
                />
              ))}
            </ChipRow>
          )}
        />
        <Mono size={10.5}>{t('Target').toUpperCase()}</Mono>
        <Controller
          control={control}
          name="target_id"
          render={({ field: { value, onChange } }) => (
            <ChipRow>
              {targetOptions.map((o) => (
                <SelectionChip
                  key={o.id}
                  label={o.name}
                  active={String(o.id) === String(value)}
                  onPress={() => onChange(o.id)}
                />
              ))}
            </ChipRow>
          )}
        />
        {targetOptions.length === 0 ? (
          <Mono size={11}>{t('No targets available').toUpperCase()}</Mono>
        ) : null}
      </Card>

      <Card style={{ gap: 12 }}>
        <SectionLabel>When</SectionLabel>
        <Mono size={10.5}>{t('Frequency').toUpperCase()}</Mono>
        <Controller
          control={control}
          name="frequency"
          render={({ field: { value, onChange } }) => (
            <ChipRow>
              {FREQS.map((f) => (
                <SelectionChip
                  key={f.id}
                  label={t(f.label)}
                  active={f.id === value}
                  onPress={() => onChange(f.id)}
                />
              ))}
            </ChipRow>
          )}
        />
        <ControlledField
          control={control}
          name="interval_value"
          label="Every (n)"
          keyboardType="number-pad"
          hint="e.g. 2 with frequency Weekly = every 2 weeks"
        />
        <ControlledField
          control={control}
          name="preferred_time"
          label="Preferred time (HH:mm)"
          autoCapitalize="none"
          autoCorrect={false}
          placeholder="08:00"
        />
        <ControlledField
          control={control}
          name="lead_time_days"
          label="Lead time (days)"
          keyboardType="number-pad"
          hint="Generate the event N days before next_due_at"
        />
        <ControlledField
          control={control}
          name="next_due_at"
          label="Next due (ISO datetime)"
          autoCapitalize="none"
          autoCorrect={false}
          placeholder="2026-06-01T08:00:00"
        />
      </Card>

      <Card style={{ gap: 12 }}>
        <SectionLabel>Who</SectionLabel>
        <Mono size={10.5}>{t('Assigned to').toUpperCase()}</Mono>
        <Controller
          control={control}
          name="assigned_to_id"
          render={({ field: { value, onChange } }) => (
            <ChipRow>
              <SelectionChip
                label={t('Unassigned')}
                active={!value || value === ''}
                onPress={() => onChange('')}
              />
              {users.map((u) => (
                <SelectionChip
                  key={u.id}
                  label={u.name}
                  active={String(u.id) === String(value)}
                  onPress={() => onChange(u.id)}
                />
              ))}
            </ChipRow>
          )}
        />
      </Card>

      <Card style={{ gap: 12 }}>
        <SectionLabel>Cost</SectionLabel>
        <Mono size={10.5}>{t('Cost source').toUpperCase()}</Mono>
        <Controller
          control={control}
          name="cost_source_id"
          render={({ field: { value, onChange } }) => (
            <ChipRow>
              <SelectionChip
                label={t('None')}
                active={!value || value === ''}
                onPress={() => onChange('')}
              />
              {costSources.map((c) => (
                <SelectionChip
                  key={c.id}
                  label={c.name}
                  active={String(c.id) === String(value)}
                  onPress={() => onChange(c.id)}
                />
              ))}
            </ChipRow>
          )}
        />
      </Card>

      <ActiveToggleCard control={control} name="is_active" />

      <Button
        title={mode === 'create' ? 'Create schedule' : 'Save changes'}
        size="lg"
        loading={!!submitting}
        disabled={!isValid}
        onPress={handleSubmit((v) => {
          const input: MaintenanceScheduleInput = {
            name: v.name,
            description: v.description || null,
            event_type: v.event_type,
            frequency: v.frequency,
            interval_value: v.interval_value,
            preferred_time: v.preferred_time || null,
            lead_time_days: v.lead_time_days,
            next_due_at: v.next_due_at,
            assigned_to_id: v.assigned_to_id,
            cost_source_id: v.cost_source_id,
            tool_id: v.target_kind === 'tool' ? v.target_id : null,
            line_id: v.target_kind === 'line' ? v.target_id : null,
            workstation_id: v.target_kind === 'workstation' ? v.target_id : null,
            is_active: v.is_active,
          };
          onSubmit(input);
        })}
      />
    </View>
  );
}

function defaultNextDueIso(): string {
  // Tomorrow at 08:00 local time, ISO without timezone (server interprets).
  const d = new Date();
  d.setDate(d.getDate() + 1);
  d.setHours(8, 0, 0, 0);
  return d.toISOString().slice(0, 19);
}
