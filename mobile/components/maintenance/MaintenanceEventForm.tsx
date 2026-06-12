import { zodResolver } from '@hookform/resolvers/zod';
import { FontAwesome } from '@expo/vector-icons';
import { Controller, useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { Pressable, StyleSheet, View } from 'react-native';
import { z } from 'zod';

import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { ChipRow, SelectionChip } from '@/components/ui/SelectionChip';
import { ControlledField } from '@/components/ui/ControlledField';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { nonEmpty } from '@/lib/forms/zod';
import type {
  CreateMaintenanceEventPayload,
  MaintenanceEvent,
  MaintenanceEventType,
} from '@/api/maintenance';

interface TypeOption {
  id: MaintenanceEventType;
  label: string;
  color: string;
  icon: React.ComponentProps<typeof FontAwesome>['name'];
}

const TYPES: TypeOption[] = [
  { id: 'planned', label: 'Preventive', color: '#3a6ed6', icon: 'cog' },
  { id: 'corrective', label: 'Corrective', color: '#dc2626', icon: 'wrench' },
  { id: 'inspection', label: 'Inspection', color: '#1f9d6c', icon: 'shield' },
];

export const eventSchema = z.object({
  title: nonEmpty('Title is required'),
  event_type: z.enum(['planned', 'corrective', 'inspection']),
  target_kind: z.enum(['tool', 'line', 'workstation', 'none']),
  target_id: z.union([z.literal(''), z.coerce.number().int().positive()])
    .transform((v) => (v === '' ? null : v)),
  scheduled_at: z.string().trim(),
  assigned_to_id: z.union([z.literal(''), z.coerce.number().int().positive()])
    .transform((v) => (v === '' ? null : v)),
  cost_source_id: z.union([z.literal(''), z.coerce.number().int().positive()])
    .transform((v) => (v === '' ? null : v)),
  actual_cost: z.union([z.literal(''), z.coerce.number().min(0)])
    .transform((v) => (v === '' ? null : v)),
  currency: z.string().trim(),
  description: z.string().trim(),
});

export type EventFormInput = z.input<typeof eventSchema>;
export type EventFormValues = z.output<typeof eventSchema>;

interface PickItem {
  id: number;
  name: string;
}

interface Props {
  initial?: Partial<MaintenanceEvent>;
  mode: 'create' | 'edit';
  submitting?: boolean;
  tools: PickItem[];
  lines: PickItem[];
  workstations: PickItem[];
  users: PickItem[];
  costSources: PickItem[];
  onSubmit: (input: CreateMaintenanceEventPayload) => void;
}

export function MaintenanceEventForm({
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
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();

  const initialTargetKind: 'tool' | 'line' | 'workstation' | 'none' = initial?.tool_id
    ? 'tool'
    : initial?.line_id
      ? 'line'
      : initial?.workstation_id
        ? 'workstation'
        : 'none';
  const initialTargetId =
    initial?.tool_id ?? initial?.line_id ?? initial?.workstation_id ?? '';

  const { control, handleSubmit, watch, formState: { isValid } } = useForm<
    EventFormInput,
    unknown,
    EventFormValues
  >({
    resolver: zodResolver(eventSchema),
    mode: 'onChange',
    defaultValues: {
      title: initial?.title ?? '',
      event_type: (initial?.event_type as MaintenanceEventType) ?? 'corrective',
      target_kind: initialTargetKind,
      target_id: typeof initialTargetId === 'number' ? initialTargetId : '',
      scheduled_at: initial?.scheduled_at ?? '',
      assigned_to_id:
        initial?.assigned_to_id != null ? String(initial.assigned_to_id) : '',
      cost_source_id:
        initial?.cost_source_id != null ? String(initial.cost_source_id) : '',
      actual_cost:
        initial?.actual_cost != null ? String(initial.actual_cost) : '',
      currency: initial?.currency ?? '',
      description: initial?.description ?? '',
    },
  });

  const targetKind = watch('target_kind');
  const targetOptions =
    targetKind === 'tool'
      ? tools
      : targetKind === 'line'
        ? lines
        : targetKind === 'workstation'
          ? workstations
          : [];

  return (
    <View style={{ gap: 14 }}>
      {/* What — title, type, description */}
      <Card style={{ gap: 12 }}>
        <SectionLabel>What</SectionLabel>
        <ControlledField
          control={control}
          name="title"
          label="Title"
          placeholder="Drive belt wear — replace"
        />
        <Mono size={10.5} color={palette.textFaint} letterSpacing={0.7} weight="700">
          {t('TYPE').toUpperCase()}
        </Mono>
        <Controller
          control={control}
          name="event_type"
          render={({ field: { value, onChange } }) => (
            <View style={styles.typeGrid}>
              {TYPES.map((opt) => {
                const on = opt.id === value;
                return (
                  <Pressable
                    key={opt.id}
                    onPress={() => onChange(opt.id)}
                    style={({ pressed }) => [
                      styles.typeCard,
                      {
                        backgroundColor: on ? BRAND.amberSoft : palette.surface,
                        borderColor: on ? BRAND.amber : palette.border,
                        opacity: pressed ? 0.9 : 1,
                      },
                    ]}>
                    <FontAwesome name={opt.icon} size={22} color={opt.color} />
                    <Mono
                      size={11}
                      color={palette.text}
                      weight="700"
                      letterSpacing={0.4}
                      style={{ marginTop: 6 }}>
                      {t(opt.label).toUpperCase()}
                    </Mono>
                  </Pressable>
                );
              })}
            </View>
          )}
        />
        <ControlledField
          control={control}
          name="description"
          label="Description"
          multiline
          numberOfLines={3}
          style={{ minHeight: 80, textAlignVertical: 'top' }}
        />
      </Card>

      {/* Where — tool/line/workstation */}
      <Card style={{ gap: 12 }}>
        <SectionLabel>Where</SectionLabel>
        <Mono size={10.5}>{t('Target kind').toUpperCase()}</Mono>
        <Controller
          control={control}
          name="target_kind"
          render={({ field: { value, onChange } }) => (
            <ChipRow>
              {(['tool', 'line', 'workstation', 'none'] as const).map((k) => (
                <SelectionChip
                  key={k}
                  label={t(k === 'none' ? 'None' : k.charAt(0).toUpperCase() + k.slice(1))}
                  active={k === value}
                  onPress={() => onChange(k)}
                />
              ))}
            </ChipRow>
          )}
        />
        {targetKind !== 'none' ? (
          <>
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
          </>
        ) : null}
      </Card>

      {/* When — scheduled timestamp */}
      <Card style={{ gap: 12 }}>
        <SectionLabel>When</SectionLabel>
        <ControlledField
          control={control}
          name="scheduled_at"
          label="Scheduled at (ISO)"
          autoCapitalize="none"
          autoCorrect={false}
          placeholder="2026-06-01T08:00:00"
          hint="ISO 8601 — minute precision"
        />
      </Card>

      {/* Who — assignee */}
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

      {/* Cost — source, actual amount, currency */}
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
        <View style={styles.row}>
          <View style={{ flex: 1.4 }}>
            <ControlledField
              control={control}
              name="actual_cost"
              label="Actual cost"
              keyboardType="decimal-pad"
              hint="Filled in after completion"
            />
          </View>
          <View style={{ flex: 1 }}>
            <ControlledField
              control={control}
              name="currency"
              label="Currency"
              autoCapitalize="characters"
              autoCorrect={false}
              placeholder="PLN"
            />
          </View>
        </View>
      </Card>

      <Button
        title={mode === 'create' ? 'Schedule event' : 'Save changes'}
        size="lg"
        loading={!!submitting}
        disabled={!isValid}
        onPress={handleSubmit((v) => {
          // Map the unified target picker back to the three nullable FK
          // columns the backend expects.
          onSubmit({
            title: v.title,
            event_type: v.event_type,
            tool_id: v.target_kind === 'tool' ? v.target_id ?? undefined : undefined,
            line_id: v.target_kind === 'line' ? v.target_id ?? undefined : undefined,
            workstation_id:
              v.target_kind === 'workstation' ? v.target_id ?? undefined : undefined,
            assigned_to_id: v.assigned_to_id ?? undefined,
            cost_source_id: v.cost_source_id ?? undefined,
            scheduled_at: v.scheduled_at || undefined,
            description: v.description || undefined,
            actual_cost: v.actual_cost ?? undefined,
            currency: v.currency || undefined,
          });
        })}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  typeGrid: { flexDirection: 'row', gap: 6 },
  typeCard: {
    flex: 1,
    padding: 14,
    borderRadius: 10,
    borderWidth: 1,
    alignItems: 'center',
  },
  row: { flexDirection: 'row', gap: 10 },
});
