/**
 * Lot Genealogy Explorer — admin traceability console (web counterpart:
 * admin/traceability/Index.jsx). Resolve a material lot, then walk its
 * backward sources, forward consumption and the visual genealogy tree.
 * Geist White, light-only v1. All data hooks and logic are unchanged from the
 * original tablet screen — only the presentation was re-skinned to tokens.
 */
import { FontAwesome } from '@expo/vector-icons';
import { format, parseISO } from 'date-fns';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import { colors, fonts, radius } from '@openmes/ui';
import { SearchField } from '@openmes/ui/native';

import { Mono } from '@/components/ui/Mono';
import {
  useLotBackwardGenealogy,
  useLotForwardGenealogy,
  useMaterialLot,
  useMaterialLots,
} from '@/hooks/queries/useMaterialLots';
import type {
  BackwardGenealogyPayload,
  BatchStepLotConsumption,
  ForwardGenealogyPayload,
  MaterialLot,
  MaterialLotStatus,
  RecallImpactPayload,
} from '@/api/materialLots';

/** Format a lot / consumption quantity to 2 decimals, matching web formatNumber. */
function fmtQty(v: string | number | null | undefined): string {
  if (v == null || v === '') return '—';
  const n = typeof v === 'string' ? parseFloat(v) : v;
  return Number.isFinite(n) ? n.toFixed(2) : String(v);
}

const STATUS_COLOR: Record<string, string> = {
  available: colors.running,
  pending_inspection: colors.downtime,
  quarantined: '#7c3aed',
  consumed: colors.muted,
  scrapped: colors.blocked,
  expired: colors.blocked,
};

export function LotGenealogyExplorer() {
  const { t } = useTranslation();

  const [search, setSearch] = useState('');
  const [lotId, setLotId] = useState<number | null>(null);

  const lotsQ = useMaterialLots({ per_page: 60 });
  const lots = lotsQ.data?.data ?? [];

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (!q) return lots;
    return lots.filter((l) => `${l.lot_number} ${l.material?.name ?? ''}`.toLowerCase().includes(q));
  }, [lots, search]);

  const fallbackId = filtered[0]?.id ?? lots[0]?.id ?? null;
  const activeId = lotId ?? fallbackId;
  const lotQ = useMaterialLot(activeId ?? undefined);
  const fwdQ = useLotForwardGenealogy(activeId ?? undefined);
  const bwdQ = useLotBackwardGenealogy(activeId ?? undefined);

  const consumptions = fwdQ.data?.consumptions ?? [];

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Traceability')}</Text>
      </View>

      <ScrollView contentContainerStyle={styles.body}>
        <SearchField value={search} onChange={setSearch} placeholder={t('Search lots…')} />

        {/* Recent lots */}
        <Label>{t('Recent lots')}</Label>
        <View style={styles.lotChips}>
          {filtered.slice(0, 12).map((lot) => {
            const active = lot.id === activeId;
            return (
              <Pressable
                key={lot.id}
                onPress={() => setLotId(lot.id)}
                style={[styles.lotChip, active && styles.lotChipActive]}>
                <Mono size={10.5} color={active ? '#FFFFFF' : colors.ink} weight="700">
                  {lot.lot_number}
                </Mono>
                <Text style={[styles.lotChipMat, { color: active ? 'rgba(255,255,255,0.8)' : colors.muted }]} numberOfLines={1}>
                  {lot.material?.name ?? t('Material #{{id}}', { id: lot.material_id })}
                </Text>
              </Pressable>
            );
          })}
          {filtered.length === 0 ? <Text style={styles.empty}>{t('No lots.')}</Text> : null}
        </View>

        {/* Selected lot */}
        {lotQ.data ? (
          <>
            <LotHero lot={lotQ.data} />

            {fwdQ.data?.recall ? <RecallImpact recall={fwdQ.data.recall} /> : null}

            <Label>{t('Genealogy · tree view')}</Label>
            <View style={styles.treeBlock}>
              <GenealogyTree backward={bwdQ.data} forward={fwdQ.data} selfLot={lotQ.data} />
            </View>

            <Label>{t('Consumption log')}</Label>
            {consumptions.map((c) => (
              <ConsumptionRow key={c.id} consumption={c} />
            ))}
            {consumptions.length === 0 ? <Text style={styles.empty}>{t('Lot not yet consumed.')}</Text> : null}
          </>
        ) : (
          <Text style={styles.empty}>{t('Pick a lot to explore.')}</Text>
        )}
      </ScrollView>
    </View>
  );
}

function LotHero({ lot }: { lot: MaterialLot }) {
  const { t } = useTranslation();
  const statusColor = STATUS_COLOR[(lot.status as MaterialLotStatus) ?? 'available'] ?? colors.muted;
  return (
    <View style={styles.hero}>
      <View style={styles.heroRow}>
        <View style={{ flex: 1 }}>
          <Mono size={9} color={colors.accent} weight="700" letterSpacing={0.8}>
            {t('Selected lot').toUpperCase()}
          </Mono>
          <Mono size={16} color={colors.ink} weight="700" letterSpacing={0.3} style={{ marginTop: 6 }}>
            {lot.lot_number}
          </Mono>
          <Text style={styles.heroSub}>{lot.material?.name ?? t('Material #{{id}}', { id: lot.material_id })}</Text>
        </View>
        <View style={{ alignItems: 'flex-end' }}>
          <Mono size={22} color={colors.ink} weight="700" letterSpacing={-0.4}>
            {fmtQty(lot.quantity_available)}
            <Mono size={12} color={colors.faint}>
              {' '}
              {lot.unit_of_measure.toUpperCase()}
            </Mono>
          </Mono>
          <View style={[styles.statePill, { backgroundColor: `${statusColor}22` }]}>
            <View style={[styles.stateDot, { backgroundColor: statusColor }]} />
            <Mono size={9} color={statusColor} weight="700" letterSpacing={0.5}>
              {(lot.status ?? 'available').toUpperCase()}
            </Mono>
          </View>
        </View>
      </View>
      <View style={styles.heroKvRow}>
        <KvTile label={t('EXP')} value={lot.expiry_date ? lot.expiry_date.substring(0, 10) : '—'} />
        <KvTile label={t('SUBLOTS')} value={String(lot.sublots?.length ?? 0)} />
        <KvTile label={t('RECEIVED')} value={fmtQty(lot.quantity_received)} />
        <KvTile label={t('AVAIL')} value={fmtQty(lot.quantity_available)} />
      </View>
    </View>
  );
}

function KvTile({ label, value }: { label: string; value: string }) {
  return (
    <View style={styles.kvTile}>
      <Mono size={8.5} color={colors.faint} letterSpacing={0.5}>
        {label}
      </Mono>
      <Mono size={11} color={colors.ink} weight="700" style={{ marginTop: 2 }}>
        {value}
      </Mono>
    </View>
  );
}

function GenealogyTree({
  backward,
  forward,
  selfLot,
}: {
  backward?: BackwardGenealogyPayload;
  forward?: ForwardGenealogyPayload;
  selfLot: MaterialLot;
}) {
  const { t } = useTranslation();
  const sources = backward?.upstream_consumptions ?? [];
  const consumed = forward?.consumptions ?? [];

  return (
    <View style={{ gap: 8 }}>
      {/* Sources row */}
      <View style={styles.treeRow}>
        {sources.length === 0 ? (
          <View style={[styles.treeNode, { backgroundColor: colors.panel, borderColor: colors.line, borderWidth: 1 }]}>
            <Mono size={9} color={colors.faint}>
              {(backward?.supplier_lot_no ?? t('Supplier lot')).toUpperCase()}
            </Mono>
            <Mono size={9} color={colors.faint} style={{ marginTop: 2 }}>
              {(backward?.supplier_reference ?? '—').toUpperCase()}
            </Mono>
          </View>
        ) : (
          sources.slice(0, 4).map((c) => (
            <View key={c.id} style={[styles.treeNode, { backgroundColor: colors.accent }]}>
              <Mono size={9.5} color="#fff" weight="700" letterSpacing={0.3}>
                {c.materialLot?.lot_number ?? t('LOT #{{id}}', { id: c.material_lot_id })}
              </Mono>
              <Mono size={9} color="rgba(255,255,255,0.7)" style={{ marginTop: 2 }}>
                {fmtQty(c.quantity)} {c.unit_of_measure ?? ''}
              </Mono>
            </View>
          ))
        )}
      </View>

      <View style={styles.arrow}>
        <FontAwesome name="long-arrow-down" size={20} color={colors.faint} />
      </View>

      {/* Self */}
      <View style={styles.treeRow}>
        <View style={[styles.treeNodeSelf, { backgroundColor: colors.accent, borderColor: colors.ink }]}>
          <Mono size={12} color="#fff" weight="700" letterSpacing={0.3}>
            {selfLot.lot_number}
          </Mono>
          <Mono size={9} color="rgba(255,255,255,0.85)" weight="700" style={{ marginTop: 2 }}>
            {t('Selected').toUpperCase()} · {fmtQty(selfLot.quantity_available)} {selfLot.unit_of_measure.toUpperCase()}
          </Mono>
        </View>
      </View>

      <View style={styles.arrow}>
        <FontAwesome name="long-arrow-down" size={20} color={colors.faint} />
      </View>

      {/* Consumed children */}
      <View style={[styles.treeRow, { flexWrap: 'wrap' }]}>
        {consumed.slice(0, 8).map((c) => (
          <View key={c.id} style={[styles.treeNode, { borderColor: `${colors.accent}60`, borderWidth: 1.5 }]}>
            <Mono size={9.5} color={colors.accent} weight="700" letterSpacing={0.3}>
              {c.batchStep?.batch?.work_order?.order_no ?? '—'}
            </Mono>
            <Mono size={9} color={colors.faint} style={{ marginTop: 2 }}>
              {fmtQty(c.quantity)} · {relTime(c.recorded_at)}
            </Mono>
          </View>
        ))}
        {consumed.length === 0 ? <Mono size={10} color={colors.faint}>{t('Not yet consumed').toUpperCase()}</Mono> : null}
      </View>
    </View>
  );
}

function RecallImpact({ recall }: { recall: RecallImpactPayload }) {
  const { t } = useTranslation();
  const workOrders = recall.work_orders ?? [];
  const totals = recall.totals ?? { work_orders: 0, finished_serials: 0, quantity_consumed: 0 };

  return (
    <>
      <Label>{t('Recall impact')}</Label>
      <View style={styles.recallCard}>
        <View style={styles.recallHead}>
          <Text style={styles.recallTitle}>{t('Recall impact')}</Text>
          <View style={{ flexDirection: 'row', gap: 6 }}>
            <View style={[styles.recallPill, { backgroundColor: colors.blockedBg }]}>
              <Mono size={9} color={colors.blocked} weight="700" letterSpacing={0.3}>
                {totals.work_orders} {t('WOs').toUpperCase()}
              </Mono>
            </View>
            <View style={[styles.recallPill, { backgroundColor: colors.chip }]}>
              <Mono size={9} color={colors.muted} weight="700" letterSpacing={0.3}>
                {totals.finished_serials} {t('units').toUpperCase()}
              </Mono>
            </View>
          </View>
        </View>
        <Text style={styles.recallSub}>{t('Finished work orders and units that contain this component.')}</Text>

        {workOrders.length === 0 ? (
          <Text style={styles.empty}>{t('No downstream consumption recorded yet.')}</Text>
        ) : (
          workOrders.map((wo) => (
            <View key={wo.id} style={styles.recallRow}>
              <View style={styles.recallRowHead}>
                <Mono size={11} color={colors.ink} weight="700">{wo.order_no}</Mono>
                <Text style={styles.recallProduct} numberOfLines={1}>{wo.product ?? '—'}</Text>
                <Mono size={9} color={colors.faint} letterSpacing={0.3}>{(wo.status ?? '').toUpperCase()}</Mono>
              </View>
              <Mono size={9.5} color={colors.faint} style={{ marginTop: 3 }}>
                {t('Consumed').toUpperCase()} {fmtQty(wo.quantity_consumed)}
                {wo.batches?.length ? ` · ${t('Batches').toUpperCase()} #${wo.batches.join(', #')}` : ''}
              </Mono>
              {wo.finished_serials?.length ? (
                <View style={styles.serialWrap}>
                  {wo.finished_serials.map((u) => (
                    <View key={u.serial_no} style={styles.serialChip}>
                      <Mono size={9} color={colors.accent} weight="700">{u.serial_no}</Mono>
                    </View>
                  ))}
                </View>
              ) : null}
            </View>
          ))
        )}
        {recall.truncated ? (
          <Mono size={9.5} color={colors.downtime} style={{ marginTop: 6 }}>
            {t('Trace truncated (max depth reached).').toUpperCase()}
          </Mono>
        ) : null}
      </View>
    </>
  );
}

function ConsumptionRow({ consumption }: { consumption: BatchStepLotConsumption }) {
  const wo = consumption.batchStep?.batch?.work_order;
  return (
    <View style={styles.logRow}>
      <View style={styles.logHead}>
        <Mono size={10} color={colors.faint} weight="600">
          {relTime(consumption.recorded_at) || '—'}
        </Mono>
        <Mono size={11} color={colors.ink} weight="700">
          {fmtQty(consumption.quantity)} {consumption.unit_of_measure ?? ''}
        </Mono>
      </View>
      <Text style={styles.logTitle}>
        {wo?.order_no ?? '—'}
        {consumption.batchStep?.batch?.batch_no ? ` · ${consumption.batchStep.batch.batch_no}` : ''}
      </Text>
      <Mono size={10} color={colors.faint} style={{ marginTop: 2 }}>
        {(wo?.product_type?.name ?? '—').toUpperCase()}
        {consumption.recordedBy ? ` · ${(consumption.recordedBy.username ?? '').toUpperCase()}` : ''}
      </Mono>
    </View>
  );
}

function Label({ children }: { children: React.ReactNode }) {
  return (
    <Mono size={9} color={colors.faint} letterSpacing={0.6} style={styles.sectionLabel}>
      {String(children).toUpperCase()}
    </Mono>
  );
}

function relTime(iso?: string | null): string {
  if (!iso) return '';
  try {
    const ms = Date.now() - new Date(iso).getTime();
    const min = Math.floor(ms / 60000);
    if (min < 60) return `${min}m`;
    const h = Math.floor(min / 60);
    if (h < 24) return format(parseISO(iso), 'HH:mm');
    const d = Math.floor(h / 24);
    if (d === 1) return 'YEST';
    if (d < 7) return `${d}D`;
    return format(parseISO(iso), 'MMM d');
  } catch {
    return '';
  }
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingVertical: 16 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  body: { padding: 16, paddingBottom: 40, gap: 8, maxWidth: 640, width: '100%', alignSelf: 'center' },

  sectionLabel: { marginTop: 14, marginBottom: 2 },
  empty: { fontSize: 13, color: colors.faint, fontFamily: fonts.sans.native.regular, paddingVertical: 12 },

  lotChips: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  lotChip: {
    minWidth: 130,
    flexGrow: 1,
    padding: 10,
    borderRadius: radius.sm,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.card,
  },
  lotChipActive: { backgroundColor: colors.ink, borderColor: colors.ink },
  lotChipMat: { fontSize: 11, marginTop: 4 },

  hero: { padding: 16, borderRadius: radius.md, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.card, marginTop: 8 },
  heroRow: { flexDirection: 'row', alignItems: 'flex-start', gap: 12 },
  heroSub: { fontSize: 13, marginTop: 4, color: colors.muted },
  statePill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingVertical: 3,
    paddingHorizontal: 8,
    borderRadius: radius.pill,
    marginTop: 6,
  },
  stateDot: { width: 6, height: 6, borderRadius: 3 },
  heroKvRow: { flexDirection: 'row', gap: 6, marginTop: 14 },
  kvTile: { flex: 1, padding: 8, borderRadius: radius.sm, backgroundColor: colors.panel },

  treeBlock: { padding: 14, borderRadius: radius.md, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.card, marginTop: 2 },
  treeRow: { flexDirection: 'row', gap: 8, justifyContent: 'center' },
  treeNode: { paddingVertical: 8, paddingHorizontal: 12, borderRadius: radius.sm, alignItems: 'center', minWidth: 100 },
  treeNodeSelf: { paddingVertical: 10, paddingHorizontal: 16, borderRadius: radius.md, borderWidth: 2, alignItems: 'center' },
  arrow: { alignItems: 'center', paddingVertical: 2 },

  logRow: { padding: 12, borderRadius: radius.sm, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, marginTop: 6 },
  logHead: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'baseline' },
  logTitle: { fontSize: 12, fontFamily: fonts.sans.native.semibold, color: colors.ink, marginTop: 4 },

  recallCard: { padding: 14, borderRadius: radius.md, borderWidth: 1, borderColor: colors.blocked, backgroundColor: colors.card, marginTop: 2 },
  recallHead: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: 6 },
  recallTitle: { fontSize: 14, fontFamily: fonts.sans.native.semibold, color: colors.ink },
  recallPill: { paddingVertical: 3, paddingHorizontal: 8, borderRadius: radius.pill },
  recallSub: { fontSize: 12, color: colors.muted, marginTop: 6, fontFamily: fonts.sans.native.regular },
  recallRow: { borderLeftWidth: 2, borderLeftColor: colors.blocked, paddingLeft: 10, paddingVertical: 4, marginTop: 10 },
  recallRowHead: { flexDirection: 'row', alignItems: 'center', gap: 8, flexWrap: 'wrap' },
  recallProduct: { flex: 1, fontSize: 12, color: colors.muted, fontFamily: fonts.sans.native.regular },
  serialWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 5, marginTop: 6 },
  serialChip: { paddingVertical: 2, paddingHorizontal: 6, borderRadius: radius.sm, backgroundColor: colors.chip },
});
