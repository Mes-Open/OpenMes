import { zodResolver } from '@hookform/resolvers/zod';
import { Controller, useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { StyleSheet, Text, View } from 'react-native';
import { z } from 'zod';

import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { ChipRow, SelectionChip } from '@/components/ui/SelectionChip';
import { ControlledField } from '@/components/ui/ControlledField';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import { Switch } from '@/components/ui/Switch';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { nonEmpty } from '@/lib/forms/zod';
import { useProductTypes } from '@/hooks/queries/useProductTypes';
import type { LotSequence } from '@/api/lot';

export const lotSequenceSchema = z.object({
  name: nonEmpty(),
  prefix: nonEmpty(),
  suffix: z.string().trim(),
  pad_size: z.string().trim(),
  year_prefix: z.boolean(),
  product_type_id: z.number().int().positive().nullable(),
});

export type LotSequenceFormValues = z.infer<typeof lotSequenceSchema>;

interface Props {
  initial?: Partial<LotSequence>;
  mode: 'create' | 'edit';
  onSubmit: (values: LotSequenceFormValues) => void;
  submitting?: boolean;
}

export function LotSequenceForm({ initial, mode, onSubmit, submitting }: Props) {
  const { t } = useTranslation();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const productTypes = useProductTypes();

  const { control, handleSubmit, watch, setValue, formState: { isValid } } =
    useForm<LotSequenceFormValues>({
      resolver: zodResolver(lotSequenceSchema),
      mode: 'onChange',
      defaultValues: {
        name: initial?.name ?? '',
        prefix: initial?.prefix ?? '',
        suffix: initial?.suffix ?? '',
        pad_size: initial?.pad_size ? String(initial.pad_size) : '4',
        year_prefix: initial?.year_prefix ?? true,
        product_type_id: initial?.product_type_id ?? null,
      },
    });

  const productTypeId = watch('product_type_id');
  const yearPrefix = watch('year_prefix');
  const prefix = watch('prefix');
  const suffix = watch('suffix');
  const padSize = watch('pad_size');

  const previewPattern = [
    yearPrefix ? 'YYYY' : null,
    prefix || null,
    padSize ? `[${padSize}-DIGIT]` : null,
    suffix || null,
  ]
    .filter(Boolean)
    .join('+') || '—';

  return (
    <View style={{ gap: 14 }}>
      <Card style={{ gap: 12 }}>
        <SectionLabel>Sequence</SectionLabel>
        <ControlledField control={control} name="name" label="Name" placeholder="e.g. default, EU-PROD" />
        <ControlledField
          control={control}
          name="prefix"
          label="Prefix"
          autoCapitalize="characters"
          autoCorrect={false}
          placeholder="e.g. LOT"
        />
        <ControlledField
          control={control}
          name="suffix"
          label="Suffix (optional)"
          autoCapitalize="characters"
          autoCorrect={false}
        />
        <ControlledField
          control={control}
          name="pad_size"
          label="Pad size"
          keyboardType="number-pad"
          placeholder="4"
        />

        <View style={[styles.preview, { backgroundColor: palette.surfaceAlt }]}>
          <Mono size={10} color={palette.textFaint} letterSpacing={0.6}>{t('PATTERN')}</Mono>
          <Text style={[styles.previewText, { color: palette.text }]}>{previewPattern}</Text>
        </View>
      </Card>

      <Card>
        <View style={styles.toggleRow}>
          <View style={{ flex: 1 }}>
            <Text style={[styles.toggleTitle, { color: palette.text }]}>{t('Year prefix')}</Text>
            <Mono size={11} color={palette.textFaint} style={{ marginTop: 3 }}>
              {t('PREPEND CURRENT YEAR (E.G. 2026LOT0001)')}
            </Mono>
          </View>
          <Controller
            control={control}
            name="year_prefix"
            render={({ field: { value, onChange } }) => (
              <Switch value={!!value} onValueChange={onChange} />
            )}
          />
        </View>
      </Card>

      <Card style={{ gap: 12 }}>
        <SectionLabel>Product type (optional)</SectionLabel>
        <ChipRow>
          <SelectionChip
            label={t('Default fallback')}
            active={productTypeId == null}
            onPress={() => setValue('product_type_id', null, { shouldValidate: true })}
          />
          {(productTypes.data ?? []).map((pt) => (
            <SelectionChip
              key={pt.id}
              label={pt.name}
              active={pt.id === productTypeId}
              onPress={() => setValue('product_type_id', pt.id, { shouldValidate: true })}
            />
          ))}
        </ChipRow>
        <Mono size={11} color={palette.textFaint}>
          {t('ONE SEQUENCE PER PRODUCT TYPE · DEFAULT IS THE GLOBAL FALLBACK')}
        </Mono>
      </Card>

      <Button
        title={mode === 'create' ? 'Create sequence' : 'Save changes'}
        size="lg"
        loading={!!submitting}
        disabled={!isValid}
        onPress={handleSubmit(onSubmit)}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  preview: { padding: 12, borderRadius: 10, gap: 4 },
  previewText: { fontSize: 14, fontWeight: '600', fontFamily: 'GeistMono_600SemiBold', letterSpacing: 0.4, marginTop: 4 },
  toggleRow: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  toggleTitle: { fontSize: 14, fontWeight: '600', letterSpacing: -0.2 },
});
