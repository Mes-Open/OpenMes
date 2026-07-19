/**
 * New work order — mirrors the Laravel admin create flow: order_no (required,
 * unique), line?, product type?, planned_qty (>0), priority?, due_date?,
 * description?. Re-skin to the shared form pattern; hooks + mutation unchanged.
 */
import { useRouter } from 'expo-router';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';

import { Dropdown, colors, fonts } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { useLines } from '@/hooks/queries/useUsers';
import { useProductTypes } from '@/hooks/queries/useProductTypes';
import { useCreateWorkOrder } from '@/hooks/queries/useWorkOrders';

export function NewWorkOrderScreen() {
  const router = useRouter();
  const { t } = useTranslation();

  const linesQ = useLines();
  const productTypesQ = useProductTypes();
  const create = useCreateWorkOrder();

  const [orderNo, setOrderNo] = useState('');
  const [lineId, setLineId] = useState<number | null>(null);
  const [productTypeId, setProductTypeId] = useState<number | null>(null);
  const [plannedQty, setPlannedQty] = useState('');
  const [priority, setPriority] = useState('');
  const [dueDate, setDueDate] = useState('');
  const [description, setDescription] = useState('');

  const valid =
    orderNo.trim().length > 0 &&
    Number.isFinite(Number(plannedQty)) &&
    Number(plannedQty) > 0;

  const submit = () => {
    const qty = Number(plannedQty);
    create.mutate(
      {
        order_no: orderNo.trim(),
        line_id: lineId ?? undefined,
        product_type_id: productTypeId ?? undefined,
        planned_qty: qty,
        priority: priority.trim() ? Number(priority) : undefined,
        due_date: dueDate.trim() || undefined,
        description: description.trim() || undefined,
      },
      {
        onSuccess: () => router.back(),
        onError: (e: Error) => Alert.alert(t('Could not create'), e.message),
      },
    );
  };

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{t('New work order')}</Text>

      <Field
        label="Order number"
        required
        value={orderNo}
        onChangeText={setOrderNo}
        placeholder="WO-XXX-NNN"
        autoCapitalize="characters"
        autoCorrect={false}
        mono
      />

      <Field
        label="Planned quantity"
        required
        value={plannedQty}
        onChangeText={setPlannedQty}
        placeholder="0"
        keyboardType="numeric"
        mono
      />

      <View style={{ gap: 6 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Line').toUpperCase()}</Mono>
        <Dropdown
          value={lineId == null ? '' : String(lineId)}
          onChange={(v) => setLineId(v ? Number(v) : null)}
          placeholder={t('None')}
          options={[{ value: '', label: t('None') }, ...(linesQ.data ?? []).map((l) => ({ value: String(l.id), label: l.name }))]}
        />
      </View>

      <View style={{ gap: 6 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Product Type').toUpperCase()}</Mono>
        <Dropdown
          value={productTypeId == null ? '' : String(productTypeId)}
          onChange={(v) => setProductTypeId(v ? Number(v) : null)}
          placeholder={t('None')}
          options={[{ value: '', label: t('None') }, ...(productTypesQ.data ?? []).map((p) => ({ value: String(p.id), label: p.name }))]}
        />
      </View>

      <View style={{ flexDirection: 'row', gap: 10 }}>
        <View style={{ flex: 1 }}>
          <Field
            label="Priority"
            value={priority}
            onChangeText={setPriority}
            placeholder="0–100"
            keyboardType="numeric"
            mono
            hint="Higher = sooner"
          />
        </View>
        <View style={{ flex: 1.4 }}>
          <Field
            label="Due date"
            value={dueDate}
            onChangeText={setDueDate}
            placeholder="YYYY-MM-DD"
            autoCorrect={false}
            mono
          />
        </View>
      </View>

      <Field
        label="Description"
        value={description}
        onChangeText={setDescription}
        placeholder="Optional notes"
        multiline
        numberOfLines={3}
        style={{ minHeight: 80, textAlignVertical: 'top' }}
      />

      <View style={styles.actions}>
        <Button title={t('Cancel')} variant="ghost" onPress={() => router.back()} />
        <View style={{ flex: 1 }} />
        <Button title={t('Create work order')} onPress={submit} loading={create.isPending} disabled={!valid} />
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
