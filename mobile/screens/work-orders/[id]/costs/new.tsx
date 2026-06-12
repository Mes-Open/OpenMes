import { zodResolver } from '@hookform/resolvers/zod';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Controller, useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { Alert, View } from 'react-native';
import { z } from 'zod';

import { Card } from '@/components/ui/Card';
import { ChipRow, SelectionChip } from '@/components/ui/SelectionChip';
import { ControlledField } from '@/components/ui/ControlledField';
import { DetailScreen } from '@/components/ui/Detail';
import { FormSubmitBar } from '@/components/ui/FormSubmitBar';
import { SectionLabel } from '@/components/ui/Mono';
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

/**
 * Record additional cost on a work order — supervisor light form. Matches
 * design ScreenWoCostNew (cost source picker on top, amount+currency
 * side-by-side, description body, submit bar).
 */
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
    <DetailScreen>
      <Card style={{ gap: 12 }}>
        <SectionLabel>Cost source</SectionLabel>
        <Controller
          control={control}
          name="cost_source_id"
          render={({ field: { value, onChange } }) => (
            <ChipRow>
              <SelectionChip
                label={t('None')}
                active={value === null}
                onPress={() => onChange(null)}
              />
              {(sourcesQuery.data ?? []).map((s) => (
                <SelectionChip
                  key={s.id}
                  label={s.name}
                  active={s.id === value}
                  onPress={() => onChange(s.id)}
                />
              ))}
            </ChipRow>
          )}
        />
      </Card>

      <Card style={{ gap: 12 }}>
        <SectionLabel>Amount</SectionLabel>
        <View style={{ flexDirection: 'row', gap: 10 }}>
          <View style={{ flex: 1.4 }}>
            <ControlledField
              control={control}
              name="amount"
              label="AMOUNT"
              keyboardType="decimal-pad"
              required
              mono
              placeholder="0.00"
            />
          </View>
          <View style={{ flex: 1 }}>
            <ControlledField
              control={control}
              name="currency"
              label="CURRENCY"
              autoCapitalize="characters"
              mono
            />
          </View>
        </View>
      </Card>

      <Card style={{ gap: 12 }}>
        <SectionLabel>Description</SectionLabel>
        <ControlledField
          control={control}
          name="description"
          label="DESCRIPTION"
          placeholder="e.g. Energy overage, emergency materials, etc."
          multiline
          numberOfLines={3}
          style={{ minHeight: 80, textAlignVertical: 'top', paddingTop: 12 }}
          required
        />
      </Card>

      <FormSubmitBar
        primary="Record cost"
        secondary="Cancel"
        onPrimary={handleSubmit(onSubmit)}
        onSecondary={() => router.back()}
        loading={createMutation.isPending}
        disabled={!isValid}
      />
    </DetailScreen>
  );
}
