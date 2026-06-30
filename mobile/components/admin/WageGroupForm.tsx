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

export const wageGroupSchema = z.object({
  code: nonEmpty(),
  name: nonEmpty(),
  description: z.string().trim(),
  base_hourly_rate: z.string().trim(),
  currency: z.string().trim(),
  is_active: z.boolean(),
});

export type WageGroupFormValues = z.infer<typeof wageGroupSchema>;

interface Props {
  initial?: Partial<WageGroupFormValues>;
  mode: 'create' | 'edit';
  onSubmit: (values: WageGroupFormValues) => void;
  submitting?: boolean;
}

export function WageGroupForm({ initial, mode, onSubmit, submitting }: Props) {
  const { control, handleSubmit, formState: { isValid } } = useForm<WageGroupFormValues>({
    resolver: zodResolver(wageGroupSchema),
    mode: 'onChange',
    defaultValues: {
      code: initial?.code ?? '',
      name: initial?.name ?? '',
      description: initial?.description ?? '',
      base_hourly_rate: initial?.base_hourly_rate ?? '',
      currency: initial?.currency ?? '',
      is_active: initial?.is_active ?? true,
    },
  });

  return (
    <View style={{ gap: 14 }}>
      <Card style={{ gap: 12 }}>
        <SectionLabel>Wage group</SectionLabel>
        <ControlledField control={control} name="code" label="Code" autoCapitalize="characters" autoCorrect={false} />
        <ControlledField control={control} name="name" label="Name" />
        <ControlledField
          control={control}
          name="base_hourly_rate"
          label="Hourly rate"
          keyboardType="decimal-pad"
          placeholder="e.g. 25.50"
        />
        <ControlledField control={control} name="currency" label="Currency" autoCapitalize="characters" placeholder="EUR" />
        <ControlledField
          control={control}
          name="description"
          label="Description"
          multiline
          numberOfLines={3}
          style={{ minHeight: 80, textAlignVertical: 'top' }}
        />
      </Card>

      <ActiveToggleCard control={control} name="is_active" />

      <Button
        title={mode === 'create' ? 'Create wage group' : 'Save changes'}
        size="lg"
        loading={!!submitting}
        disabled={!isValid}
        onPress={handleSubmit(onSubmit)}
      />
    </View>
  );
}
