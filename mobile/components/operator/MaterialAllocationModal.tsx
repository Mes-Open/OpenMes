import { FontAwesome } from '@expo/vector-icons';
import { Modal, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Mono } from '@/components/ui/Mono';
import { Button } from '@/components/ui/Button';
import { LoadingState } from '@/components/ui/StateViews';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useBatchAllocationPreview } from '@/hooks/queries/useBatch';
import type { MaterialAllocationLine } from '@/api/batches';

interface Props {
  /** Batch we're about to allocate materials for; null = modal closed. */
  batchId: number | null;
  onConfirm: () => void;
  onCancel: () => void;
  confirmLoading?: boolean;
}

/**
 * Confirmation modal shown before the operator starts the first step on a
 * PENDING batch. Surfaces the BOM materials, available stock, and any
 * insufficient warnings. Allocation itself happens server-side on step start.
 */
export function MaterialAllocationModal({ batchId, onConfirm, onCancel, confirmLoading }: Props) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  const open = batchId != null;
  const previewQ = useBatchAllocationPreview(batchId ?? undefined, open);
  const lines = previewQ.data?.data ?? [];
  const allSufficient = previewQ.data?.all_sufficient ?? true;
  const noBom = !previewQ.isLoading && lines.length === 0;

  return (
    <Modal
      visible={open}
      animationType="fade"
      transparent
      onRequestClose={onCancel}>
      <View style={styles.backdrop}>
        <View style={[styles.sheet, { backgroundColor: palette.surface, borderColor: palette.border }]}>
          <View style={styles.header}>
            <View style={{ flex: 1 }}>
              <Mono size={11} color={palette.textFaint} letterSpacing={0.7}>
                {t('Materials').toUpperCase()}
              </Mono>
              <Text style={[styles.title, { color: palette.text }]}>
                {t('Confirm material allocation')}
              </Text>
            </View>
            <Pressable onPress={onCancel} hitSlop={8}>
              <FontAwesome name="close" size={18} color={palette.textMuted} />
            </Pressable>
          </View>

          {previewQ.isLoading ? (
            <LoadingState />
          ) : noBom ? (
            <View style={styles.noBomBlock}>
              <Mono size={11} color={palette.textFaint} letterSpacing={0.6}>
                {t('No BOM').toUpperCase()}
              </Mono>
              <Text style={[styles.noBomBody, { color: palette.textMuted }]}>
                {t('This work order has no bill of materials. Nothing will be allocated.')}
              </Text>
            </View>
          ) : (
            <ScrollView style={{ maxHeight: 320 }} contentContainerStyle={{ gap: 8 }}>
              {lines.map((line) => (
                <MaterialRow key={line.material_code} line={line} />
              ))}
            </ScrollView>
          )}

          {!allSufficient && !noBom ? (
            <View style={[styles.warning, { backgroundColor: palette.dangerSoft }]}>
              <FontAwesome name="exclamation-triangle" size={14} color={palette.danger} />
              <Text style={[styles.warningText, { color: palette.danger }]}>
                {t('Some materials are insufficient. Starting will proceed but may run short.')}
              </Text>
            </View>
          ) : null}

          <View style={styles.actions}>
            <Button title={t('Cancel')} variant="secondary" onPress={onCancel} style={{ flex: 1 }} />
            <Button
              title={t('Confirm and start')}
              onPress={onConfirm}
              loading={confirmLoading}
              style={{ flex: 1 }}
            />
          </View>
        </View>
      </View>
    </Modal>
  );
}

function MaterialRow({ line }: { line: MaterialAllocationLine }) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  const insufficient = !line.sufficient;
  return (
    <View
      style={[
        styles.row,
        {
          backgroundColor: palette.surfaceAlt,
          borderColor: insufficient ? palette.danger : palette.border,
        },
      ]}>
      <View style={{ flex: 1, minWidth: 0 }}>
        <Text style={[styles.matName, { color: palette.text }]} numberOfLines={1}>
          {line.material_name}
        </Text>
        <Mono size={10} color={palette.textFaint} letterSpacing={0.4} style={{ marginTop: 2 }}>
          {line.material_code}
        </Mono>
      </View>
      <View style={{ alignItems: 'flex-end' }}>
        <Mono size={12} color={insufficient ? palette.danger : palette.text} weight="700">
          {line.required_qty} {line.unit_of_measure}
        </Mono>
        <Mono size={10} color={insufficient ? palette.danger : palette.textFaint} style={{ marginTop: 2 }}>
          {t('Avail').toUpperCase()} {line.available_qty}
        </Mono>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  backdrop: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.45)',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 20,
  },
  sheet: {
    width: '100%',
    maxWidth: 480,
    borderRadius: 16,
    borderWidth: 1,
    padding: 18,
    gap: 14,
  },
  header: { flexDirection: 'row', alignItems: 'flex-start', gap: 12 },
  title: { fontSize: 17, fontWeight: '700', marginTop: 4, letterSpacing: -0.2 },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 12,
    borderRadius: 10,
    borderWidth: 1,
  },
  matName: { fontSize: 14, fontWeight: '600', letterSpacing: -0.2 },
  noBomBlock: { gap: 4, paddingVertical: 12 },
  noBomBody: { fontSize: 13, lineHeight: 19 },
  warning: {
    flexDirection: 'row',
    gap: 10,
    padding: 12,
    borderRadius: 10,
    alignItems: 'flex-start',
  },
  warningText: { flex: 1, fontSize: 12, lineHeight: 17 },
  actions: { flexDirection: 'row', gap: 10, marginTop: 4 },
});
