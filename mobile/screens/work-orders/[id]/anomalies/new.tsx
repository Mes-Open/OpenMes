/**
 * Report production anomaly — pick a reason, the affected quantity and optional
 * notes. Re-skin of the operator form to the shared form pattern; the
 * react-hook-form schema and create mutation are unchanged.
 */
import { zodResolver } from '@hookform/resolvers/zod';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Controller, useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { z } from 'zod';

import { colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { useAnomalyReasons } from '@/hooks/queries/useOps';
import { useCreateProductionAnomaly } from '@/hooks/queries/useWoExtras';

const numericString = z
  .string()
  .trim()
  .min(1, 'Required')
  .refine((v) => !Number.isNaN(Number(v)), 'Must be a number');

const schema = z.object({
  anomaly_reason_id: z.number(),
  actual_qty: numericString,
  comment: z.string().trim(),
});

type FormValues = z.infer<typeof schema>;

export function NewAnomalyScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const woId = Number(id);
  const router = useRouter();
  const { t } = useTranslation();

  const reasonsQuery = useAnomalyReasons();
  const createMutation = useCreateProductionAnomaly();

  const { control, handleSubmit, formState: { isValid }, watch } = useForm<FormValues>({
    resolver: zodResolver(schema),
    mode: 'onChange',
    defaultValues: {
      anomaly_reason_id: undefined as unknown as number,
      actual_qty: '',
      comment: '',
    },
  });

  const selectedReasonId = watch('anomaly_reason_id');

  const onSubmit = (values: FormValues) => {
    // Backend wants planned vs actual; for operator inline reports we record
    // the affected qty as the "actual" delta and leave planned at 0 so the
    // controller computes a meaningful deviation.
    createMutation.mutate(
      {
        workOrderId: woId,
        payload: {
          anomaly_reason_id: values.anomaly_reason_id,
          planned_qty: 0,
          actual_qty: Number(values.actual_qty),
          comment: values.comment.trim() || undefined,
        },
      },
      {
        onSuccess: () => router.back(),
        onError: (e: Error) => Alert.alert(t('Could not record'), e.message),
      },
    );
  };

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{t('Report anomaly')}</Text>

      <View style={{ gap: 8 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Reason').toUpperCase()} *</Mono>
        <Controller
          control={control}
          name="anomaly_reason_id"
          render={({ field: { onChange } }) => (
            <View style={styles.reasonGrid}>
              {(reasonsQuery.data ?? []).map((r) => {
                const active = r.id === selectedReasonId;
                const sev = (r.category ?? 'minor').toLowerCase();
                const dotColor = sev === 'scrap' ? colors.blocked : sev === 'major' ? colors.downtime : colors.accent;
                return (
                  <Pressable
                    key={r.id}
                    onPress={() => onChange(r.id)}
                    style={[styles.reasonChip, active ? styles.reasonChipActive : null]}>
                    <View style={styles.reasonHead}>
                      <View style={[styles.sevDot, { backgroundColor: dotColor }]} />
                      <Mono size={9.5} color={colors.faint} letterSpacing={0.4} weight="600">{r.code.toUpperCase()}</Mono>
                    </View>
                    <Text style={styles.reasonName} numberOfLines={2}>{r.name}</Text>
                  </Pressable>
                );
              })}
            </View>
          )}
        />
      </View>

      <Controller
        control={control}
        name="actual_qty"
        render={({ field: { value, onChange } }) => (
          <Field
            label="Quantity affected"
            value={value}
            onChangeText={onChange}
            keyboardType="decimal-pad"
            placeholder="0"
            mono
            required
          />
        )}
      />

      <Controller
        control={control}
        name="comment"
        render={({ field: { value, onChange } }) => (
          <Field
            label="Notes (optional)"
            value={value}
            onChangeText={onChange}
            multiline
            numberOfLines={4}
            placeholder="What happened?"
            style={{ minHeight: 80, textAlignVertical: 'top' }}
          />
        )}
      />

      <View style={styles.actions}>
        <Button title={t('Cancel')} variant="ghost" onPress={() => router.back()} />
        <View style={{ flex: 1 }} />
        <Button
          title={t('Submit anomaly')}
          onPress={handleSubmit(onSubmit)}
          loading={createMutation.isPending}
          disabled={!isValid || createMutation.isPending}
        />
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  reasonGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 6 },
  reasonChip: {
    width: '49%',
    padding: 12,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.card,
    gap: 6,
  },
  reasonChipActive: { borderColor: colors.accent, backgroundColor: colors.runningBg },
  reasonHead: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  sevDot: { width: 6, height: 6, borderRadius: 3 },
  reasonName: { color: colors.ink, fontSize: 12.5, fontFamily: fonts.sans.native.medium },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
});
