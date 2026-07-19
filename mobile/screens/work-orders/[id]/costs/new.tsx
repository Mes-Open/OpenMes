/**
 * Record additional cost on a work order — cost source, amount + currency and a
 * description. Re-skin to the shared form pattern; the react-hook-form schema
 * and create mutation are unchanged.
 */
import { zodResolver } from '@hookform/resolvers/zod';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Controller, useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { z } from 'zod';

import { Dropdown, colors, fonts } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { useCostSources } from '@/hooks/queries/useOps';
import { useCreateAdditionalCost } from '@/hooks/queries/useWoExtras';
import { nonEmpty } from '@/lib/forms/zod';

const schema = z.object({
  description: nonEmpty(),
  amount: z
    .string()
    .trim()
    .min(1, 'Required')
    .refine((v) => !Number.isNaN(Number(v)) && Number(v) >= 0, 'Must be a number ≥ 0'),
  currency: z.string().trim(),
  cost_source_id: z.number().nullable(),
});

type FormValues = z.infer<typeof schema>;

export function NewCostScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const woId = Number(id);
  const router = useRouter();
  const { t } = useTranslation();

  const sourcesQuery = useCostSources();
  const createMutation = useCreateAdditionalCost();

  const { control, handleSubmit, formState: { isValid } } = useForm<FormValues>({
    resolver: zodResolver(schema),
    mode: 'onChange',
    defaultValues: { description: '', amount: '', currency: 'PLN', cost_source_id: null },
  });

  const onSubmit = (values: FormValues) => {
    createMutation.mutate(
      {
        workOrderId: woId,
        payload: {
          description: values.description.trim(),
          amount: Number(values.amount),
          currency: values.currency.trim() || undefined,
          cost_source_id: values.cost_source_id ?? undefined,
        },
      },
      {
        onSuccess: () => router.back(),
        onError: (e: Error) => Alert.alert(t('Could not add'), e.message),
      },
    );
  };

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{t('Record cost')}</Text>

      <View style={{ gap: 6 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Cost source').toUpperCase()}</Mono>
        <Controller
          control={control}
          name="cost_source_id"
          render={({ field: { value, onChange } }) => (
            <Dropdown
              value={value == null ? '' : String(value)}
              onChange={(v) => onChange(v ? Number(v) : null)}
              placeholder={t('None')}
              options={[
                { value: '', label: t('None') },
                ...(sourcesQuery.data ?? []).map((s) => ({ value: String(s.id), label: s.name })),
              ]}
            />
          )}
        />
      </View>

      <View style={{ flexDirection: 'row', gap: 10 }}>
        <View style={{ flex: 1.4 }}>
          <Controller
            control={control}
            name="amount"
            render={({ field: { value, onChange } }) => (
              <Field label="Amount" value={value} onChangeText={onChange} keyboardType="decimal-pad" required mono placeholder="0.00" />
            )}
          />
        </View>
        <View style={{ flex: 1 }}>
          <Controller
            control={control}
            name="currency"
            render={({ field: { value, onChange } }) => (
              <Field label="Currency" value={value} onChangeText={onChange} autoCapitalize="characters" mono />
            )}
          />
        </View>
      </View>

      <Controller
        control={control}
        name="description"
        render={({ field: { value, onChange } }) => (
          <Field
            label="Description"
            value={value}
            onChangeText={onChange}
            placeholder="e.g. Energy overage, emergency materials, etc."
            multiline
            numberOfLines={3}
            style={{ minHeight: 80, textAlignVertical: 'top' }}
            required
          />
        )}
      />

      <View style={styles.actions}>
        <Button title={t('Cancel')} variant="ghost" onPress={() => router.back()} />
        <View style={{ flex: 1 }} />
        <Button
          title={t('Record cost')}
          onPress={handleSubmit(onSubmit)}
          loading={createMutation.isPending}
          disabled={!isValid}
        />
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
});
