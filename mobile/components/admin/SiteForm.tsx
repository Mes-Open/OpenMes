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
import type { Site, SiteInput } from '@/api/sites';

export const siteSchema = z.object({
  name: nonEmpty('Name is required'),
  code: nonEmpty('Code is required'),
  company_id: z.union([z.literal(''), z.coerce.number().int().positive()])
    .transform((v) => (v === '' ? null : v)),
  description: z.string().trim(),
  address: z.string().trim(),
  city: z.string().trim(),
  country: z.string().trim(),
  timezone: z.string().trim(),
  is_active: z.boolean(),
});

export type SiteFormInput = z.input<typeof siteSchema>;
export type SiteFormValues = z.output<typeof siteSchema>;

interface Props {
  initial?: Partial<Site>;
  mode: 'create' | 'edit';
  submitting?: boolean;
  companies: { id: number; name: string }[];
  onSubmit: (input: SiteInput) => void;
}

export function SiteForm({ initial, mode, submitting, companies, onSubmit }: Props) {
  const { t } = useTranslation();
  const { control, handleSubmit, formState: { isValid } } = useForm<
    SiteFormInput,
    unknown,
    SiteFormValues
  >({
    resolver: zodResolver(siteSchema),
    mode: 'onChange',
    defaultValues: {
      name: initial?.name ?? '',
      code: initial?.code ?? '',
      company_id: initial?.company_id != null ? String(initial.company_id) : '',
      description: initial?.description ?? '',
      address: initial?.address ?? '',
      city: initial?.city ?? '',
      country: initial?.country ?? '',
      timezone: initial?.timezone ?? '',
      is_active: initial?.is_active ?? true,
    },
  });

  return (
    <View style={{ gap: 14 }}>
      <Card style={{ gap: 12 }}>
        <SectionLabel>Site</SectionLabel>
        <ControlledField control={control} name="name" label="Name" />
        <ControlledField
          control={control}
          name="code"
          label="Code"
          autoCapitalize="characters"
          autoCorrect={false}
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

      <Card style={{ gap: 12 }}>
        <SectionLabel>Company</SectionLabel>
        <Controller
          control={control}
          name="company_id"
          render={({ field: { value, onChange } }) => (
            <ChipRow>
              <SelectionChip
                label={t('None')}
                active={!value || value === ''}
                onPress={() => onChange('')}
              />
              {companies.map((c) => (
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
      </Card>

      <Card style={{ gap: 12 }}>
        <SectionLabel>Location</SectionLabel>
        <ControlledField
          control={control}
          name="address"
          label="Address"
          multiline
          numberOfLines={2}
          style={{ minHeight: 60, textAlignVertical: 'top' }}
        />
        <ControlledField control={control} name="city" label="City" />
        <ControlledField control={control} name="country" label="Country" />
        <ControlledField
          control={control}
          name="timezone"
          label="Timezone"
          autoCapitalize="none"
          autoCorrect={false}
          placeholder="Europe/Warsaw"
          hint="IANA timezone name (e.g. Europe/Warsaw)"
        />
      </Card>

      <ActiveToggleCard control={control} name="is_active" />

      <Button
        title={mode === 'create' ? 'Create site' : 'Save changes'}
        size="lg"
        loading={!!submitting}
        disabled={!isValid}
        onPress={handleSubmit((v) => {
          onSubmit({
            name: v.name,
            code: v.code,
            company_id: v.company_id,
            description: v.description || null,
            address: v.address || null,
            city: v.city || null,
            country: v.country || null,
            timezone: v.timezone || null,
            is_active: v.is_active,
          });
        })}
      />
      <Mono size={10.5}>{t('Sites group areas, which group lines (ISA-95)').toUpperCase()}</Mono>
    </View>
  );
}
