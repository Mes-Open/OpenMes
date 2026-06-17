import { zodResolver } from '@hookform/resolvers/zod';
import { Controller, useForm } from 'react-hook-form';
import { View } from 'react-native';
import { z } from 'zod';

import { ActiveToggleCard } from '@/components/ui/ActiveToggleCard';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { ChipRow, SelectionChip } from '@/components/ui/SelectionChip';
import { ControlledField } from '@/components/ui/ControlledField';
import { SectionLabel } from '@/components/ui/Mono';
import { useLines } from '@/hooks/queries/useUsers';
import { nonEmpty } from '@/lib/forms/zod';
import type { Shift } from '@/api/ops';

const TIME_RE = /^([01]\d|2[0-3]):[0-5]\d$/;
const time = z.string().regex(TIME_RE, 'HH:mm');

export const shiftSchema = z.object({
  name: nonEmpty(),
  start_time: time,
  end_time: time,
  days_of_week: z.array(z.number()),
  line_id: z.number().nullable(),
  is_active: z.boolean(),
});

export type ShiftFormValues = z.infer<typeof shiftSchema>;

interface Props {
  initial?: Partial<Shift>;
  mode: 'create' | 'edit';
  onSubmit: (values: ShiftFormValues) => void;
  submitting?: boolean;
}

const DAYS = [
  { num: 1, label: 'Mon' },
  { num: 2, label: 'Tue' },
  { num: 3, label: 'Wed' },
  { num: 4, label: 'Thu' },
  { num: 5, label: 'Fri' },
  { num: 6, label: 'Sat' },
  { num: 7, label: 'Sun' },
];

export function ShiftForm({ initial, mode, onSubmit, submitting }: Props) {
  const linesQuery = useLines();

  const { control, handleSubmit, formState: { isValid } } = useForm<ShiftFormValues>({
    resolver: zodResolver(shiftSchema),
    mode: 'onChange',
    defaultValues: {
      name: initial?.name ?? '',
      start_time: initial?.start_time?.slice(0, 5) ?? '06:00',
      end_time: initial?.end_time?.slice(0, 5) ?? '14:00',
      days_of_week: initial?.days_of_week ?? [1, 2, 3, 4, 5],
      line_id: initial?.line_id ?? null,
      is_active: initial?.is_active ?? true,
    },
  });

  return (
    <View style={{ gap: 14 }}>
      <Card style={{ gap: 12 }}>
        <SectionLabel>Shift</SectionLabel>
        <ControlledField control={control} name="name" label="Name" placeholder="e.g. Morning, Night" />
        <ControlledField
          control={control}
          name="start_time"
          label="Start time"
          placeholder="HH:mm"
          autoCorrect={false}
        />
        <ControlledField
          control={control}
          name="end_time"
          label="End time"
          placeholder="HH:mm"
          autoCorrect={false}
        />
      </Card>

      <Card style={{ gap: 12 }}>
        <SectionLabel>Days of week</SectionLabel>
        <Controller
          control={control}
          name="days_of_week"
          render={({ field: { value, onChange } }) => (
            <ChipRow>
              {DAYS.map((d) => (
                <SelectionChip
                  key={d.num}
                  label={d.label}
                  active={value.includes(d.num)}
                  onPress={() =>
                    onChange(
                      value.includes(d.num)
                        ? value.filter((x) => x !== d.num)
                        : [...value, d.num].sort(),
                    )
                  }
                />
              ))}
            </ChipRow>
          )}
        />
      </Card>

      <Card style={{ gap: 12 }}>
        <SectionLabel>Line (optional)</SectionLabel>
        <Controller
          control={control}
          name="line_id"
          render={({ field: { value, onChange } }) => (
            <ChipRow>
              <SelectionChip
                label="All lines"
                active={value === null}
                onPress={() => onChange(null)}
              />
              {(linesQuery.data ?? []).map((l) => (
                <SelectionChip
                  key={l.id}
                  label={l.name}
                  active={l.id === value}
                  onPress={() => onChange(l.id)}
                />
              ))}
            </ChipRow>
          )}
        />
      </Card>

      <ActiveToggleCard control={control} name="is_active" />

      <Button
        title={mode === 'create' ? 'Create shift' : 'Save changes'}
        size="lg"
        loading={!!submitting}
        disabled={!isValid}
        onPress={handleSubmit(onSubmit)}
      />
    </View>
  );
}
