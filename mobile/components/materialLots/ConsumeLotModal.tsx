import { useEffect, useState } from 'react';
import { Alert, Modal, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useConsumeMaterialLot } from '@/hooks/queries/useMaterialLots';
import type { MaterialLot } from '@/api/materialLots';

interface Props {
  visible: boolean;
  lot: MaterialLot | null;
  onClose: () => void;
  /** Optional batch-step preselect when consuming from a run-screen context. */
  initialBatchStepId?: number;
}

/**
 * Consume a quantity from this material lot against a specific batch step.
 * Manual `batch_step_id` entry for the admin debug/correction path; from the
 * operator run flow callers pass `initialBatchStepId` to lock it.
 */
export function ConsumeLotModal({ visible, lot, onClose, initialBatchStepId }: Props) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  const m = useConsumeMaterialLot();

  const [batchStepId, setBatchStepId] = useState<string>(
    initialBatchStepId != null ? String(initialBatchStepId) : '',
  );
  const [quantity, setQuantity] = useState('');
  const [sublotId, setSublotId] = useState('');

  useEffect(() => {
    if (visible) {
      setBatchStepId(initialBatchStepId != null ? String(initialBatchStepId) : '');
      setQuantity('');
      setSublotId('');
    }
  }, [visible, initialBatchStepId]);

  if (!visible || !lot) return null;

  const qtyNum = Number(quantity);
  const bsNum = Number(batchStepId);
  const slNum = sublotId ? Number(sublotId) : undefined;
  const valid =
    Number.isFinite(bsNum) && bsNum > 0 &&
    Number.isFinite(qtyNum) && qtyNum > 0;

  const available = Number(lot.quantity_available);
  const overdraw = Number.isFinite(qtyNum) && qtyNum > available;

  const submit = () => {
    if (!valid) return;
    m.mutate(
      {
        id: lot.id,
        payload: {
          batch_step_id: bsNum,
          quantity: qtyNum,
          ...(slNum && Number.isFinite(slNum) ? { sublot_id: slNum } : {}),
        },
      },
      {
        onSuccess: () => onClose(),
        onError: (e: Error) => Alert.alert(t('Could not consume'), e.message),
      },
    );
  };

  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={onClose}>
      <View style={styles.backdrop}>
        <View style={[styles.sheet, { backgroundColor: palette.background }]}>
          <View style={[styles.header, { borderBottomColor: palette.border }]}>
            <View style={{ flex: 1 }}>
              <Mono size={10.5} color={palette.textFaint} letterSpacing={0.6}>
                {t('CONSUME FROM LOT')}
              </Mono>
              <Text style={[styles.title, { color: palette.text }]} numberOfLines={1}>
                {lot.lot_number}
              </Text>
              <Mono size={10.5} color={palette.textMuted} style={{ marginTop: 2 }}>
                {available} {lot.unit_of_measure} {t('AVAILABLE').toUpperCase()}
              </Mono>
            </View>
            <Pressable onPress={onClose} hitSlop={10} style={styles.closeBtn}>
              <Mono size={12} color={palette.textMuted} weight="700">
                {t('CLOSE')}
              </Mono>
            </Pressable>
          </View>

          <ScrollView contentContainerStyle={styles.body}>
            <SectionLabel>Target</SectionLabel>
            <Field
              label="Batch step ID"
              value={batchStepId}
              onChangeText={setBatchStepId}
              keyboardType="number-pad"
              editable={initialBatchStepId == null}
              hint={
                initialBatchStepId != null
                  ? 'Locked to the current run step'
                  : 'Find the batch step on the work-order run screen'
              }
            />

            <View style={{ height: 12 }} />
            <SectionLabel>Quantity</SectionLabel>
            <Field
              label={`Amount (${lot.unit_of_measure})`}
              value={quantity}
              onChangeText={setQuantity}
              keyboardType="decimal-pad"
              hint={
                overdraw
                  ? `Exceeds available (${available} ${lot.unit_of_measure})`
                  : `Available: ${available} ${lot.unit_of_measure}`
              }
            />
            <Field
              label="Sublot ID (optional)"
              value={sublotId}
              onChangeText={setSublotId}
              keyboardType="number-pad"
              hint="Leave blank to consume from the parent lot directly"
            />

            <View style={{ height: 18 }} />
            <Button
              title="Consume"
              variant="danger"
              onPress={submit}
              loading={m.isPending}
              disabled={!valid || overdraw}
              size="lg"
            />
            <Mono size={10.5} color={palette.textFaint} style={{ marginTop: 12, textAlign: 'center' }}>
              {t('Quantities below available are deducted from quantity_available').toUpperCase()}
            </Mono>
          </ScrollView>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdrop: { flex: 1, backgroundColor: 'rgba(0,0,0,0.5)', justifyContent: 'flex-end' },
  sheet: {
    maxHeight: '85%',
    borderTopLeftRadius: 18,
    borderTopRightRadius: 18,
    overflow: 'hidden',
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 18,
    paddingVertical: 14,
    borderBottomWidth: StyleSheet.hairlineWidth,
    gap: 10,
  },
  title: { fontSize: 18, fontWeight: '700', letterSpacing: -0.2, marginTop: 2 },
  closeBtn: { padding: 6 },
  body: { padding: 18, paddingBottom: 28 },
});
