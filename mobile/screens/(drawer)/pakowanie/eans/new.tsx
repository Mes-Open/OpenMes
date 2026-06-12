import { FontAwesome } from '@expo/vector-icons';
import { zodResolver } from '@hookform/resolvers/zod';
import { useRouter } from 'expo-router';
import { Controller, useForm } from 'react-hook-form';
import { Alert, Pressable, StyleSheet, Text, View } from 'react-native';
import { z } from 'zod';

import { Card } from '@/components/ui/Card';
import { ChipRow, SelectionChip } from '@/components/ui/SelectionChip';
import { ControlledField } from '@/components/ui/ControlledField';
import { DetailScreen } from '@/components/ui/Detail';
import { FormSubmitBar } from '@/components/ui/FormSubmitBar';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useWorkOrders } from '@/hooks/queries/useWorkOrders';
import { useCreateEan } from '@/hooks/queries/usePackaging';
import { nonEmpty } from '@/lib/forms/zod';

const schema = z.object({
  ean: nonEmpty().refine((v) => /^\d{13,14}$/.test(v), 'EAN must be 13 or 14 digits'),
  work_order_id: z.number(),
  // We hold qty fields as text in the form so the keypad is forgiving;
  // they get parsed when computing the binding summary.
  qty_per_unit: z.string().regex(/^\d+$/, 'Whole number'),
  target_qty: z.string().regex(/^\d+$/, 'Whole number'),
});

type FormValues = z.infer<typeof schema>;

export function NewEanScreen() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

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
    // qty_per_unit / target_qty are not part of the EAN endpoint payload yet —
    // backend infers from the work order. We collect them client-side for the
    // binding summary card, but only ean + work_order_id are sent.
    // TODO(api/packaging-eans): persist qty_per_unit + target_qty per binding.
    createMutation.mutate(
      { work_order_id: values.work_order_id, ean: values.ean.trim() },
      {
        onSuccess: () => router.back(),
        onError: (e: Error) => Alert.alert('Could not add EAN', e.message),
      },
    );
  };

  return (
    <DetailScreen>
      <Card style={{ gap: 12 }}>
        <SectionLabel>EAN</SectionLabel>
        <ControlledField
          control={control}
          name="ean"
          label="EAN"
          required
          mono
          labelHint="13 OR 14 DIGITS"
          autoCapitalize="none"
          autoCorrect={false}
          keyboardType="number-pad"
          placeholder="5901234567890"
          hint="Or tap scan to capture from barcode"
        />
        <Pressable
          onPress={() => router.push('/(drawer)/pakowanie/scan' as never)}
          style={({ pressed }) => [
            styles.scanBtn,
            { borderColor: palette.border, backgroundColor: palette.surface, opacity: pressed ? 0.85 : 1 },
          ]}>
          <FontAwesome name="qrcode" size={16} color={palette.text} />
          <Mono size={12} color={palette.text} weight="700" letterSpacing={0.6}>
            SCAN BARCODE
          </Mono>
        </Pressable>
      </Card>

      <Card style={{ gap: 12 }}>
        <SectionLabel>Work order</SectionLabel>
        <Controller
          control={control}
          name="work_order_id"
          render={({ field: { value, onChange } }) => (
            <ChipRow>
              {(wosQuery.data ?? []).map((wo) => (
                <SelectionChip
                  key={wo.id}
                  label={wo.order_no}
                  active={wo.id === value}
                  onPress={() => onChange(wo.id)}
                />
              ))}
            </ChipRow>
          )}
        />
      </Card>

      <Card style={{ gap: 12 }}>
        <SectionLabel>Quantities</SectionLabel>
        <View style={styles.qtyRow}>
          <View style={{ flex: 1 }}>
            <ControlledField
              control={control}
              name="qty_per_unit"
              label="QTY_PER_UNIT"
              required
              mono
              keyboardType="number-pad"
              placeholder="6"
            />
          </View>
          <View style={{ flex: 1 }}>
            <ControlledField
              control={control}
              name="target_qty"
              label="TARGET_QTY"
              required
              mono
              keyboardType="number-pad"
              placeholder="80"
              suffix={
                <Text style={{ fontFamily: MONO, fontSize: 11, color: palette.textFaint }}>UNITS</Text>
              }
            />
          </View>
        </View>
      </Card>

      <View style={[styles.summary, { backgroundColor: palette.surface, borderColor: palette.border }]}>
        <Mono size={11} color={palette.textFaint} letterSpacing={0.8}>BINDING SUMMARY</Mono>
        <Text style={{ marginTop: 8, fontFamily: MONO, fontSize: 13, color: palette.textFaint, lineHeight: 21 }}>
          Pack{' '}
          <Text style={{ color: palette.text, fontWeight: '700' }}>{watched.target_qty || 0} units</Text>
          {' of '}
          <Text style={{ color: palette.text, fontWeight: '700' }}>{watched.qty_per_unit || 0} each</Text>
          {' = '}
          <Text style={{ color: BRAND.amber, fontWeight: '700' }}>{totalPieces} pieces</Text>
          {selectedWo ? ` of ${selectedWo.order_no}` : ''}
        </Text>
      </View>

      <FormSubmitBar
        primary="Create EAN"
        onPrimary={handleSubmit(onSubmit)}
        loading={createMutation.isPending}
        disabled={!isValid}
      />
    </DetailScreen>
  );
}

const styles = StyleSheet.create({
  scanBtn: {
    height: 48,
    borderRadius: 12,
    borderWidth: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  qtyRow: { flexDirection: 'row', gap: 10 },
  summary: { padding: 14, borderRadius: 12, borderWidth: 1 },
});
