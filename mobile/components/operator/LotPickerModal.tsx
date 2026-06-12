import { FontAwesome } from '@expo/vector-icons';
import { format, parseISO } from 'date-fns';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Alert,
  KeyboardAvoidingView,
  Modal,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { LoadingState } from '@/components/ui/StateViews';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import {
  useConsumeMaterialLot,
  useMaterialLots,
} from '@/hooks/queries/useMaterialLots';
import type { MaterialLot } from '@/api/materialLots';

interface Props {
  open: boolean;
  onClose: () => void;
  /** Which batch step the consumption will be attached to — required for the
   *  POST /material-lots/{id}/consume call. */
  batchStepId: number | null;
  /** Limit lots to a single material (typical — the operator is consuming a
   *  specific BOM line). Pass `null` for an "any lot" picker. */
  materialId?: number | null;
  /** Default quantity to pre-fill — usually pulled from the BOM line. */
  defaultQuantity?: number;
}

/**
 * Operator lot picker — full-screen modal that lists available lots for a
 * material and records a consumption against the selected step. Lots are
 * sorted by expiry (FEFO) since that's the default backend strategy.
 */
export function LotPickerModal({
  open,
  onClose,
  batchStepId,
  materialId,
  defaultQuantity,
}: Props) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();

  const lotsQ = useMaterialLots(
    open
      ? {
          material_id: materialId ?? undefined,
          available_only: true,
          per_page: 50,
        }
      : {},
  );
  const consumeMutation = useConsumeMaterialLot();

  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [qty, setQty] = useState<string>(
    defaultQuantity != null ? String(defaultQuantity) : '',
  );

  // Sort the lots by expiry — soonest expiry first (FEFO). Backend has the
  // authoritative pick strategy in system settings, but the operator should
  // see the same order they'd get from auto-pick.
  const lots = useMemo(() => {
    const data = lotsQ.data?.data ?? [];
    return [...data].sort((a, b) => {
      const ax = a.expiry_date ? new Date(a.expiry_date).getTime() : Infinity;
      const bx = b.expiry_date ? new Date(b.expiry_date).getTime() : Infinity;
      return ax - bx;
    });
  }, [lotsQ.data]);

  const selected = lots.find((l) => l.id === selectedId);

  const confirm = async () => {
    if (!selected) return;
    if (batchStepId == null) {
      Alert.alert(t('No active step'), t('Start a step before picking a lot.'));
      return;
    }
    const n = Number(qty);
    if (!Number.isFinite(n) || n <= 0) {
      Alert.alert(t('Invalid quantity'), t('Enter a quantity greater than 0.'));
      return;
    }
    try {
      await consumeMutation.mutateAsync({
        id: selected.id,
        payload: { batch_step_id: batchStepId, quantity: n },
      });
      onClose();
      setSelectedId(null);
      setQty('');
    } catch (e) {
      Alert.alert(t('Consume failed'), (e as Error).message);
    }
  };

  return (
    <Modal visible={open} animationType="slide" transparent onRequestClose={onClose}>
      <KeyboardAvoidingView
        style={styles.backdrop}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <View
          style={[
            styles.sheet,
            { backgroundColor: palette.surface, borderColor: palette.border },
          ]}>
          <View style={styles.header}>
            <View style={{ flex: 1 }}>
              <Mono size={11} color={palette.textFaint} letterSpacing={0.7}>
                {t('LOT PICKING').toUpperCase()}
              </Mono>
              <Text style={[styles.title, { color: palette.text }]}>
                {t('Pick a lot')}
              </Text>
            </View>
            <Pressable onPress={onClose} hitSlop={8}>
              <FontAwesome name="close" size={18} color={palette.textMuted} />
            </Pressable>
          </View>

          {lotsQ.isLoading ? (
            <LoadingState />
          ) : lots.length === 0 ? (
            <View style={{ padding: 24, alignItems: 'center' }}>
              <Mono size={11} color={palette.textFaint}>
                {t('No available lots').toUpperCase()}
              </Mono>
            </View>
          ) : (
            <ScrollView style={{ maxHeight: 320 }} contentContainerStyle={{ gap: 8 }}>
              {lots.map((lot) => (
                <LotRow
                  key={lot.id}
                  lot={lot}
                  selected={lot.id === selectedId}
                  onPress={() => setSelectedId(lot.id)}
                  palette={palette}
                />
              ))}
            </ScrollView>
          )}

          {selected ? (
            <View
              style={[
                styles.qtyBlock,
                { backgroundColor: palette.surfaceAlt, borderColor: palette.border },
              ]}>
              <View style={{ flex: 1 }}>
                <Mono size={10} color={palette.textFaint} letterSpacing={0.5}>
                  {t('QUANTITY').toUpperCase()}
                </Mono>
                <TextInput
                  value={qty}
                  onChangeText={setQty}
                  keyboardType="numeric"
                  placeholder="0.00"
                  placeholderTextColor={palette.textFaint}
                  style={[
                    styles.qtyInput,
                    { color: palette.text, fontFamily: MONO },
                  ]}
                />
              </View>
              <Mono
                size={11}
                color={palette.textFaint}
                letterSpacing={0.4}
                style={{ alignSelf: 'flex-end', marginBottom: 6 }}>
                / {selected.quantity_available} {selected.unit_of_measure}
              </Mono>
            </View>
          ) : null}

          <View style={styles.actions}>
            <Button
              title={t('Cancel')}
              variant="secondary"
              onPress={onClose}
              style={{ flex: 1 }}
            />
            <Button
              title={t('Consume')}
              variant="primary"
              onPress={confirm}
              loading={consumeMutation.isPending}
              disabled={!selected || !qty}
              style={{ flex: 2 }}
            />
          </View>
        </View>
      </KeyboardAvoidingView>
    </Modal>
  );
}

function LotRow({
  lot,
  selected,
  onPress,
  palette,
}: {
  lot: MaterialLot;
  selected: boolean;
  onPress: () => void;
  palette: typeof Colors.light;
}) {
  const { t } = useTranslation();
  const expiry = (() => {
    if (!lot.expiry_date) return null;
    try {
      const d = new Date(lot.expiry_date).getTime();
      const days = Math.floor((d - Date.now()) / (24 * 60 * 60 * 1000));
      return { text: format(parseISO(lot.expiry_date), 'yyyy-MM-dd'), days };
    } catch {
      return null;
    }
  })();
  const expiringSoon = expiry != null && expiry.days < 30;

  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [
        styles.lotRow,
        {
          backgroundColor: selected
            ? `${BRAND.amber}11`
            : palette.surfaceAlt,
          borderColor: selected ? BRAND.amber : palette.border,
          opacity: pressed ? 0.85 : 1,
        },
      ]}>
      <View style={{ flex: 1, minWidth: 0 }}>
        <Mono size={12} color={palette.text} weight="700">
          {lot.lot_number}
        </Mono>
        <Mono size={10} color={palette.textFaint} letterSpacing={0.4} style={{ marginTop: 3 }}>
          {(lot.material?.name ?? lot.material?.code ?? '—').toUpperCase()}
          {lot.supplier_lot_no ? ` · ${lot.supplier_lot_no}` : ''}
        </Mono>
      </View>
      <View style={{ alignItems: 'flex-end' }}>
        <Mono size={12} color={palette.text} weight="700">
          {lot.quantity_available} {lot.unit_of_measure}
        </Mono>
        {expiry ? (
          <Mono
            size={9.5}
            color={expiringSoon ? palette.danger : palette.textFaint}
            weight={expiringSoon ? '700' : '500'}
            letterSpacing={0.4}
            style={{ marginTop: 3 }}>
            {expiry.text}
            {expiringSoon
              ? ` · ${expiry.days < 0 ? t('EXPIRED').toUpperCase() : `${expiry.days}d`}`
              : ''}
          </Mono>
        ) : null}
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  backdrop: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.45)',
    justifyContent: 'flex-end',
  },
  sheet: {
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    borderWidth: 1,
    padding: 18,
    gap: 14,
  },
  header: { flexDirection: 'row', alignItems: 'flex-start', gap: 12 },
  title: { fontSize: 17, fontWeight: '700', marginTop: 4, letterSpacing: -0.2 },
  lotRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 12,
    borderRadius: 10,
    borderWidth: 1,
  },
  qtyBlock: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    gap: 12,
    padding: 12,
    borderRadius: 12,
    borderWidth: 1,
  },
  qtyInput: {
    fontSize: 24,
    fontWeight: '700',
    paddingVertical: 4,
  },
  actions: { flexDirection: 'row', gap: 10 },
});
