/**
 * Material detail — mirrors the web admin materials Show page: header (name +
 * code + status), a stock breakdown (on hand / reserved / available / min /
 * value), supplier, details/identifiers, and the lots / stock movements / BOM
 * usage tables. Edit / delete wiring via REST useMaterial / useDeleteMaterial.
 */
import { format, formatDistanceToNowStrict, parseISO } from 'date-fns';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';

import { colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useDeleteMaterial, useMaterial } from '@/hooks/queries/useBom';
import type { MaterialBomUsage, MaterialLotSummary, MaterialStockMovement } from '@/api/bom';

function num(v: number | string | null | undefined): number | null {
  if (v == null || v === '') return null;
  const n = typeof v === 'string' ? parseFloat(v) : v;
  return Number.isFinite(n) ? n : null;
}

/** Fixed-3-decimal quantity, matching the web Show page fmt(). */
function fmt3(v: number | string | null | undefined): string {
  return Number(v ?? 0).toFixed(3);
}

function ucFirst(v: string | null | undefined): string {
  if (!v) return '—';
  return v.charAt(0).toUpperCase() + v.slice(1);
}

interface StockState {
  label: string;
  color: string;
  bg: string;
}

/** Health band driven by the truthful available (on hand − reserved) vs min. */
function stockState(available: number | null, min: number | null): StockState {
  if (available == null) return { label: 'No data', color: colors.faint, bg: colors.chip };
  if (available <= 0) return { label: 'Out of stock', color: colors.blocked, bg: colors.blockedBg };
  if (min != null && available <= min) {
    return { label: 'Low stock', color: colors.downtime, bg: colors.downtimeBg };
  }
  return { label: 'Healthy', color: colors.running, bg: colors.runningBg };
}

export function MaterialDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();
  const { t } = useTranslation();
  const del = useDeleteMaterial();

  const query = useMaterial(numericId);

  const onDelete = () =>
    Alert.alert(t('Delete material?'), query.data?.name ?? '', [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () =>
          del.mutate(numericId, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert(t('Could not delete'), e.message),
          }),
      },
    ]);

  if (query.isLoading && !query.data) return <LoadingState />;
  if (query.isError || !query.data) return <ErrorState error={query.error} onRetry={query.refetch} />;

  const m = query.data;
  const active = m.is_active !== false;
  const stock = num(m.stock_quantity);
  const reserved = num(m.reserved_quantity) ?? 0;
  const available = num(m.available_quantity) ?? (stock != null ? stock - reserved : null);
  const min = num(m.min_stock_level);
  const unitPrice = num(m.unit_price);
  const state = stockState(available, min);
  const unit = m.unit_of_measure ?? '';
  const stockValue = stock != null && unitPrice != null ? stock * unitPrice : null;
  const lots = m.lots ?? [];
  const movements = m.recent_movements ?? [];
  const bomUsage = m.bom_usage ?? [];

  const lastSync = m.last_stock_sync_at;
  const lastSyncLabel = (() => {
    if (!lastSync) return null;
    try {
      return format(parseISO(lastSync), 'HH:mm');
    } catch {
      return null;
    }
  })();
  const lastSyncAgo = (() => {
    if (!lastSync) return null;
    try {
      return formatDistanceToNowStrict(parseISO(lastSync), { addSuffix: true });
    } catch {
      return null;
    }
  })();

  const fillTarget = (min ?? 0) * 4 || 100;
  const fillPct = available != null ? Math.max(0, Math.min(100, Math.round((available / fillTarget) * 100))) : 0;

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1} numberOfLines={2}>{m.name}</Text>
        <StatusPill status={active ? 'IN_PROGRESS' : 'CANCELLED'} label={active ? t('Active') : t('Inactive')} />
      </View>
      <ScrollView style={styles.screen} contentContainerStyle={styles.scroll}>
        <View>
          <Mono size={10.5} color={colors.faint} letterSpacing={0.5}>{m.code}</Mono>
          {m.description ? <Text style={styles.desc} numberOfLines={3}>{m.description}</Text> : null}
        </View>

        {/* Stock */}
        <SectionLabel>{t('Stock')}</SectionLabel>
        <View style={[styles.box, { gap: 14 }]}>
          <View style={styles.stockRow}>
            <View>
              <Mono size={36} color={colors.ink} weight="600">{fmt3(available)}</Mono>
              <Mono size={11} color={colors.faint} letterSpacing={0.4} style={{ marginTop: 4 }}>
                {(unit || t('unit')).toUpperCase()} {t('available').toUpperCase()}
              </Mono>
            </View>
            <View style={[styles.statePill, { backgroundColor: state.bg }]}>
              <View style={[styles.stateDot, { backgroundColor: state.color }]} />
              <Mono size={10.5} color={state.color} weight="700" letterSpacing={0.5}>{t(state.label).toUpperCase()}</Mono>
            </View>
          </View>
          <View style={styles.stockBar}>
            <View style={{ height: '100%', width: `${fillPct}%`, backgroundColor: state.color, borderRadius: 3 }} />
          </View>
          <View style={[styles.box, { paddingVertical: 4, borderColor: colors.line2 }]}>
            <KvRow label={t('On hand')} value={`${fmt3(stock)} ${unit}`} />
            <KvRow label={t('Reserved by active batches')} value={`${fmt3(reserved)} ${unit}`} valueColor={colors.downtime} />
            <KvRow label={t('Available')} value={`${fmt3(available)} ${unit}`} valueColor={available != null && available <= 0 ? colors.blocked : colors.running} />
            <KvRow label={t('Min stock level')} value={min != null ? `${fmt3(min)} ${unit}` : '—'} />
            {stockValue != null ? (
              <KvRow label={t('Stock value')} value={`${stockValue.toFixed(2)} ${m.price_currency ?? 'PLN'}`} last />
            ) : null}
          </View>
          {lastSyncLabel ? (
            <Mono size={10.5} color={colors.faint} letterSpacing={0.4}>
              {t('Last sync').toUpperCase()} <Mono size={10.5} color={colors.muted}>{lastSyncLabel}</Mono>
              {lastSyncAgo ? <Mono size={10.5} color={colors.faint}> · {lastSyncAgo.toUpperCase()}</Mono> : null}
            </Mono>
          ) : null}
        </View>

        {/* Supplier */}
        {m.supplier_name || m.supplier_code || unitPrice != null ? (
          <>
            <SectionLabel>{t('Supplier')}</SectionLabel>
            <View style={styles.box}>
              <View style={styles.supplierRow}>
                <View style={styles.supplierIcon}>
                  <Mono size={13} color={colors.muted} weight="700" letterSpacing={0.5}>{(m.supplier_name ?? '?').slice(0, 2).toUpperCase()}</Mono>
                </View>
                <View style={{ flex: 1, minWidth: 0 }}>
                  <Text style={styles.supplierName} numberOfLines={1}>{m.supplier_name ?? '—'}</Text>
                  {m.supplier_code ? <Mono size={10.5} color={colors.faint} style={{ marginTop: 3 }}>{m.supplier_code}</Mono> : null}
                </View>
                {unitPrice != null ? (
                  <View style={{ alignItems: 'flex-end' }}>
                    <Mono size={14} color={colors.ink} weight="700">
                      {unitPrice.toFixed(2)}
                      <Mono size={10} color={colors.faint}> {(m.price_currency ?? 'PLN')}/{unit.toUpperCase() || t('unit').toUpperCase()}</Mono>
                    </Mono>
                  </View>
                ) : null}
              </View>
            </View>
          </>
        ) : null}

        {/* Details / Identifiers */}
        <SectionLabel>{t('Details')}</SectionLabel>
        <View style={[styles.box, { paddingVertical: 4 }]}>
          <IdRow label={t('Material type')} value={m.material_type?.name ?? '—'} />
          <IdRow label={t('Unit of Measure')} value={unit || '—'} />
          <IdRow label={t('Tracking')} value={ucFirst(m.tracking_type)} />
          <IdRow label={t('Default Scrap %')} value={m.default_scrap_percentage != null ? `${m.default_scrap_percentage}%` : '—'} />
          <IdRow label={t('EAN')} value={m.ean ?? '—'} mono />
          <IdRow label={t('External code')} value={m.external_code ?? '—'} mono />
          <IdRow label={t('External system')} value={m.external_system ?? '—'} last />
        </View>

        {/* Lots */}
        {lots.length > 0 ? (
          <>
            <SectionLabel>{t('Lots')} ({lots.length})</SectionLabel>
            <View style={[styles.box, { padding: 0 }]}>
              {lots.map((lot, i) => (
                <LotRow key={lot.id} lot={lot} last={i === lots.length - 1} />
              ))}
            </View>
          </>
        ) : null}

        {/* Recent stock movements */}
        {movements.length > 0 ? (
          <>
            <SectionLabel>{t('Recent stock movements')}</SectionLabel>
            <View style={[styles.box, { padding: 0 }]}>
              {movements.map((mv, i) => (
                <MovementRow key={mv.id} mv={mv} last={i === movements.length - 1} />
              ))}
            </View>
          </>
        ) : null}

        {/* Used in BOMs */}
        {bomUsage.length > 0 ? (
          <>
            <SectionLabel>{t('Used in BOMs')} ({bomUsage.length})</SectionLabel>
            <View style={[styles.box, { padding: 0 }]}>
              {bomUsage.map((b, i) => (
                <BomUsageRow key={b.id} usage={b} last={i === bomUsage.length - 1} />
              ))}
            </View>
          </>
        ) : null}

        <View style={styles.actions}>
          <Button title={t('Edit')} onPress={() => router.push(`/(drawer)/admin/materials/${numericId}/edit` as never)} />
          <View style={{ flex: 1 }} />
          <Button title={t('Delete')} variant="danger" onPress={onDelete} loading={del.isPending} />
        </View>
      </ScrollView>
    </View>
  );
}

function SectionLabel({ children }: { children: React.ReactNode }) {
  return (
    <Mono size={9} color={colors.faint} letterSpacing={0.6} style={styles.sectionLabel}>
      {String(children).toUpperCase()}
    </Mono>
  );
}

function KvRow({ label, value, valueColor, last }: { label: string; value: string; valueColor?: string; last?: boolean }) {
  return (
    <View style={[styles.idRow, last ? null : styles.idRowBorder]}>
      <Mono size={10.5} color={colors.faint} letterSpacing={0.5}>{label.toUpperCase()}</Mono>
      <Mono size={11.5} color={valueColor ?? colors.ink} weight="700">{value}</Mono>
    </View>
  );
}

function IdRow({ label, value, mono, last }: { label: string; value: string; mono?: boolean; last?: boolean }) {
  return (
    <View style={[styles.idRow, last ? null : styles.idRowBorder]}>
      <Mono size={10.5} color={colors.faint} letterSpacing={0.5}>{label.toUpperCase()}</Mono>
      <Text style={[styles.idValue, { fontFamily: mono ? fonts.mono.native.regular : fonts.sans.native.medium }]} numberOfLines={1}>{value}</Text>
    </View>
  );
}

function LotRow({ lot, last }: { lot: MaterialLotSummary; last?: boolean }) {
  const { t } = useTranslation();
  return (
    <View style={[styles.tableRow, last ? null : styles.idRowBorder]}>
      <View style={styles.tableRowHead}>
        <Mono size={11} color={colors.ink} weight="700">{lot.lot_number}</Mono>
        <View style={styles.chip}>
          <Mono size={9} color={colors.muted} weight="700" letterSpacing={0.3}>{(lot.status ?? '').toUpperCase()}</Mono>
        </View>
      </View>
      <View style={styles.tableMetaRow}>
        <Mono size={9.5} color={colors.faint}>
          {t('Supplier ref').toUpperCase()} <Mono size={9.5} color={colors.muted}>{lot.supplier_lot_no ?? '—'}</Mono>
        </Mono>
        <Mono size={9.5} color={colors.faint}>
          {t('Received').toUpperCase()} <Mono size={9.5} color={colors.muted}>{fmt3(lot.quantity_received)}</Mono>
        </Mono>
      </View>
      <View style={styles.tableMetaRow}>
        <Mono size={9.5} color={colors.faint}>
          {t('Available').toUpperCase()} <Mono size={9.5} color={colors.muted}>{fmt3(lot.quantity_available)}</Mono>
        </Mono>
        <Mono size={9.5} color={lot.is_expired ? colors.blocked : colors.faint}>
          {t('Expiry').toUpperCase()} <Mono size={9.5} color={lot.is_expired ? colors.blocked : colors.muted}>{lot.expiry_date ? lot.expiry_date.substring(0, 10) : '—'}</Mono>
        </Mono>
      </View>
    </View>
  );
}

const MOVEMENT_COLORS: Record<string, string> = {
  receipt: colors.running,
  return: colors.accent,
  allocation: colors.downtime,
  consume: colors.muted,
  scrap: colors.blocked,
};

function MovementRow({ mv, last }: { mv: MaterialStockMovement; last?: boolean }) {
  const { t } = useTranslation();
  const qty = Number(mv.quantity ?? 0);
  const qtyColor = qty > 0 ? colors.running : qty < 0 ? colors.blocked : colors.muted;
  const when = mv.performed_at ? mv.performed_at.substring(0, 16).replace('T', ' ') : '—';
  return (
    <View style={[styles.tableRow, last ? null : styles.idRowBorder]}>
      <View style={styles.tableRowHead}>
        <Mono size={10.5} color={MOVEMENT_COLORS[mv.movement_type] ?? colors.muted} weight="700">{mv.movement_type}</Mono>
        <Mono size={11} color={qtyColor} weight="700">{qty > 0 ? '+' : ''}{fmt3(qty)}</Mono>
      </View>
      <View style={styles.tableMetaRow}>
        <Mono size={9.5} color={colors.faint}>{when}</Mono>
        <Mono size={9.5} color={colors.faint}>
          {t('Balance').toUpperCase()} <Mono size={9.5} color={colors.muted}>{fmt3(mv.balance_after)}</Mono>
        </Mono>
      </View>
      {mv.reason || mv.performed_by ? (
        <View style={styles.tableMetaRow}>
          {mv.reason ? <Text style={styles.reason} numberOfLines={1}>{mv.reason}</Text> : <View style={{ flex: 1 }} />}
          {mv.performed_by ? <Mono size={9.5} color={colors.faint}>{(mv.performed_by.name ?? '').toUpperCase()}</Mono> : null}
        </View>
      ) : null}
    </View>
  );
}

function BomUsageRow({ usage, last }: { usage: MaterialBomUsage; last?: boolean }) {
  const { t } = useTranslation();
  const tpl = usage.process_template;
  return (
    <View style={[styles.tableRow, last ? null : styles.idRowBorder]}>
      <View style={styles.tableRowHead}>
        <Text style={styles.tplName} numberOfLines={1}>{tpl?.name ?? '—'}</Text>
        <Mono size={10.5} color={colors.muted} weight="700">{usage.quantity_per_unit}</Mono>
      </View>
      <View style={styles.tableMetaRow}>
        <Mono size={9.5} color={colors.faint} style={{ flex: 1 }} numberOfLines={1}>
          {(tpl?.product_type?.name ?? '—').toUpperCase()}
        </Mono>
        <Mono size={9.5} color={colors.faint}>
          {t('Scrap').toUpperCase()} <Mono size={9.5} color={colors.muted}>{usage.scrap_percentage}%</Mono>
        </Mono>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingVertical: 16 },
  h1: { flex: 1, fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  scroll: { padding: 16, gap: 10, paddingBottom: 32 },
  desc: { fontSize: 13, color: colors.muted, marginTop: 6, lineHeight: 19, fontFamily: fonts.sans.native.regular },
  sectionLabel: { marginTop: 6, paddingHorizontal: 2 },
  box: { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  stockRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-end' },
  statePill: { flexDirection: 'row', alignItems: 'center', gap: 6, paddingVertical: 4, paddingHorizontal: 10, borderRadius: radius.pill },
  stateDot: { width: 8, height: 8, borderRadius: 4 },
  stockBar: { height: 6, borderRadius: 3, overflow: 'hidden', backgroundColor: colors.chip },
  supplierRow: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  supplierIcon: { width: 44, height: 44, borderRadius: radius.md, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.chip },
  supplierName: { fontSize: 13, fontFamily: fonts.sans.native.semibold, color: colors.ink },
  idRow: { paddingVertical: 10, paddingHorizontal: 4, flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 12 },
  idRowBorder: { borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.line2 },
  idValue: { fontSize: 12.5, color: colors.ink, flexShrink: 1, textAlign: 'right' },
  tableRow: { paddingVertical: 11, paddingHorizontal: 12, gap: 5 },
  tableRowHead: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8 },
  tableMetaRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8 },
  chip: { paddingVertical: 2, paddingHorizontal: 6, borderRadius: radius.sm, backgroundColor: colors.chip },
  reason: { flex: 1, fontSize: 11, color: colors.muted, fontFamily: fonts.sans.native.regular },
  tplName: { flex: 1, fontSize: 12.5, fontFamily: fonts.sans.native.semibold, color: colors.ink },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 10 },
});
