/**
 * Pallet create/edit form — 1:1 with the web admin form: work order, quantity,
 * status, location and ERP reference. Quality status is derived (not editable)
 * and label printing stays on the web admin.
 */
import { useMemo, useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { LoadingState } from '@/components/ui/StateViews';
import {
  useCreatePallet,
  usePalletMeta,
  usePallets,
  useUpdatePallet,
} from '@/hooks/queries/usePackaging';
import type { PalletStatus } from '@/api/packaging';

export function PalletFormScreen({ id }: { id?: number }) {
  const { t } = useTranslation();
  const router = useRouter();
  const isEdit = typeof id === 'number';

  const list = usePallets();
  const meta = usePalletMeta();
  const existing = isEdit ? list.data?.find((p) => p.id === id) : undefined;
  const create = useCreatePallet();
  const update = useUpdatePallet();

  const [workOrderId, setWorkOrderId] = useState(existing?.work_order_id ? String(existing.work_order_id) : '');
  const [qty, setQty] = useState(existing?.qty != null ? String(existing.qty) : '');
  const [status, setStatus] = useState<PalletStatus>(existing?.status ?? 'open');
  const [location, setLocation] = useState(existing?.location ?? '');
  const [erpRef, setErpRef] = useState(existing?.erp_reference ?? '');
  const [seeded, setSeeded] = useState(!isEdit);

  if (isEdit && !seeded && existing) {
    setWorkOrderId(String(existing.work_order_id ?? ''));
    setQty(existing.qty != null ? String(existing.qty) : '');
    setStatus(existing.status);
    setLocation(existing.location ?? '');
    setErpRef(existing.erp_reference ?? '');
    setSeeded(true);
  }

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!workOrderId) e.workOrder = 'Required';
    return e;
  }, [workOrderId]);

  const pending = create.isPending || update.isPending;

  const onSave = () => {
    if (Object.keys(errors).length > 0) return;
    const input = {
      work_order_id: Number(workOrderId),
      qty: qty.trim() ? Number(qty) : null,
      status,
      location: location.trim() || null,
      erp_reference: erpRef.trim() || null,
    };
    const onError = (err: Error) => Alert.alert(t('Could not save'), err.message);
    if (isEdit) update.mutate({ id: id as number, input }, { onSuccess: () => router.back(), onError });
    else create.mutate(input, { onSuccess: () => router.back(), onError });
  };

  if ((isEdit && list.isLoading && !existing) || meta.isLoading) return <LoadingState />;

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{isEdit ? `${t('Edit pallet')} ${existing?.pallet_no ?? ''}` : t('New pallet')}</Text>

      <View style={{ gap: 6 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Work Order').toUpperCase()} *</Mono>
        <Dropdown
          value={workOrderId}
          onChange={(v) => setWorkOrderId(v as string)}
          placeholder={t('Select work order')}
          options={(meta.data?.work_orders ?? []).map((w) => ({ value: String(w.id), label: w.order_no }))}
        />
        {errors.workOrder ? <Text style={styles.error}>{t(errors.workOrder)}</Text> : null}
      </View>

      <Field label="Quantity" value={qty} onChangeText={setQty} keyboardType="number-pad" mono placeholder="0" />

      <View style={{ gap: 6 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Status').toUpperCase()} *</Mono>
        <Dropdown
          value={status}
          onChange={(v) => setStatus(v as PalletStatus)}
          options={(meta.data?.statuses ?? []).map((s) => ({ value: s.value, label: s.label }))}
        />
      </View>

      <Field label="Location" value={location} onChangeText={setLocation} placeholder="A-12" />
      <Field label="ERP reference" value={erpRef} onChangeText={setErpRef} autoCapitalize="none" mono placeholder="—" />

      <View style={styles.actions}>
        <Button title={t('Cancel')} variant="ghost" onPress={() => router.back()} />
        <View style={{ flex: 1 }} />
        <Button title={t('Save')} onPress={onSave} loading={pending} disabled={pending} />
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  error: { fontSize: 12, color: colors.blocked, fontFamily: fonts.sans.native.regular },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
});
