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

export const simpleCodeNameSchema = z.object({
  code: nonEmpty(),
  name: nonEmpty(),
  description: z.string().trim(),
  is_active: z.boolean(),
});

export type SimpleCodeNameValues = z.infer<typeof simpleCodeNameSchema>;

interface Props {
  initial?: Partial<SimpleCodeNameValues>;
  mode: 'create' | 'edit';
  entityLabel: string;
  onSubmit: (values: SimpleCodeNameValues) => void;
  submitting?: boolean;
}

export function SimpleCodeNameForm({ initial, mode, entityLabel, onSubmit, submitting }: Props) {
  const { control, handleSubmit, formState: { isValid } } = useForm<SimpleCodeNameValues>({
    resolver: zodResolver(simpleCodeNameSchema),
    mode: 'onChange',
    defaultValues: {
      code: initial?.code ?? '',
      name: initial?.name ?? '',
      description: initial?.description ?? '',
      is_active: initial?.is_active ?? true,
    },
  });

  return (
    <View style={{ gap: 14 }}>
      <Card style={{ gap: 12 }}>
        <SectionLabel>{entityLabel}</SectionLabel>
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

      <ActiveToggleCard control={control} name="is_active" />

      <Button
        title={mode === 'create' ? `Create ${entityLabel}` : 'Save changes'}
        size="lg"
        loading={!!submitting}
        disabled={!isValid}
        onPress={handleSubmit(onSubmit)}
      />
    </View>
  );
}
