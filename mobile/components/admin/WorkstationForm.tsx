import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { View } from 'react-native';
import { z } from 'zod';

import { ActiveToggleCard } from '@/components/ui/ActiveToggleCard';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { ControlledField } from '@/components/ui/ControlledField';
import { SectionLabel } from '@/components/ui/Mono';
import { nonEmpty } from '@/lib/forms/zod';
import type { Workstation } from '@/api/workstations';

export const workstationSchema = z.object({
  code: nonEmpty(),
  name: nonEmpty(),
  workstation_type: z.string().trim(),
  is_active: z.boolean(),
});

export type WorkstationFormValues = z.infer<typeof workstationSchema>;

interface Props {
  initial?: Partial<Workstation>;
  mode: 'create' | 'edit';
  onSubmit: (values: WorkstationFormValues) => void;
  submitting?: boolean;
}

export function WorkstationForm({ initial, mode, onSubmit, submitting }: Props) {
  const { control, handleSubmit, formState: { isValid } } = useForm<WorkstationFormValues>({
    resolver: zodResolver(workstationSchema),
    mode: 'onChange',
    defaultValues: {
      code: initial?.code ?? '',
      name: initial?.name ?? '',
      workstation_type: initial?.workstation_type ?? '',
      is_active: initial?.is_active ?? true,
    },
  });

  return (
    <View style={{ gap: 14 }}>
      <Card style={{ gap: 12 }}>
        <SectionLabel>Workstation</SectionLabel>
        <ControlledField control={control} name="code" label="Code" autoCapitalize="characters" autoCorrect={false} />
        <ControlledField control={control} name="name" label="Name" />
        <ControlledField
          control={control}
          name="workstation_type"
          label="Type (optional)"
          placeholder="e.g. Welding, Assembly"
        />
      </Card>

      <ActiveToggleCard control={control} name="is_active" />

      <Button
        title={mode === 'create' ? 'Create workstation' : 'Save changes'}
        size="lg"
        loading={!!submitting}
        disabled={!isValid}
        onPress={handleSubmit(onSubmit)}
      />
    </View>
  );
}
