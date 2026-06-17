import { zodResolver } from '@hookform/resolvers/zod';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Controller, useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { Alert, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { z } from 'zod';

import { Mono } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useAnomalyReasons } from '@/hooks/queries/useOps';
import { useCreateProductionAnomaly } from '@/hooks/queries/useWoExtras';

const DARK = Colors.dark;

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

/**
 * Report production anomaly — operator-facing inline form. Dark surface so
 * the screen reads at a glance on the shop floor. Reason picker is a 2-col
 * grid of code + name chips per the design.
 */
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
    <View style={{ flex: 1, backgroundColor: DARK.background }}>
      <ScreenHeader
        variant="dark"
        back
        title={t('Report anomaly')}
        subtitle={`WO ${id}`}
      />
      <ScrollView contentContainerStyle={styles.scroll}>
        <View style={styles.group}>
          <Mono size={10.5} color={DARK.textFaint} letterSpacing={0.8}>
            {t('REASON').toUpperCase()}
          </Mono>
          <Controller
            control={control}
            name="anomaly_reason_id"
            render={({ field: { onChange } }) => (
              <View style={styles.reasonGrid}>
                {(reasonsQuery.data ?? []).map((r) => {
                  const active = r.id === selectedReasonId;
                  const sev = (r.category ?? 'minor').toLowerCase();
                  const dotColor =
                    sev === 'scrap' ? '#7c3aed' : sev === 'major' ? DARK.danger : BRAND.amber;
                  return (
                    <Pressable
                      key={r.id}
                      onPress={() => onChange(r.id)}
                      style={[
                        styles.reasonChip,
                        {
                          backgroundColor: active ? '#241a08' : DARK.surface,
                          borderColor: active ? BRAND.amber : DARK.border,
                        },
                      ]}>
                      <View style={styles.reasonHead}>
                        <View style={[styles.sevDot, { backgroundColor: dotColor }]} />
                        <Mono size={9.5} color={DARK.textFaint} letterSpacing={0.4} weight="600">
                          {r.code.toUpperCase()}
                        </Mono>
                      </View>
                      <Text style={styles.reasonName} numberOfLines={2}>
                        {r.name}
                      </Text>
                    </Pressable>
                  );
                })}
              </View>
            )}
          />
        </View>

        <View style={styles.group}>
          <Mono size={10.5} color={DARK.textFaint} letterSpacing={0.8}>
            {t('QUANTITY AFFECTED').toUpperCase()}
          </Mono>
          <Controller
            control={control}
            name="actual_qty"
            render={({ field: { value, onChange } }) => (
              <View style={styles.darkInputWrap}>
                <TextInput
                  value={value}
                  onChangeText={onChange}
                  keyboardType="decimal-pad"
                  placeholder="0"
                  placeholderTextColor={DARK.textFaint}
                  style={[styles.darkInput, { fontFamily: MONO }]}
                />
                <Mono size={11} color={DARK.textFaint} weight="700" letterSpacing={0.6}>
                  {t('PCS').toUpperCase()}
                </Mono>
              </View>
            )}
          />
        </View>

        <View style={styles.group}>
          <Mono size={10.5} color={DARK.textFaint} letterSpacing={0.8}>
            {t('NOTES · OPTIONAL').toUpperCase()}
          </Mono>
          <Controller
            control={control}
            name="comment"
            render={({ field: { value, onChange } }) => (
              <TextInput
                value={value}
                onChangeText={onChange}
                multiline
                numberOfLines={4}
                placeholder={t('What happened?')}
                placeholderTextColor={DARK.textFaint}
                style={styles.darkTextarea}
                textAlignVertical="top"
              />
            )}
          />
        </View>

        <View style={styles.actions}>
          <Pressable
            onPress={() => router.back()}
            style={({ pressed }) => [
              styles.cancelBtn,
              { borderColor: DARK.border, opacity: pressed ? 0.7 : 1 },
            ]}>
            <Mono size={11.5} color={DARK.text} weight="600" letterSpacing={0.5}>
              {t('Cancel').toUpperCase()}
            </Mono>
          </Pressable>
          <Pressable
            onPress={handleSubmit(onSubmit)}
            disabled={!isValid || createMutation.isPending}
            style={({ pressed }) => [
              styles.submitBtn,
              {
                backgroundColor: BRAND.amber,
                opacity: !isValid || createMutation.isPending ? 0.5 : pressed ? 0.9 : 1,
              },
            ]}>
            <Mono size={12} color="#1a1208" weight="700" letterSpacing={0.6}>
              {createMutation.isPending
                ? t('Submitting…').toUpperCase()
                : t('Submit anomaly').toUpperCase()}
            </Mono>
          </Pressable>
        </View>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  scroll: { padding: 16, gap: 16 },
  group: { gap: 8 },
  reasonGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 6 },
  reasonChip: {
    width: '49%',
    padding: 12,
    borderRadius: 10,
    borderWidth: 1,
    gap: 6,
  },
  reasonHead: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  sevDot: { width: 6, height: 6, borderRadius: 3 },
  reasonName: { color: DARK.text, fontSize: 12.5, fontWeight: '600' },
  darkInputWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: DARK.surface,
    borderColor: DARK.border,
    borderWidth: 1,
    borderRadius: 10,
    paddingHorizontal: 14,
    height: 48,
    gap: 10,
  },
  darkInput: { flex: 1, color: DARK.text, fontSize: 14 },
  darkTextarea: {
    backgroundColor: DARK.surface,
    borderColor: DARK.border,
    borderWidth: 1,
    borderRadius: 10,
    padding: 14,
    minHeight: 80,
    color: DARK.text,
    fontSize: 13,
    lineHeight: 19,
  },
  actions: { flexDirection: 'row', gap: 8, marginTop: 4 },
  cancelBtn: {
    flex: 1,
    height: 48,
    borderRadius: 10,
    borderWidth: 1,
    backgroundColor: 'transparent',
    alignItems: 'center',
    justifyContent: 'center',
  },
  submitBtn: {
    flex: 2,
    height: 48,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
