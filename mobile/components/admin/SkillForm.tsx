import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { View } from 'react-native';
import { z } from 'zod';

import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { ControlledField } from '@/components/ui/ControlledField';
import { SectionLabel } from '@/components/ui/Mono';
import { nonEmpty } from '@/lib/forms/zod';

export const skillSchema = z.object({
  code: nonEmpty(),
  name: nonEmpty(),
  description: z.string().trim(),
});

export type SkillFormValues = z.infer<typeof skillSchema>;

interface Props {
  initial?: Partial<SkillFormValues>;
  mode: 'create' | 'edit';
  onSubmit: (values: SkillFormValues) => void;
  submitting?: boolean;
}

export function SkillForm({ initial, mode, onSubmit, submitting }: Props) {
  const { control, handleSubmit, formState: { isValid } } = useForm<SkillFormValues>({
    resolver: zodResolver(skillSchema),
    mode: 'onChange',
    defaultValues: {
      code: initial?.code ?? '',
      name: initial?.name ?? '',
      description: initial?.description ?? '',
    },
  });

  return (
    <View style={{ gap: 14 }}>
      <Card style={{ gap: 12 }}>
        <SectionLabel>Skill</SectionLabel>
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
      <Button
        title={mode === 'create' ? 'Create skill' : 'Save changes'}
        size="lg"
        loading={!!submitting}
        disabled={!isValid}
        onPress={handleSubmit(onSubmit)}
      />
    </View>
  );
}
