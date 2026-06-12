import { FontAwesome } from '@expo/vector-icons';
import { format, parseISO } from 'date-fns';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import { TabletShell } from '@/components/tablet/TabletShell';
import { Mono } from '@/components/ui/Mono';
import { SearchBar } from '@/components/ui/SearchBar';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
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
} from '@/api/materialLots';

const STATUS_COLOR: Record<string, string> = {
  available: '#1C9A55',
  pending_inspection: BRAND.amber,
  quarantined: '#7c3aed',
  consumed: '#9B9892',
  scrapped: '#D6442F',
  expired: '#D6442F',
};

/**
 * Tablet Lot Genealogy Explorer — admin-facing 3-pane:
 *  - LEFT: lot search + recent lots.
 *  - CENTER: selected lot header + visual tree (sources → self → consumed).
 *  - RIGHT: consumption log scrubbing through every step that touched the lot.
 */
export function TabletLotGenealogyExplorer() {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();

  const [search, setSearch] = useState('');
  const [lotId, setLotId] = useState<number | null>(null);

  const lotsQ = useMaterialLots({ per_page: 60 });
  const lots = lotsQ.data?.data ?? [];

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (!q) return lots;
    return lots.filter((l) =>
      `${l.lot_number} ${l.material?.name ?? ''}`.toLowerCase().includes(q),
    );
  }, [lots, search]);

  const fallbackId = filtered[0]?.id ?? lots[0]?.id ?? null;
  const activeId = lotId ?? fallbackId;
  const lotQ = useMaterialLot(activeId ?? undefined);
  const fwdQ = useLotForwardGenealogy(activeId ?? undefined);
  const bwdQ = useLotBackwardGenealogy(activeId ?? undefined);

  return (
    <TabletShell
      eyebrow={`${t('LOT GENEALOGY').toUpperCase()} · ${t('ISA-95 TRACEABILITY').toUpperCase()}`}
      title={t('Material lot explorer')}
      right={
        <Pressable
          style={({ pressed }) => [
            styles.scanBtn,
            { borderColor: palette.border, opacity: pressed ? 0.7 : 1 },
          ]}>
          <FontAwesome name="qrcode" size={14} color={palette.text} />
          <Mono size={11.5} color={palette.text} weight="600" letterSpacing={0.4}>
            {t('SCAN LOT').toUpperCase()}
          </Mono>
        </Pressable>
      }>
      <View style={styles.grid}>
        {/* LEFT — search + recent lots */}
        <View
          style={[
            styles.panel,
            styles.searchPanel,
            { backgroundColor: palette.surface, borderColor: palette.border },
          ]}>
          <SearchBar
            placeholder="Search lots…"
            value={search}
            onChangeText={setSearch}
          />
          <Mono size={10.5} color={palette.textFaint} letterSpacing={0.7} style={{ marginTop: 14 }}>
            {t('RECENT LOTS').toUpperCase()}
          </Mono>
          <ScrollView style={{ flex: 1, marginTop: 8 }} contentContainerStyle={{ gap: 6 }}>
            {filtered.map((lot) => (
              <Pressable
                key={lot.id}
                onPress={() => setLotId(lot.id)}
                style={({ pressed }) => [
                  styles.lotRow,
                  {
                    backgroundColor:
                      lot.id === activeId
                        ? BRAND.amberSoft
                        : palette.surfaceAlt,
                    borderColor:
                      lot.id === activeId ? BRAND.amber : 'transparent',
                    opacity: pressed ? 0.85 : 1,
                  },
                ]}>
                <Mono size={10.5} color={palette.text} weight="700" letterSpacing={0.3}>
                  {lot.lot_number}
                </Mono>
                <Text
                  style={[styles.lotMat, { color: palette.textMuted }]}
                  numberOfLines={1}>
                  {lot.material?.name ?? `Material #${lot.material_id}`}
                </Text>
                <Mono
                  size={10}
                  color={palette.textFaint}
                  letterSpacing={0.3}
                  weight="600"
                  style={{ marginTop: 4 }}>
                  {lot.quantity_available} {lot.unit_of_measure.toUpperCase()}
                </Mono>
              </Pressable>
            ))}
            {filtered.length === 0 ? (
              <Mono
                size={11}
                color={palette.textFaint}
                style={{ textAlign: 'center', padding: 16 }}>
                {t('No lots').toUpperCase()}
              </Mono>
            ) : null}
          </ScrollView>
        </View>

        {/* CENTER — lot card + visual tree */}
        <View
          style={[
            styles.panel,
            styles.treePanel,
            { backgroundColor: palette.surface, borderColor: palette.border },
          ]}>
          <ScrollView contentContainerStyle={{ gap: 14 }}>
            {lotQ.data ? (
              <LotHero lot={lotQ.data} palette={palette} />
            ) : (
              <Mono size={11} color={palette.textFaint} style={{ padding: 16 }}>
                {t('Pick a lot to explore').toUpperCase()}
              </Mono>
            )}

            {lotQ.data ? (
              <>
                <Mono size={10.5} color={palette.textFaint} letterSpacing={0.8}>
                  {t('GENEALOGY · TREE VIEW').toUpperCase()}
                </Mono>
                <View
                  style={[
                    styles.treeBlock,
                    { backgroundColor: palette.surfaceAlt, borderColor: palette.border },
                  ]}>
                  <GenealogyTree
                    backward={bwdQ.data}
                    forward={fwdQ.data}
                    selfLot={lotQ.data}
                    palette={palette}
                  />
                </View>
              </>
            ) : null}
          </ScrollView>
        </View>

        {/* RIGHT — consumption log */}
        <View
          style={[
            styles.panel,
            styles.logPanel,
            { backgroundColor: palette.surface, borderColor: palette.border },
          ]}>
          <Mono size={10.5} color={palette.textFaint} letterSpacing={0.8}>
            {t('CONSUMPTION LOG').toUpperCase()}
          </Mono>
          <ScrollView style={{ flex: 1, marginTop: 12 }} contentContainerStyle={{ gap: 8 }}>
            {(fwdQ.data?.consumptions ?? []).map((c) => (
              <ConsumptionRow key={c.id} consumption={c} palette={palette} />
            ))}
            {(fwdQ.data?.consumptions ?? []).length === 0 ? (
              <Mono
                size={11}
                color={palette.textFaint}
                style={{ textAlign: 'center', padding: 16 }}>
                {t('Lot not yet consumed').toUpperCase()}
              </Mono>
            ) : null}
          </ScrollView>
        </View>
      </View>
    </TabletShell>
  );
}

function LotHero({
  lot,
  palette,
}: {
  lot: MaterialLot;
  palette: typeof Colors.light;
}) {
  const { t } = useTranslation();
  const statusColor =
    STATUS_COLOR[(lot.status as MaterialLotStatus) ?? 'available'] ?? palette.textMuted;
  return (
    <View
      style={[
        styles.hero,
        { backgroundColor: BRAND.amberSoft, borderColor: BRAND.amber },
      ]}>
      <View style={styles.heroRow}>
        <View style={{ flex: 1 }}>
          <Mono size={10.5} color={BRAND.amber} weight="700" letterSpacing={0.8}>
            {t('SELECTED LOT').toUpperCase()}
          </Mono>
          <Mono size={16} color={palette.text} weight="700" letterSpacing={0.3} style={{ marginTop: 6 }}>
            {lot.lot_number}
          </Mono>
          <Text style={[styles.heroSub, { color: palette.textMuted }]}>
            {lot.material?.name ?? `Material #${lot.material_id}`}
          </Text>
        </View>
        <View style={{ alignItems: 'flex-end' }}>
          <Mono size={22} color={palette.text} weight="700" letterSpacing={-0.4}>
            {lot.quantity_available}
            <Mono size={12} color={palette.textFaint}> {lot.unit_of_measure.toUpperCase()}</Mono>
          </Mono>
          <View
            style={[
              styles.statePill,
              { backgroundColor: `${statusColor}22` },
            ]}>
            <View style={[styles.stateDot, { backgroundColor: statusColor }]} />
            <Mono size={9} color={statusColor} weight="700" letterSpacing={0.5}>
              {(lot.status ?? 'available').toUpperCase()}
            </Mono>
          </View>
        </View>
      </View>
      <View style={styles.heroKvRow}>
        <KvTile label="EXP" value={lot.expiry_date ?? '—'} palette={palette} />
        <KvTile label="SUBLOTS" value={String(lot.sublots?.length ?? 0)} palette={palette} />
        <KvTile
          label="RECEIVED"
          value={String(lot.quantity_received)}
          palette={palette}
        />
        <KvTile
          label="AVAIL"
          value={String(lot.quantity_available)}
          palette={palette}
        />
      </View>
    </View>
  );
}

function KvTile({
  label,
  value,
  palette,
}: {
  label: string;
  value: string;
  palette: typeof Colors.light;
}) {
  return (
    <View
      style={[
        styles.kvTile,
        { backgroundColor: 'rgba(255,255,255,0.5)' },
      ]}>
      <Mono size={8.5} color={palette.textFaint} letterSpacing={0.5}>
        {label}
      </Mono>
      <Mono size={11} color={palette.text} weight="700" style={{ marginTop: 2 }}>
        {value}
      </Mono>
    </View>
  );
}

function GenealogyTree({
  backward,
  forward,
  selfLot,
  palette,
}: {
  backward?: BackwardGenealogyPayload;
  forward?: ForwardGenealogyPayload;
  selfLot: MaterialLot;
  palette: typeof Colors.light;
}) {
  const { t } = useTranslation();
  const sources = backward?.upstream_consumptions ?? [];
  const consumed = forward?.consumptions ?? [];

  return (
    <View style={{ gap: 8 }}>
      {/* Sources row */}
      <View style={styles.treeRow}>
        {sources.length === 0 ? (
          <View style={[styles.treeNode, { backgroundColor: palette.surfaceAlt, borderColor: palette.border }]}>
            <Mono size={9} color={palette.textFaint}>
              {(backward?.supplier_lot_no ?? t('SUPPLIER LOT')).toUpperCase()}
            </Mono>
            <Mono size={9} color={palette.textFaint} style={{ marginTop: 2 }}>
              {(backward?.supplier_reference ?? '—').toUpperCase()}
            </Mono>
          </View>
        ) : (
          sources.slice(0, 4).map((c) => (
            <View
              key={c.id}
              style={[styles.treeNode, { backgroundColor: '#EA5A2B' }]}>
              <Mono size={9.5} color="#fff" weight="700" letterSpacing={0.3}>
                {c.materialLot?.lot_number ?? `LOT #${c.material_lot_id}`}
              </Mono>
              <Mono size={9} color="rgba(255,255,255,0.7)" style={{ marginTop: 2 }}>
                {c.quantity} {c.unit_of_measure ?? ''}
              </Mono>
            </View>
          ))
        )}
      </View>

      {/* Arrow down */}
      <View style={styles.arrow}>
        <FontAwesome name="long-arrow-down" size={20} color={palette.textFaint} />
      </View>

      {/* Self */}
      <View style={styles.treeRow}>
        <View
          style={[
            styles.treeNodeSelf,
            { backgroundColor: BRAND.amber, borderColor: palette.text },
          ]}>
          <Mono size={12} color="#1a1208" weight="700" letterSpacing={0.3}>
            {selfLot.lot_number}
          </Mono>
          <Mono size={9} color="#5b3a06" weight="700" style={{ marginTop: 2 }}>
            {t('SELECTED').toUpperCase()} · {selfLot.quantity_available}{' '}
            {selfLot.unit_of_measure.toUpperCase()}
          </Mono>
        </View>
      </View>

      {/* Arrow down */}
      <View style={styles.arrow}>
        <FontAwesome name="long-arrow-down" size={20} color={palette.textFaint} />
      </View>

      {/* Consumed children */}
      <View style={[styles.treeRow, { flexWrap: 'wrap' }]}>
        {consumed.slice(0, 8).map((c) => (
          <View
            key={c.id}
            style={[
              styles.treeNode,
              { borderColor: `${BRAND.amber}60`, borderWidth: 1.5 },
            ]}>
            <Mono size={9.5} color={BRAND.amber} weight="700" letterSpacing={0.3}>
              {c.batchStep?.batch?.work_order?.order_no ?? '—'}
            </Mono>
            <Mono size={9} color={palette.textFaint} style={{ marginTop: 2 }}>
              {c.quantity} · {relTime(c.recorded_at)}
            </Mono>
          </View>
        ))}
        {consumed.length === 0 ? (
          <Mono size={10} color={palette.textFaint}>
            {t('Not yet consumed').toUpperCase()}
          </Mono>
        ) : null}
      </View>
    </View>
  );
}

function ConsumptionRow({
  consumption,
  palette,
}: {
  consumption: BatchStepLotConsumption;
  palette: typeof Colors.light;
}) {
  const wo = consumption.batchStep?.batch?.work_order;
  return (
    <View style={[styles.logRow, { backgroundColor: palette.surfaceAlt }]}>
      <View style={styles.logHead}>
        <Mono size={10} color={palette.textFaint} weight="600">
          {relTime(consumption.recorded_at) || '—'}
        </Mono>
        <Mono size={11} color={palette.text} weight="700">
          {consumption.quantity} {consumption.unit_of_measure ?? ''}
        </Mono>
      </View>
      <Text style={[styles.logTitle, { color: palette.text }]}>
        {wo?.order_no ?? '—'}
        {consumption.batchStep?.batch?.batch_no
          ? ` · ${consumption.batchStep.batch.batch_no}`
          : ''}
      </Text>
      <Mono size={10} color={palette.textFaint} style={{ marginTop: 2 }}>
        {(wo?.product_type?.name ?? '—').toUpperCase()}
        {consumption.recordedBy
          ? ` · ${(consumption.recordedBy.username ?? '').toUpperCase()}`
          : ''}
      </Mono>
    </View>
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
  grid: { flex: 1, flexDirection: 'row', gap: 14, minHeight: 0 },
  panel: { borderRadius: 16, borderWidth: 1, padding: 14 },
  searchPanel: { width: 280, flexDirection: 'column' },
  treePanel: { flex: 1, padding: 18 },
  logPanel: { width: 380 },

  scanBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    height: 38,
    paddingHorizontal: 14,
    borderRadius: 10,
    borderWidth: 1,
  },

  lotRow: {
    padding: 10,
    borderRadius: 8,
    borderWidth: 1,
  },
  lotMat: { fontSize: 11, marginTop: 4 },

  hero: { padding: 16, borderRadius: 12, borderWidth: 2 },
  heroRow: { flexDirection: 'row', alignItems: 'flex-start', gap: 12 },
  heroSub: { fontSize: 13, marginTop: 4 },
  statePill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingVertical: 3,
    paddingHorizontal: 8,
    borderRadius: 999,
    marginTop: 6,
  },
  stateDot: { width: 6, height: 6, borderRadius: 3 },
  heroKvRow: { flexDirection: 'row', gap: 6, marginTop: 14 },
  kvTile: { flex: 1, padding: 6, borderRadius: 6 },

  treeBlock: { padding: 14, borderRadius: 12, borderWidth: 1 },
  treeRow: { flexDirection: 'row', gap: 8, justifyContent: 'center' },
  treeNode: {
    paddingVertical: 8,
    paddingHorizontal: 12,
    borderRadius: 8,
    alignItems: 'center',
    minWidth: 100,
  },
  treeNodeSelf: {
    paddingVertical: 10,
    paddingHorizontal: 16,
    borderRadius: 10,
    borderWidth: 2,
    alignItems: 'center',
  },
  arrow: { alignItems: 'center', paddingVertical: 2 },

  logRow: { padding: 10, borderRadius: 8 },
  logHead: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'baseline' },
  logTitle: { fontSize: 12, fontWeight: '600', marginTop: 4 },
});
