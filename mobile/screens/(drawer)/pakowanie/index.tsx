/**
 * Packaging overview — mirrors the web packaging Admin page: a four-up stat row
 * (shift packed / plan / backlog / progress) above the "orders to pack" table
 * (Order / Product / Qty / Progress / Status). Supervisors get a "Manage EANs"
 * shortcut. Data via REST usePackagingItems + usePackagingStats.
 */
import { useRouter } from 'expo-router';
import { RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import * as WebBrowser from 'expo-web-browser';

import { StatusPill, colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { usePackagingItems, usePackagingStats } from '@/hooks/queries/usePackaging';
import { isSupervisorOrAdmin, useAuthStore } from '@/stores/authStore';
import { useSettingsStore } from '@/stores/settingsStore';
import type { PackagingItem } from '@/api/packaging';

export function PakowanieDashboard() {
  const { t } = useTranslation();
  const router = useRouter();

  const itemsQuery = usePackagingItems();
  const statsQuery = usePackagingStats();

  const user = useAuthStore((s) => s.user);
  const canManage = isSupervisorOrAdmin(user);
  const serverUrl = useSettingsStore((s) => s.serverUrl);

  const stats = statsQuery.data;
  const rows = itemsQuery.data ?? [];
  const plan = stats?.plan ?? 0;
  const totalPacked = stats?.total_packed ?? 0;
  const realizacja = plan > 0 ? Math.min(100, Math.round((totalPacked / plan) * 100)) : 0;

  // Progress banding, matching the web Admin page (>=100 running, >=50 downtime, else blocked).
  const realizacjaColor =
    realizacja >= 100 ? colors.running : realizacja >= 50 ? colors.downtime : colors.blocked;

  // Current shift window — mirrors the web's 06:00–18:00 / 18:00–06:00 derivation.
  const hour = new Date().getHours();
  const shiftLabel = hour >= 6 && hour < 18 ? '06:00 – 18:00' : '18:00 – 06:00';

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <View style={{ flex: 1, minWidth: 0 }}>
          <Text style={styles.h1}>{t('Packaging')}</Text>
          <Mono size={10} color={colors.faint} style={{ marginTop: 3 }}>
            {t('Current shift')}: {shiftLabel}
          </Mono>
        </View>
        <Button
          title={t('Open station on web')}
          size="sm"
          variant="secondary"
          onPress={() => WebBrowser.openBrowserAsync(`${serverUrl}/packaging/station`)}
        />
        {canManage ? (
          <Button title={t('Manage EANs')} size="sm" onPress={() => router.push('/(drawer)/pakowanie/eans' as never)} />
        ) : null}
      </View>

      {itemsQuery.isLoading && !itemsQuery.data ? (
        <LoadingState />
      ) : itemsQuery.isError && !itemsQuery.data ? (
        <ErrorState error={itemsQuery.error} onRetry={itemsQuery.refetch} />
      ) : (
        <ScrollView
          style={styles.screen}
          contentContainerStyle={styles.tableWrap}
          refreshControl={
            <RefreshControl
              refreshing={itemsQuery.isFetching || statsQuery.isFetching}
              onRefresh={() => {
                itemsQuery.refetch();
                statsQuery.refetch();
              }}
              tintColor={colors.accent}
            />
          }>
          {stats ? (
            <View style={styles.statsGrid}>
              <Stat label={t('Packed (shift)')} value={stats.today_packed} />
              <Stat label={t('Plan')} value={plan} muted />
              <Stat label={t('Backlog')} value={stats.backlog} tint={stats.backlog > 0 ? colors.blocked : colors.running} />
              <Stat label={t('Progress')} value={realizacja} suffix="%" bar={realizacja} tint={realizacjaColor} barColor={realizacjaColor} />
            </View>
          ) : null}

          <Mono size={9} color={colors.faint} letterSpacing={0.6} style={{ marginTop: 6, marginBottom: 4 }}>
            {t('Orders to pack').toUpperCase()} · {rows.length}
          </Mono>

          <View style={[styles.row, styles.headerRow]}>
            <HCell flex={1.1}>{t('Order')}</HCell>
            <HCell flex={1.4}>{t('Product')}</HCell>
            <HCell w={72}>{t('Qty')}</HCell>
            <HCell w={72}>{t('Progress')}</HCell>
            <HCell w={96}>{t('Status')}</HCell>
          </View>
          {rows.map((item) => (
            <ItemRow key={item.id} item={item} />
          ))}
          {rows.length === 0 ? <Text style={styles.empty}>{t('No work orders to pack.')}</Text> : null}
        </ScrollView>
      )}
    </View>
  );
}

function ItemRow({ item }: { item: PackagingItem }) {
  const { t } = useTranslation();
  const fill = Math.min(100, Math.max(0, item.progress));
  // Three-band bar, matching the web ProgressBar: done→running, >=50→downtime, else accent.
  const barColor = item.done ? colors.running : item.progress >= 50 ? colors.downtime : colors.accent;
  const status = item.done
    ? { key: 'done' as const, label: t('Packed') }
    : item.status === 'DONE'
      ? { key: 'running' as const, label: t('In progress') }
      : { key: 'pending' as const, label: item.status };
  return (
    <View style={[styles.row, styles.dataRow]}>
      <View style={{ flex: 1.1 }}>
        <Mono size={11} color={colors.ink}>{item.order_no}</Mono>
      </View>
      <View style={{ flex: 1.4 }}>
        <Text numberOfLines={1} style={styles.cellText}>{item.product ?? '—'}</Text>
        {item.line ? <Mono size={9} color={colors.faint}>{item.line.toUpperCase()}</Mono> : null}
        {item.eans?.length ? (
          <View style={styles.eanWrap}>
            {item.eans.map((ean) => (
              <Text key={ean} style={styles.eanChip}>{ean}</Text>
            ))}
          </View>
        ) : null}
      </View>
      <View style={{ width: 72 }}>
        <Mono size={11} color={colors.muted}>{item.packed_qty}/{item.planned_qty}</Mono>
      </View>
      <View style={{ width: 72, gap: 3 }}>
        <Mono size={10} color={colors.muted}>{item.progress}%</Mono>
        <View style={styles.bar}>
          <View style={[styles.barFill, { width: `${fill}%`, backgroundColor: barColor }]} />
        </View>
      </View>
      <View style={{ width: 96 }}>
        <StatusPill status={status.key} label={String(status.label).toUpperCase()} />
      </View>
    </View>
  );
}

function Stat({ label, value, muted, tint, suffix, bar, barColor }: { label: string; value: number; muted?: boolean; tint?: string; suffix?: string; bar?: number; barColor?: string }) {
  return (
    <View style={styles.kpi}>
      <Text style={[styles.kpiValue, { color: tint ?? (muted ? colors.muted : colors.ink) }]}>{value}{suffix ?? ''}</Text>
      <Mono size={9} color={colors.faint} letterSpacing={0.6}>{label.toUpperCase()}</Mono>
      {bar != null ? (
        <View style={[styles.bar, { marginTop: 6 }]}>
          <View style={[styles.barFill, { width: `${Math.min(100, Math.max(0, bar))}%`, backgroundColor: barColor ?? colors.accent }]} />
        </View>
      ) : null}
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
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingVertical: 16 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  tableWrap: { paddingHorizontal: 14, paddingBottom: 24 },
  statsGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 8 },
  kpi: { flexBasis: '47%', flexGrow: 1, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 12, gap: 4 },
  kpiValue: { fontSize: 26, fontFamily: fonts.mono.native.semibold, letterSpacing: -0.5 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingHorizontal: 6 },
  headerRow: { paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: colors.line },
  dataRow: { paddingVertical: 12, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.line2 },
  cellText: { fontSize: 12.5, color: colors.muted, fontFamily: fonts.sans.native.regular },
  eanWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 4, marginTop: 4 },
  eanChip: {
    fontFamily: fonts.mono.native.regular,
    fontSize: 9,
    color: colors.muted,
    backgroundColor: colors.chip,
    paddingHorizontal: 5,
    paddingVertical: 1,
    borderRadius: 4,
    overflow: 'hidden',
  },
  bar: { height: 4, borderRadius: 2, backgroundColor: colors.line2, overflow: 'hidden' },
  barFill: { height: 4, borderRadius: 2 },
  empty: { fontSize: 13, color: colors.faint, fontFamily: fonts.sans.native.regular, padding: 16 },
});
