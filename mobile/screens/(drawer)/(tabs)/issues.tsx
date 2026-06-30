import { FontAwesome } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { LegendList } from '@legendapp/list';
import {
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';

import { Mono } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import {
  EmptyState,
  ErrorState,
  LoadingState,
} from '@/components/ui/StateViews';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useDeviceClass } from '@/hooks/useDeviceClass';
import { useAlertCounts, useAlerts } from '@/hooks/queries/useSystem';
import { isSupervisorOrAdmin, useAuthStore } from '@/stores/authStore';
import type { Alert, AlertSeverity, AlertType } from '@/api/system';

type SeverityId = AlertSeverity;

const SEVERITY_ORDER: SeverityId[] = ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'];

const SEVERITY_COLOR: Record<SeverityId, string> = {
  CRITICAL: '#D6442F',
  HIGH: '#f97316',
  MEDIUM: '#EA5A2B',
  LOW: '#EA5A2B',
};

const TYPE_ICON: Record<AlertType, React.ComponentProps<typeof FontAwesome>['name']> = {
  issue: 'flag',
  blocking_issue: 'exclamation-triangle',
  overdue_order: 'clock-o',
  blocked_order: 'ban',
  machine_offline: 'plug',
  maintenance: 'wrench',
};

const TYPE_LABEL: Record<AlertType, string> = {
  issue: 'Issue',
  blocking_issue: 'Blocking',
  overdue_order: 'Overdue WO',
  blocked_order: 'Blocked WO',
  machine_offline: 'Machine offline',
  maintenance: 'Maintenance',
};

// Status values come from the API as upper-snake (PENDING, IN_PROGRESS, …).
// Map to translation keys — the corresponding entries live in lang/{en,pl}.json.
function statusKey(s: string | null | undefined): string {
  return (s ?? '').toUpperCase();
}

function normSeverity(s: string): SeverityId {
  const u = (s ?? '').toUpperCase();
  if (u === 'CRITICAL') return 'CRITICAL';
  if (u === 'HIGH') return 'HIGH';
  if (u === 'LOW') return 'LOW';
  return 'MEDIUM';
}

/**
 * Alerts triage screen — phone and tablet share this entry. Phone: severity
 * tiles + list. Tablet: tiles span top, list on left, selected-alert detail
 * panel on right (matching design ScreenIssueTriage from gaps.jsx).
 */
export function IssuesTab() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  const { useTabletLayout: isTablet } = useDeviceClass();
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [severityFilter, setSeverityFilter] = useState<SeverityId | 'all'>('all');

  const alertsQ = useAlerts('all');
  const countsQ = useAlertCounts();
  const all: Alert[] = alertsQ.data ?? [];

  const user = useAuthStore((s) => s.user);
  const canEscalate = isSupervisorOrAdmin(user);

  // Per-severity counts derived from the alert list (the /counts endpoint
  // only returns per-type totals, not per-severity).
  const sevCounts = useMemo(() => {
    const c: Record<SeverityId, number> = {
      CRITICAL: 0,
      HIGH: 0,
      MEDIUM: 0,
      LOW: 0,
    };
    for (const a of all) c[normSeverity(a.severity)] += 1;
    return c;
  }, [all]);

  const filtered = useMemo(() => {
    if (severityFilter === 'all') return all;
    return all.filter((a) => normSeverity(a.severity) === severityFilter);
  }, [all, severityFilter]);

  const selected = useMemo(() => {
    if (!filtered.length) return null;
    const explicit = filtered.find((a) => `${a.type}-${a.id}` === selectedId);
    return explicit ?? filtered[0];
  }, [filtered, selectedId]);

  const totalOpen = countsQ.data?.total ?? all.length;
  const criticalCount = sevCounts.CRITICAL;

  if (alertsQ.isLoading) {
    return (
      <View style={{ flex: 1, backgroundColor: palette.background }}>
        <ScreenHeader title="Alerts" />
        <LoadingState />
      </View>
    );
  }
  if (alertsQ.isError) {
    return (
      <View style={{ flex: 1, backgroundColor: palette.background }}>
        <ScreenHeader title="Alerts" />
        <ErrorState error={alertsQ.error} onRetry={alertsQ.refetch} />
      </View>
    );
  }

  const headerRight = (
    <View style={{ flexDirection: 'row', gap: 8 }}>
      <Pressable
        onPress={() => router.push('/issues/new' as never)}
        style={({ pressed }) => [
          styles.ghostBtn,
          { borderColor: palette.border, opacity: pressed ? 0.7 : 1 },
        ]}>
        <Mono size={11} color={palette.text} weight="600" letterSpacing={0.5}>
          {t('All issues').toUpperCase()}
        </Mono>
      </Pressable>
      {canEscalate ? (
        <Pressable
          style={({ pressed }) => [
            styles.solidBtn,
            { backgroundColor: palette.text, opacity: pressed ? 0.85 : 1 },
          ]}>
          <Mono
            size={11}
            color={palette.background}
            weight="700"
            letterSpacing={0.5}>
            {t('Escalate').toUpperCase()}
          </Mono>
        </Pressable>
      ) : null}
    </View>
  );

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader
        title={t('Issue triage')}
        subtitle={`ANDON · ${totalOpen} ${t('OPEN').toUpperCase()} · ${criticalCount} ${t('CRITICAL').toUpperCase()}`}
        subtitleColor={totalOpen > 0 ? palette.danger : undefined}
        rightSlot={isTablet ? headerRight : undefined}
      />

      {/* Severity tiles — tap to filter, tap again to clear back to ALL. */}
      <View style={styles.tilesRow}>
        <Pressable
          onPress={() => setSeverityFilter('all')}
          style={({ pressed }) => [
            styles.tile,
            {
              backgroundColor: severityFilter === 'all' ? palette.text : palette.surface,
              borderColor: severityFilter === 'all' ? palette.text : palette.border,
              opacity: pressed ? 0.85 : 1,
            },
          ]}>
          <View style={{ flex: 1, minWidth: 0 }}>
            <Mono
              size={9.5}
              color={severityFilter === 'all' ? palette.background : palette.textFaint}
              letterSpacing={0.5}>
              {t('ALL').toUpperCase()}
            </Mono>
          </View>
          <Text
            style={[
              styles.tileNum,
              {
                color: severityFilter === 'all' ? palette.background : palette.text,
                fontFamily: MONO,
              },
            ]}>
            {all.length}
          </Text>
        </Pressable>
        {SEVERITY_ORDER.map((sev) => {
          const on = severityFilter === sev;
          const color = SEVERITY_COLOR[sev];
          return (
            <Pressable
              key={sev}
              onPress={() =>
                setSeverityFilter((cur) => (cur === sev ? 'all' : sev))
              }
              style={({ pressed }) => [
                styles.tile,
                {
                  backgroundColor: on ? `${color}11` : palette.surface,
                  borderColor: on ? color : palette.border,
                  borderLeftColor: color,
                  borderLeftWidth: 3,
                  opacity: pressed ? 0.85 : 1,
                },
              ]}>
              <View style={{ flex: 1, minWidth: 0 }}>
                <Mono size={9.5} color={palette.textFaint} letterSpacing={0.5}>
                  {sev}
                </Mono>
              </View>
              <Text
                style={[
                  styles.tileNum,
                  { color, fontFamily: MONO },
                ]}>
                {sevCounts[sev]}
              </Text>
            </Pressable>
          );
        })}
      </View>

      {/* Body — split on tablet, single column on phone */}
      <View style={[styles.body, isTablet ? styles.bodyTablet : null]}>
        <View
          style={[
            isTablet
              ? { flex: 1, marginRight: 12, borderRadius: 14, overflow: 'hidden', backgroundColor: palette.surface, borderWidth: 1, borderColor: palette.border }
              : { flex: 1 },
          ]}>
          {filtered.length === 0 ? (
            <EmptyState
              title={t('No alerts')}
              subtitle={t('Nothing to triage right now.')}
            />
          ) : (
            <LegendList
              data={filtered}
              keyExtractor={(a) => `${a.type}-${a.id}`}
              contentContainerStyle={isTablet ? styles.listTablet : styles.list}
              ItemSeparatorComponent={() =>
                isTablet ? (
                  <View
                    style={{
                      height: StyleSheet.hairlineWidth,
                      backgroundColor: palette.border,
                    }}
                  />
                ) : (
                  <View style={{ height: 10 }} />
                )
              }
              renderItem={({ item }) => (
                <AlertRow
                  alert={item}
                  selected={
                    isTablet && selected
                      ? selected.type === item.type && selected.id === item.id
                      : false
                  }
                  palette={palette}
                  isTablet={isTablet}
                  onPress={() => {
                    if (isTablet) {
                      setSelectedId(`${item.type}-${item.id}`);
                    } else {
                      router.push(item.link as never);
                    }
                  }}
                />
              )}
              refreshControl={
                <RefreshControl
                  refreshing={alertsQ.isFetching}
                  onRefresh={alertsQ.refetch}
                />
              }
            />
          )}
        </View>

        {isTablet ? (
          <View
            style={[
              styles.detailPane,
              { backgroundColor: palette.surface, borderColor: palette.border },
            ]}>
            {selected ? (
              <AlertDetail
                alert={selected}
                palette={palette}
                onOpen={() => router.push(selected.link as never)}
              />
            ) : (
              <Mono
                size={11}
                color={palette.textFaint}
                style={{ textAlign: 'center', padding: 24 }}>
                {t('Pick an alert to view details').toUpperCase()}
              </Mono>
            )}
          </View>
        ) : null}
      </View>
    </View>
  );
}

function AlertRow({
  alert,
  selected,
  palette,
  isTablet,
  onPress,
}: {
  alert: Alert;
  selected: boolean;
  palette: typeof Colors.light;
  isTablet: boolean;
  onPress: () => void;
}) {
  const { t } = useTranslation();
  const sev = normSeverity(alert.severity);
  const color = SEVERITY_COLOR[sev];
  const icon = TYPE_ICON[alert.type as AlertType] ?? 'bell';
  const typeLabel = TYPE_LABEL[alert.type as AlertType] ?? alert.type;
  const age = relTime(alert.created_at);

  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [
        isTablet ? styles.rowTablet : styles.rowPhone,
        {
          backgroundColor: selected
            ? `${color}11`
            : isTablet
              ? 'transparent'
              : palette.surface,
          borderColor: isTablet ? 'transparent' : palette.border,
          borderLeftColor: color,
          opacity: pressed ? 0.9 : 1,
        },
      ]}>
      <View
        style={[
          styles.iconBadge,
          { backgroundColor: `${color}22` },
        ]}>
        <FontAwesome name={icon} size={14} color={color} />
      </View>
      <View style={{ flex: 1, minWidth: 0 }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
          <View
            style={[styles.sevPill, { backgroundColor: `${color}22` }]}>
            <Mono size={9} color={color} weight="700" letterSpacing={0.5}>
              {sev}
            </Mono>
          </View>
          <Mono size={9.5} color={palette.textFaint} letterSpacing={0.5}>
            {typeLabel.toUpperCase()}
          </Mono>
        </View>
        <Text
          style={[styles.rowTitle, { color: palette.text }]}
          numberOfLines={1}>
          {alert.title}
        </Text>
      </View>
      <View style={{ alignItems: 'flex-end' }}>
        <Mono size={10.5} color={palette.textFaint}>
          {age}
        </Mono>
        <View
          style={[
            styles.statusPill,
            { backgroundColor: palette.surfaceAlt, marginTop: 4 },
          ]}>
          <Mono
            size={9}
            color={palette.textMuted}
            weight="700"
            letterSpacing={0.5}>
            {t(statusKey(alert.status)).toUpperCase()}
          </Mono>
        </View>
      </View>
    </Pressable>
  );
}

function AlertDetail({
  alert,
  palette,
  onOpen,
}: {
  alert: Alert;
  palette: typeof Colors.light;
  onOpen: () => void;
}) {
  const { t } = useTranslation();
  const sev = normSeverity(alert.severity);
  const color = SEVERITY_COLOR[sev];
  const icon = TYPE_ICON[alert.type as AlertType] ?? 'bell';
  const typeLabel = TYPE_LABEL[alert.type as AlertType] ?? alert.type;

  return (
    <ScrollView contentContainerStyle={{ padding: 18, gap: 14 }}>
      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 10 }}>
        <View style={[styles.sevPill, { backgroundColor: `${color}22` }]}>
          <Mono size={9.5} color={color} weight="700" letterSpacing={0.5}>
            {sev}
          </Mono>
        </View>
        <Mono size={10.5} color={palette.textFaint} letterSpacing={0.5}>
          {typeLabel.toUpperCase()}
        </Mono>
      </View>
      <Text style={[styles.detailTitle, { color: palette.text }]}>
        {alert.title}
      </Text>

      <View
        style={[
          styles.impact,
          { backgroundColor: `${color}11`, borderColor: color },
        ]}>
        <FontAwesome name={icon} size={20} color={color} />
        <View style={{ flex: 1, marginLeft: 10 }}>
          <Mono size={10.5} color={color} weight="700" letterSpacing={0.5}>
            {t('IMPACT').toUpperCase()}
          </Mono>
          <Mono size={11} color={palette.text} style={{ marginTop: 4 }}>
            {t(statusKey(alert.status)).toUpperCase()}
            {alert.created_at ? ` · ${t('SINCE').toUpperCase()} ${relTime(alert.created_at)}` : ''}
          </Mono>
        </View>
      </View>

      <View style={{ flexDirection: 'row', gap: 8 }}>
        <Pressable
          style={({ pressed }) => [
            styles.detailAction,
            {
              backgroundColor: palette.surface,
              borderColor: palette.border,
              borderWidth: 1,
              opacity: pressed ? 0.85 : 1,
            },
          ]}>
          <Mono size={11} color={palette.text} weight="700" letterSpacing={0.5}>
            {t('Snooze').toUpperCase()}
          </Mono>
        </Pressable>
        <Pressable
          onPress={onOpen}
          style={({ pressed }) => [
            styles.detailAction,
            {
              backgroundColor: color,
              opacity: pressed ? 0.9 : 1,
            },
          ]}>
          <FontAwesome name="exclamation-triangle" size={12} color="#fff" />
          <Mono size={11} color="#fff" weight="700" letterSpacing={0.5}>
            {t('Dispatch now').toUpperCase()}
          </Mono>
        </Pressable>
      </View>
    </ScrollView>
  );
}

function relTime(iso?: string | null): string {
  if (!iso) return '';
  try {
    const ms = Date.now() - new Date(iso).getTime();
    const min = Math.max(0, Math.floor(ms / 60000));
    if (min < 60) return `${min}m`;
    const h = Math.floor(min / 60);
    if (h < 24) return `${h}h`;
    const d = Math.floor(h / 24);
    return `${d}d`;
  } catch {
    return '';
  }
}

const styles = StyleSheet.create({
  tilesRow: {
    flexDirection: 'row',
    gap: 8,
    paddingHorizontal: 18,
    paddingTop: 12,
    paddingBottom: 8,
  },
  tile: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    padding: 12,
    borderRadius: 12,
    borderWidth: 1,
    gap: 8,
  },
  tileNum: { fontSize: 20, fontWeight: '700', letterSpacing: -0.4 },

  ghostBtn: {
    height: 32,
    paddingHorizontal: 12,
    borderRadius: 8,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  solidBtn: {
    height: 32,
    paddingHorizontal: 12,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },

  body: { flex: 1, padding: 18, paddingTop: 6 },
  bodyTablet: { flexDirection: 'row' },

  list: { padding: 0, paddingTop: 10 },
  listTablet: { padding: 0 },

  rowPhone: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 12,
    borderRadius: 12,
    borderWidth: 1,
    borderLeftWidth: 3,
  },
  rowTablet: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingVertical: 12,
    paddingHorizontal: 14,
    borderLeftWidth: 3,
  },

  iconBadge: {
    width: 36,
    height: 36,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  rowTitle: { fontSize: 13, fontWeight: '600', marginTop: 4 },
  sevPill: { paddingVertical: 2, paddingHorizontal: 6, borderRadius: 3 },
  statusPill: { paddingVertical: 2, paddingHorizontal: 5, borderRadius: 3 },

  detailPane: {
    width: 380,
    borderRadius: 14,
    borderWidth: 1,
    overflow: 'hidden',
  },
  detailTitle: { fontSize: 20, fontWeight: '700', letterSpacing: -0.3 },
  impact: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 12,
    borderRadius: 12,
    borderWidth: 1,
  },
  detailAction: {
    flex: 1,
    height: 44,
    borderRadius: 10,
    flexDirection: 'row',
    gap: 6,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
