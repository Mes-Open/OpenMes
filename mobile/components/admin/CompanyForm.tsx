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
import { nonEmpty } from '@/lib/forms/zod';
import type { Company, CompanyType } from '@/api/ops';

const TYPES: { id: CompanyType; label: string }[] = [
  { id: 'supplier', label: 'Supplier' },
  { id: 'customer', label: 'Customer' },
  { id: 'both', label: 'Both' },
];

export const companySchema = z.object({
  code: nonEmpty(),
  name: nonEmpty(),
  type: z.enum(['supplier', 'customer', 'both']),
  tax_id: z.string().trim(),
  email: z.string().trim(),
  phone: z.string().trim(),
  address: z.string().trim(),
  is_active: z.boolean(),
});

export type CompanyFormValues = z.infer<typeof companySchema>;

interface Props {
  initial?: Partial<Company>;
  mode: 'create' | 'edit';
  onSubmit: (values: CompanyFormValues) => void;
  submitting?: boolean;
}

export function CompanyForm({ initial, mode, onSubmit, submitting }: Props) {
  const { control, handleSubmit, formState: { isValid } } = useForm<CompanyFormValues>({
    resolver: zodResolver(companySchema),
    mode: 'onChange',
    defaultValues: {
      code: initial?.code ?? '',
      name: initial?.name ?? '',
      type: initial?.type ?? 'supplier',
      tax_id: initial?.tax_id ?? '',
      email: initial?.email ?? '',
      phone: initial?.phone ?? '',
      address: initial?.address ?? '',
      is_active: initial?.is_active ?? true,
    },
  });

  return (
    <View style={{ gap: 14 }}>
      <Card style={{ gap: 12 }}>
        <SectionLabel>Company</SectionLabel>
        <ControlledField control={control} name="code" label="Code" autoCapitalize="characters" autoCorrect={false} />
        <ControlledField control={control} name="name" label="Name" />
        <ControlledField control={control} name="tax_id" label="Tax ID" />
      </Card>

      <Card style={{ gap: 12 }}>
        <SectionLabel>Type</SectionLabel>
        <Controller
          control={control}
          name="type"
          render={({ field: { value, onChange } }) => (
            <ChipRow>
              {TYPES.map((t) => (
                <SelectionChip
                  key={t.id}
                  label={t.label}
                  active={t.id === value}
                  onPress={() => onChange(t.id)}
                />
              ))}
            </ChipRow>
          )}
        />
      </Card>

      <Card style={{ gap: 12 }}>
        <SectionLabel>Contact</SectionLabel>
        <ControlledField control={control} name="email" label="Email" keyboardType="email-address" autoCapitalize="none" />
        <ControlledField control={control} name="phone" label="Phone" keyboardType="phone-pad" />
        <ControlledField
          control={control}
          name="address"
          label="Address"
          multiline
          numberOfLines={3}
          style={{ minHeight: 80, textAlignVertical: 'top' }}
        />
      </Card>

      <ActiveToggleCard control={control} name="is_active" />

      <Button
        title={mode === 'create' ? 'Create company' : 'Save changes'}
        size="lg"
        loading={!!submitting}
        disabled={!isValid}
        onPress={handleSubmit(onSubmit)}
      />
    </View>
  );
}
