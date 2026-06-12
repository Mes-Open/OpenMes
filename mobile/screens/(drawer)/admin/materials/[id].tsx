import { FontAwesome } from '@expo/vector-icons';
import { format, formatDistanceToNowStrict, parseISO } from 'date-fns';
import { useLocalSearchParams } from 'expo-router';
import { ScrollView, StyleSheet, Text, View } from 'react-native';

import { Card } from '@/components/ui/Card';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useMaterial } from '@/hooks/queries/useBom';

function num(v: number | string | null | undefined): number | null {
  if (v == null || v === '') return null;
  const n = typeof v === 'string' ? parseFloat(v) : v;
  return Number.isFinite(n) ? n : null;
}

function formatStock(n: number | null): string {
  if (n == null) return '—';
  if (Number.isInteger(n)) return String(n);
  return n.toFixed(3).replace(/\.?0+$/, '');
}

interface StockState {
  label: string;
  color: string;
  bg: string;
}

function stockState(
  stock: number | null,
  min: number | null,
  palette: typeof Colors.light,
): StockState {
  if (stock == null) return { label: 'NO DATA', color: palette.textFaint, bg: palette.surfaceAlt };
  if (stock === 0) return { label: 'OUT OF STOCK', color: palette.danger, bg: palette.dangerSoft };
  if (min != null && stock <= min) {
    return { label: 'LOW STOCK', color: BRAND.amber, bg: palette.warningSoft };
  }
  return { label: 'HEALTHY', color: palette.success, bg: palette.successSoft };
}

export function MaterialDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  const query = useMaterial(numericId);

  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data) return <ErrorState error={query.error} onRetry={query.refetch} />;

  const m = query.data;
  const stock = num(m.stock_quantity);
  const min = num(m.min_stock_level);
  const unitPrice = num(m.unit_price);
  const state = stockState(stock, min, palette);
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

  // Stock-level visual: ratio of current stock vs 4× min (so the bar "fills" at
  // 4× minimum threshold). If min is missing, fill based on stock <= 100.
  const fillTarget = (min ?? 0) * 4 || 100;
  const fillPct = stock != null
    ? Math.max(0, Math.min(100, Math.round((stock / fillTarget) * 100)))
    : 0;

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader
        back
        title="Material"
        subtitle={m.code}
        rightAction={{ icon: 'pencil', onPress: () => {} }}
      />
      <ScrollView contentContainerStyle={styles.scroll}>
        {/* Header */}
        <View>
          <Mono size={10.5} color={palette.textFaint} letterSpacing={0.5}>{m.code}</Mono>
          <Text style={[styles.heroTitle, { color: palette.text }]}>{m.name}</Text>
          {m.description ? (
            <Text style={[styles.heroSub, { color: palette.textMuted }]} numberOfLines={3}>
              {m.description}
            </Text>
          ) : null}
        </View>

        {/* Stock card */}
        <SectionLabel>Stock</SectionLabel>
        <Card style={{ gap: 14 }}>
          <View style={styles.stockRow}>
            <View>
              <Text style={[styles.stockBig, { color: palette.text, fontFamily: MONO }]}>
                {formatStock(stock)}
              </Text>
              <Mono size={11} color={palette.textFaint} letterSpacing={0.4} style={{ marginTop: 4 }}>
                {(m.unit_of_measure ?? 'UNIT').toUpperCase()} ON HAND
              </Mono>
            </View>
            <View style={[styles.statePill, { backgroundColor: state.bg }]}>
              <View style={[styles.stateDot, { backgroundColor: state.color }]} />
              <Mono size={10.5} color={state.color} weight="700" letterSpacing={0.5}>
                {state.label}
              </Mono>
            </View>
          </View>
          <View style={[styles.stockBar, { backgroundColor: palette.surfaceAlt }]}>
            <View
              style={{
                height: '100%',
                width: `${fillPct}%`,
                backgroundColor: state.color,
                borderRadius: 3,
              }}
            />
          </View>
          <View style={styles.stockMetaRow}>
            <Mono size={10.5} color={palette.textFaint} letterSpacing={0.4}>
              MIN <Text style={{ color: palette.textMuted }}>{min != null ? formatStock(min) : '—'} {m.unit_of_measure?.toUpperCase() ?? ''}</Text>
            </Mono>
            {lastSyncLabel ? (
              <Mono size={10.5} color={palette.textFaint} letterSpacing={0.4}>
                LAST SYNC <Text style={{ color: palette.textMuted }}>{lastSyncLabel}</Text>
                {lastSyncAgo ? <Text style={{ color: palette.textFaint }}> · {lastSyncAgo.toUpperCase()}</Text> : null}
              </Mono>
            ) : null}
          </View>
        </Card>

        {/* Supplier card */}
        {m.supplier_name || m.supplier_code || unitPrice != null ? (
          <>
            <SectionLabel>Supplier</SectionLabel>
            <Card>
              <View style={styles.supplierRow}>
                <View style={[styles.supplierIcon, { backgroundColor: palette.surfaceAlt }]}>
                  <Mono size={13} color={palette.textMuted} weight="700" letterSpacing={0.5}>
                    {(m.supplier_name ?? '?').slice(0, 2).toUpperCase()}
                  </Mono>
                </View>
                <View style={{ flex: 1, minWidth: 0 }}>
                  <Text style={[styles.supplierName, { color: palette.text }]} numberOfLines={1}>
                    {m.supplier_name ?? '—'}
                  </Text>
                  {m.supplier_code ? (
                    <Mono size={10.5} color={palette.textFaint} style={{ marginTop: 3 }}>
                      {m.supplier_code}
                    </Mono>
                  ) : null}
                </View>
                {unitPrice != null ? (
                  <View style={{ alignItems: 'flex-end' }}>
                    <Mono size={14} color={palette.text} weight="700">
                      {unitPrice.toFixed(2)}
                      <Text style={{ fontSize: 10, color: palette.textFaint }}>
                        {' '}
                        {(m.price_currency ?? 'PLN')}/{m.unit_of_measure?.toUpperCase() ?? 'UNIT'}
                      </Text>
                    </Mono>
                  </View>
                ) : null}
              </View>
            </Card>
          </>
        ) : null}

        {/* Identifiers */}
        <SectionLabel>Identifiers</SectionLabel>
        <Card style={{ paddingVertical: 4 }}>
          <IdRow label="EAN" value={m.ean ?? '—'} mono />
          <IdRow label="EXTERNAL CODE" value={m.external_code ?? '—'} mono />
          <IdRow label="EXTERNAL SYSTEM" value={m.external_system ?? '—'} />
          <IdRow label="MATERIAL TYPE" value={m.material_type?.name ?? '—'} last />
        </Card>
      </ScrollView>
    </View>
  );
}

function IdRow({
  label,
  value,
  mono,
  last,
}: {
  label: string;
  value: string;
  mono?: boolean;
  last?: boolean;
}) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  return (
    <View
      style={[
        styles.idRow,
        last ? null : { borderBottomColor: palette.border, borderBottomWidth: StyleSheet.hairlineWidth },
      ]}>
      <Mono size={10.5} color={palette.textFaint} letterSpacing={0.5}>{label}</Mono>
      <Text
        style={[
          styles.idValue,
          { color: palette.text, fontFamily: mono ? MONO : undefined },
        ]}
        numberOfLines={1}>
        {value}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  scroll: { padding: 16, gap: 14, paddingBottom: 32 },
  heroTitle: { fontSize: 22, fontWeight: '700', letterSpacing: -0.3, marginTop: 4, lineHeight: 26 },
  heroSub: { fontSize: 13, marginTop: 6, lineHeight: 19 },
  stockRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-end' },
  stockBig: { fontSize: 36, fontWeight: '700', letterSpacing: -0.8, lineHeight: 36 },
  statePill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 4,
    paddingHorizontal: 10,
    borderRadius: 999,
  },
  stateDot: { width: 8, height: 8, borderRadius: 4 },
  stockBar: { height: 6, borderRadius: 3, overflow: 'hidden' },
  stockMetaRow: { flexDirection: 'row', justifyContent: 'space-between' },
  supplierRow: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  supplierIcon: {
    width: 44,
    height: 44,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  supplierName: { fontSize: 13, fontWeight: '600' },
  idRow: {
    paddingVertical: 10,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: 12,
  },
  idValue: { fontSize: 12.5, fontWeight: '500', flexShrink: 1, textAlign: 'right' },
});
