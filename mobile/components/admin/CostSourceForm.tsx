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
import type { CostSource } from '@/api/ops';

export const costSourceSchema = z.object({
  code: nonEmpty(),
  name: nonEmpty(),
  description: z.string().trim(),
  unit_cost: z.string().trim(),
  unit: z.string().trim(),
  currency: z.string().trim(),
  is_active: z.boolean(),
});

export type CostSourceFormValues = z.infer<typeof costSourceSchema>;

interface Props {
  initial?: Partial<CostSource>;
  mode: 'create' | 'edit';
  onSubmit: (values: CostSourceFormValues) => void;
  submitting?: boolean;
}

export function CostSourceForm({ initial, mode, onSubmit, submitting }: Props) {
  const { control, handleSubmit, formState: { isValid } } = useForm<CostSourceFormValues>({
    resolver: zodResolver(costSourceSchema),
    mode: 'onChange',
    defaultValues: {
      code: initial?.code ?? '',
      name: initial?.name ?? '',
      description: initial?.description ?? '',
      unit_cost: initial?.unit_cost?.toString() ?? '',
      unit: initial?.unit ?? '',
      currency: initial?.currency ?? '',
      is_active: initial?.is_active ?? true,
    },
  });

  return (
    <View style={{ gap: 14 }}>
      <Card style={{ gap: 12 }}>
        <SectionLabel>Cost source</SectionLabel>
        <ControlledField control={control} name="code" label="Code" autoCapitalize="characters" autoCorrect={false} />
        <ControlledField control={control} name="name" label="Name" />
      </Card>

      <Card style={{ gap: 12 }}>
        <SectionLabel>Pricing</SectionLabel>
        <ControlledField
          control={control}
          name="unit_cost"
          label="Unit cost"
          keyboardType="decimal-pad"
          placeholder="e.g. 0.20"
        />
        <ControlledField control={control} name="unit" label="Unit" placeholder="e.g. kWh, hour" />
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
        title={mode === 'create' ? 'Create cost source' : 'Save changes'}
        size="lg"
        loading={!!submitting}
        disabled={!isValid}
        onPress={handleSubmit(onSubmit)}
      />
    </View>
  );
}
