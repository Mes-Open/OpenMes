import { zodResolver } from '@hookform/resolvers/zod';
import { Controller, useForm } from 'react-hook-form';
import { View } from 'react-native';
import { z } from 'zod';

import { ActiveToggleCard } from '@/components/ui/ActiveToggleCard';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { ChipRow, SelectionChip } from '@/components/ui/SelectionChip';
import { ControlledField } from '@/components/ui/ControlledField';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import { useUsers } from '@/hooks/queries/useUsers';
import { nonEmpty } from '@/lib/forms/zod';

export const crewSchema = z.object({
  code: nonEmpty(),
  name: nonEmpty(),
  description: z.string().trim(),
  leader_id: z.number().nullable(),
  is_active: z.boolean(),
});

export type CrewFormValues = z.infer<typeof crewSchema>;

interface Props {
  initial?: Partial<CrewFormValues>;
  mode: 'create' | 'edit';
  onSubmit: (values: CrewFormValues) => void;
  submitting?: boolean;
}

export function CrewForm({ initial, mode, onSubmit, submitting }: Props) {
  const usersQuery = useUsers({ role: 'Supervisor' });

  const { control, handleSubmit, formState: { isValid } } = useForm<CrewFormValues>({
    resolver: zodResolver(crewSchema),
    mode: 'onChange',
    defaultValues: {
      code: initial?.code ?? '',
      name: initial?.name ?? '',
      description: initial?.description ?? '',
      leader_id: initial?.leader_id ?? null,
      is_active: initial?.is_active ?? true,
    },
  });

  return (
    <View style={{ gap: 14 }}>
      <Card style={{ gap: 12 }}>
        <SectionLabel>Crew</SectionLabel>
        <ControlledField control={control} name="code" label="Code" autoCapitalize="characters" autoCorrect={false} />
        <ControlledField control={control} name="name" label="Name" />
        <ControlledField
          control={control}
          name="description"
          label="Description"
          multiline
          numberOfLines={3}
          style={{ minHeight: 80, textAlignVertical: 'top' }}
        />
      </Card>

      <Card style={{ gap: 12 }}>
        <SectionLabel
          right={<Mono size={11} color="#8a8780">PICK A SUPERVISOR</Mono>}>
          Leader (optional)
        </SectionLabel>
        <Controller
          control={control}
          name="leader_id"
          render={({ field: { value, onChange } }) => (
            <ChipRow>
              <SelectionChip label="None" active={value === null} onPress={() => onChange(null)} />
              {(usersQuery.data?.data ?? []).map((u) => (
                <SelectionChip
                  key={u.id}
                  label={u.name ?? u.username}
                  active={u.id === value}
                  onPress={() => onChange(u.id)}
                />
              ))}
            </ChipRow>
          )}
        />
      </Card>

      <ActiveToggleCard control={control} name="is_active" />

      <Button
        title={mode === 'create' ? 'Create crew' : 'Save changes'}
        size="lg"
        loading={!!submitting}
        disabled={!isValid}
        onPress={handleSubmit(onSubmit)}
      />
    </View>
  );
}
