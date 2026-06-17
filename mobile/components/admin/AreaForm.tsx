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
import type { Area, AreaInput } from '@/api/areas';

export const areaSchema = z.object({
  name: nonEmpty('Name is required'),
  code: nonEmpty('Code is required'),
  site_id: z.coerce.number({ message: 'Pick a site' }).int().positive('Pick a site'),
  description: z.string().trim(),
  is_active: z.boolean(),
});

export type AreaFormInput = z.input<typeof areaSchema>;
export type AreaFormValues = z.output<typeof areaSchema>;

interface Props {
  initial?: Partial<Area>;
  mode: 'create' | 'edit';
  submitting?: boolean;
  sites: { id: number; name: string }[];
  lockedSiteId?: number;
  onSubmit: (input: AreaInput) => void;
}

export function AreaForm({ initial, mode, submitting, sites, lockedSiteId, onSubmit }: Props) {
  const { t } = useTranslation();
  const defaultSiteId =
    lockedSiteId ?? initial?.site_id ?? sites[0]?.id ?? 0;

  const { control, handleSubmit, formState: { isValid } } = useForm<
    AreaFormInput,
    unknown,
    AreaFormValues
  >({
    resolver: zodResolver(areaSchema),
    mode: 'onChange',
    defaultValues: {
      name: initial?.name ?? '',
      code: initial?.code ?? '',
      site_id: defaultSiteId,
      description: initial?.description ?? '',
      is_active: initial?.is_active ?? true,
    },
  });

  return (
    <View style={{ gap: 14 }}>
      <Card style={{ gap: 12 }}>
        <SectionLabel>Area</SectionLabel>
        <ControlledField control={control} name="name" label="Name" />
        <ControlledField
          control={control}
          name="code"
          label="Code"
          autoCapitalize="characters"
          autoCorrect={false}
          hint="Unique within the parent site"
        />
        <ControlledField
          control={control}
          name="description"
          label="Description"
          multiline
          numberOfLines={2}
          style={{ minHeight: 60, textAlignVertical: 'top' }}
        />
      </Card>

      {!lockedSiteId ? (
        <Card style={{ gap: 12 }}>
          <SectionLabel>Site</SectionLabel>
          <Controller
            control={control}
            name="site_id"
            render={({ field: { value, onChange } }) => (
              <ChipRow>
                {sites.map((s) => (
                  <SelectionChip
                    key={s.id}
                    label={s.name}
                    active={String(s.id) === String(value)}
                    onPress={() => onChange(s.id)}
                  />
                ))}
              </ChipRow>
            )}
          />
          {sites.length === 0 ? (
            <Mono size={11}>{t('No sites yet — create one first').toUpperCase()}</Mono>
          ) : null}
        </Card>
      ) : null}

      <ActiveToggleCard control={control} name="is_active" />

      <Button
        title={mode === 'create' ? 'Create area' : 'Save changes'}
        size="lg"
        loading={!!submitting}
        disabled={!isValid || sites.length === 0}
        onPress={handleSubmit((v) => {
          onSubmit({
            name: v.name,
            code: v.code,
            site_id: v.site_id,
            description: v.description || null,
            is_active: v.is_active,
          });
        })}
      />
    </View>
  );
}
