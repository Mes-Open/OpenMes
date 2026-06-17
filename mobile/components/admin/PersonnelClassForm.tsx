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
  CreatePersonnelClassPayload,
  PersonnelClass,
} from '@/api/personnel';

export const personnelClassSchema = z.object({
  code: nonEmpty('Code is required'),
  name: nonEmpty('Name is required'),
  description: z.string().trim(),
  required_skill_ids: z.array(z.number().int().positive()),
  is_active: z.boolean(),
});

export type PersonnelClassFormValues = z.infer<typeof personnelClassSchema>;

interface Props {
  initial?: Partial<PersonnelClass>;
  mode: 'create' | 'edit';
  submitting?: boolean;
  skills: { id: number; name: string }[];
  onSubmit: (input: CreatePersonnelClassPayload) => void;
}

export function PersonnelClassForm({
  initial,
  mode,
  submitting,
  skills,
  onSubmit,
}: Props) {
  const { t } = useTranslation();
  const { control, handleSubmit, formState: { isValid } } = useForm<PersonnelClassFormValues>({
    resolver: zodResolver(personnelClassSchema),
    mode: 'onChange',
    defaultValues: {
      code: initial?.code ?? '',
      name: initial?.name ?? '',
      description: initial?.description ?? '',
      required_skill_ids: initial?.required_skill_ids ?? [],
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
          hint="ISA-95 personnel class code (e.g. OP-A1)"
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
        <SectionLabel>Required skills</SectionLabel>
        <Controller
          control={control}
          name="required_skill_ids"
          render={({ field: { value, onChange } }) => (
            <ChipRow>
              {skills.map((s) => {
                const selected = value.includes(s.id);
                return (
                  <SelectionChip
                    key={s.id}
                    label={s.name}
                    active={selected}
                    onPress={() =>
                      onChange(
                        selected
                          ? value.filter((id) => id !== s.id)
                          : [...value, s.id],
                      )
                    }
                  />
                );
              })}
            </ChipRow>
          )}
        />
        {skills.length === 0 ? (
          <Mono size={11}>{t('No skills defined yet').toUpperCase()}</Mono>
        ) : (
          <Mono size={10.5}>
            {t('Multi-select — workers must hold every selected skill').toUpperCase()}
          </Mono>
        )}
      </Card>

      <ActiveToggleCard control={control} name="is_active" />

      <Button
        title={mode === 'create' ? 'Create class' : 'Save changes'}
        size="lg"
        loading={!!submitting}
        disabled={!isValid}
        onPress={handleSubmit((v) => {
          onSubmit({
            code: v.code,
            name: v.name,
            description: v.description || undefined,
            required_skill_ids: v.required_skill_ids,
            is_active: v.is_active,
          });
        })}
      />
    </View>
  );
}
