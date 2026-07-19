/**
 * New EAN — binds a barcode to a work order. Restyled to the shared form
 * pattern (Field inputs, Mono-labelled Dropdown, toggle-style summary box) while
 * keeping the react-hook-form + zod validation and the useCreateEan mutation.
 * Only ean + work_order_id are persisted; the qty fields drive the summary.
 */
import { zodResolver } from '@hookform/resolvers/zod';
import { useRouter } from 'expo-router';
import { Controller, useForm } from 'react-hook-form';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { FontAwesome } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';

import { Dropdown, colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { useWorkOrders } from '@/hooks/queries/useWorkOrders';
import { useCreateEan } from '@/hooks/queries/usePackaging';
import { nonEmpty } from '@/lib/forms/zod';

const schema = z.object({
  ean: nonEmpty().refine((v) => /^\d{13,14}$/.test(v), 'EAN must be 13 or 14 digits'),
  work_order_id: z.number(),
  qty_per_unit: z.string().regex(/^\d+$/, 'Whole number'),
  target_qty: z.string().regex(/^\d+$/, 'Whole number'),
});

type FormValues = z.infer<typeof schema>;

export function NewEanScreen() {
  const { t } = useTranslation();
  const router = useRouter();

  const wosQuery = useWorkOrders({});
  const createMutation = useCreateEan();

  const { control, handleSubmit, watch, formState: { isValid } } = useForm<FormValues>({
    resolver: zodResolver(schema),
    mode: 'onChange',
    defaultValues: {
      ean: '',
      work_order_id: undefined as unknown as number,
      qty_per_unit: '1',
      target_qty: '1',
    },
  });

  const watched = watch();
  const selectedWo = (wosQuery.data ?? []).find((w) => w.id === watched.work_order_id);
  const totalPieces = (Number(watched.qty_per_unit) || 0) * (Number(watched.target_qty) || 0);

  const onSubmit = (values: FormValues) => {
    createMutation.mutate(
      { work_order_id: values.work_order_id, ean: values.ean.trim() },
      {
        onSuccess: () => router.back(),
        onError: (e: Error) => Alert.alert(t('Could not add EAN'), e.message),
      },
    );
  };

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{t('New EAN')}</Text>

      <Controller
        control={control}
        name="ean"
        render={({ field: { value, onChange }, fieldState }) => (
          <Field
            label="EAN"
            value={value}
            onChangeText={onChange}
            error={fieldState.error?.message}
            required
            mono
            labelHint="13 or 14 digits"
            autoCapitalize="none"
            autoCorrect={false}
            keyboardType="number-pad"
            placeholder="5901234567890"
          />
        )}
      />

      <Pressable
        onPress={() => router.push('/(drawer)/pakowanie/scan' as never)}
        style={({ pressed }) => [styles.scanBtn, { opacity: pressed ? 0.7 : 1 }]}>
        <FontAwesome name="qrcode" size={16} color={colors.ink} />
        <Mono size={12} color={colors.ink} weight="600" letterSpacing={0.5}>{t('Scan barcode').toUpperCase()}</Mono>
      </Pressable>

      <Controller
        control={control}
        name="work_order_id"
        render={({ field: { value, onChange } }) => (
          <View style={{ gap: 6 }}>
            <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Work Order').toUpperCase()} *</Mono>
            <Dropdown
              value={value == null ? '' : String(value)}
              onChange={(v) => onChange(Number(v))}
              placeholder={t('Select work order')}
              options={(wosQuery.data ?? []).map((wo) => ({ value: String(wo.id), label: wo.order_no }))}
            />
          </View>
        )}
      />

      <View style={styles.qtyRow}>
        <View style={{ flex: 1 }}>
          <Controller
            control={control}
            name="qty_per_unit"
            render={({ field: { value, onChange }, fieldState }) => (
              <Field label="Qty per unit" value={value} onChangeText={onChange} error={fieldState.error?.message} required mono keyboardType="number-pad" placeholder="6" />
            )}
          />
        </View>
        <View style={{ flex: 1 }}>
          <Controller
            control={control}
            name="target_qty"
            render={({ field: { value, onChange }, fieldState }) => (
              <Field label="Target qty" value={value} onChangeText={onChange} error={fieldState.error?.message} required mono keyboardType="number-pad" placeholder="80" />
            )}
          />
        </View>
      </View>

      <View style={styles.summary}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Binding summary').toUpperCase()}</Mono>
        <Text style={styles.summaryText}>
          {t('Pack {{units}} units of {{each}} each = {{pieces}} pieces', {
            units: watched.target_qty || 0,
            each: watched.qty_per_unit || 0,
            pieces: totalPieces,
          })}
          {selectedWo ? ` · ${selectedWo.order_no}` : ''}
        </Text>
      </View>

      <View style={styles.actions}>
        <Button title={t('Cancel')} variant="ghost" onPress={() => router.back()} />
        <View style={{ flex: 1 }} />
        <Button title={t('Create EAN')} onPress={handleSubmit(onSubmit)} loading={createMutation.isPending} disabled={!isValid} />
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  scanBtn: {
    height: 46,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.card,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  qtyRow: { flexDirection: 'row', gap: 10 },
  summary: { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14, gap: 8 },
  summaryText: { fontFamily: fonts.mono.native.regular, fontSize: 12.5, color: colors.muted, lineHeight: 20 },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
});
