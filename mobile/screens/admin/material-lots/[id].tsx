/**
 * Material lot detail — mirrors the web material-lots Show page: an info box of
 * key/value rows, then genealogy (forward = consumed into, backward = sourced
 * from) rendered as tables. Consume-from-lot wiring is preserved. Data via
 * useMaterialLot + forward/backward genealogy endpoints.
 */
import { useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts, radius } from '@openmes/ui';

import { ConsumeLotModal } from '@/components/materialLots/ConsumeLotModal';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import {
  useLotBackwardGenealogy,
  useLotForwardGenealogy,
  useMaterialLot,
} from '@/hooks/queries/useMaterialLots';
import { isSupervisorOrAdmin, useAuthStore } from '@/stores/authStore';
import type { BatchStepLotConsumption } from '@/api/materialLots';

const humanize = (s: string) => s.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
const fmtDate = (s?: string | null) => (s ? String(s).slice(0, 10) : '—');
const fmtDateTime = (s?: string | null) => (s ? String(s).slice(0, 16).replace('T', ' ') : '—');

export function LotGenealogyScreen() {
  const { t } = useTranslation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);

  const lotQ = useMaterialLot(numericId);
  const fwdQ = useLotForwardGenealogy(numericId);
  const bwdQ = useLotBackwardGenealogy(numericId);

  const canConsume = isSupervisorOrAdmin(useAuthStore((s) => s.user));
  const [consumeOpen, setConsumeOpen] = useState(false);

  if (lotQ.isLoading) return <LoadingState />;
  if (lotQ.isError || !lotQ.data) return <ErrorState error={lotQ.error} onRetry={lotQ.refetch} />;

  const lot = lotQ.data;
  const fwd = fwdQ.data?.consumptions ?? [];
  const upstream = bwdQ.data?.upstream_consumptions ?? [];

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <View style={styles.headerRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.h1}>{lot.lot_number}</Text>
          <Mono size={10} color={colors.faint} letterSpacing={0.4} style={{ marginTop: 6 }}>
            {`${lot.material?.name ?? `Material #${lot.material_id}`} · ${lot.quantity_available} ${lot.unit_of_measure}`.toUpperCase()}
          </Mono>
        </View>
        <StatusPill status={lot.status} label={humanize(String(lot.status ?? 'available'))} />
      </View>

      {canConsume && Number(lot.quantity_available) > 0 ? (
        <Pressable
          onPress={() => setConsumeOpen(true)}
          style={({ pressed }) => [styles.consumeBtn, { opacity: pressed ? 0.7 : 1 }]}>
          <Mono size={11} color={colors.accent} letterSpacing={0.5}>{t('Consume from this lot').toUpperCase()}</Mono>
        </Pressable>
      ) : null}

      <ConsumeLotModal visible={consumeOpen} lot={lot} onClose={() => setConsumeOpen(false)} />

      {/* Info */}
      <View style={{ gap: 8 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Info').toUpperCase()}</Mono>
        <View style={styles.box}>
          <KVRow label={t('Material')} value={lot.material?.name ?? '—'} />
          <KVRow label={t('Quantity')} value={`${lot.quantity_available} / ${lot.quantity_received ?? '—'} ${lot.unit_of_measure}`} mono />
          <KVRow label={t('Expiry')} value={fmtDate(lot.expiry_date)} mono />
          <KVRow label={t('Manufacturing date')} value={fmtDate(lot.manufacturing_date)} mono />
          <KVRow label={t('Supplier lot')} value={lot.supplier_lot_no ?? '—'} mono />
          <KVRow label={t('Supplier reference')} value={lot.supplier_reference ?? '—'} last />
        </View>
      </View>

      {/* Forward — consumed into */}
      <View style={{ gap: 8 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Forward — consumed into').toUpperCase()}</Mono>
        {fwdQ.isLoading ? (
          <Text style={styles.empty}>{t('Loading…')}</Text>
        ) : fwd.length === 0 ? (
          <Text style={styles.empty}>{t('No consumption recorded yet.')}</Text>
        ) : (
          <GenealogyTable rows={fwd} />
        )}
      </View>

      {/* Backward — sourced from */}
      <View style={{ gap: 8 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Backward — sourced from').toUpperCase()}</Mono>
        {bwdQ.isLoading ? (
          <Text style={styles.empty}>{t('Loading…')}</Text>
        ) : upstream.length === 0 && !bwdQ.data?.supplier_lot_no ? (
          <Text style={styles.empty}>{t('No upstream sources recorded.')}</Text>
        ) : (
          <View>
            {bwdQ.data?.supplier_lot_no ? (
              <View style={styles.box}>
                <KVRow label={t('Supplier lot')} value={bwdQ.data.supplier_lot_no} mono last />
              </View>
            ) : null}
            {upstream.length > 0 ? <GenealogyTable rows={upstream} /> : null}
          </View>
        )}
      </View>
    </ScrollView>
  );
}

function GenealogyTable({ rows }: { rows: BatchStepLotConsumption[] }) {
  const { t } = useTranslation();
  return (
    <View>
      <View style={[styles.row, styles.tableHead]}>
        <HCell flex={1}>{t('Work order')}</HCell>
        <HCell flex={1}>{t('Batch')}</HCell>
        <HCell w={110}>{t('When')}</HCell>
        <HCell w={72}>{t('Qty')}</HCell>
      </View>
      {rows.map((c) => {
        const wo = c.batchStep?.batch?.work_order;
        const woNo = wo?.order_no ?? '—';
        const batchNo = c.batchStep?.batch?.batch_no ?? '—';
        const when = c.recorded_at ? fmtDateTime(c.recorded_at) : '—';
        return (
          <View key={c.id} style={[styles.row, styles.tableRow]}>
            <View style={{ flex: 1 }}>
              <Mono size={11} color={colors.accent}>{woNo}</Mono>
            </View>
            <View style={{ flex: 1 }}>
              <Mono size={11} color={colors.muted}>{batchNo}</Mono>
            </View>
            <View style={{ width: 110 }}>
              <Mono size={10} color={colors.faint}>{when}</Mono>
            </View>
            <View style={{ width: 72 }}>
              <Mono size={11} color={colors.ink}>{`${c.quantity} ${c.unit_of_measure ?? ''}`.trim()}</Mono>
            </View>
          </View>
        );
      })}
    </View>
  );
}

function KVRow({ label, value, mono, last }: { label: string; value: string; mono?: boolean; last?: boolean }) {
  return (
    <View style={[styles.kvRow, last ? null : styles.kvBorder]}>
      <Mono size={9} color={colors.faint} letterSpacing={0.6}>{label.toUpperCase()}</Mono>
      {mono ? (
        <Mono size={11} color={colors.ink}>{value}</Mono>
      ) : (
        <Text style={styles.kvValue} numberOfLines={1}>{value}</Text>
      )}
    </View>
  );
}

function HCell({ children, w, flex }: { children: React.ReactNode; w?: number; flex?: number }) {
  return (
    <View style={{ width: w, flex }}>
      <Mono size={9} color={colors.faint} letterSpacing={0.6}>{String(children).toUpperCase()}</Mono>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  headerRow: { flexDirection: 'row', alignItems: 'flex-start', gap: 12 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  consumeBtn: {
    height: 40,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.accent,
    alignItems: 'center',
    justifyContent: 'center',
  },
  box: { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, paddingHorizontal: 14 },
  kvRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10, paddingVertical: 11 },
  kvBorder: { borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.line2 },
  kvValue: { fontSize: 12.5, color: colors.ink, fontFamily: fonts.sans.native.medium },
  row: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingHorizontal: 6 },
  tableHead: { paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: colors.line },
  tableRow: { paddingVertical: 11, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.line2 },
  empty: { fontSize: 13, color: colors.faint, fontFamily: fonts.sans.native.regular, padding: 16 },
});
