import { format, parseISO } from 'date-fns';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { StyleSheet, Text, View } from 'react-native';

import { Card } from '@/components/ui/Card';
import { ListScreen } from '@/components/ui/ListScreen';
import { Mono } from '@/components/ui/Mono';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useAuditLogs } from '@/hooks/queries/useAuditLogs';
import type { AuditAction, AuditLog } from '@/api/auditLogs';

type FilterId = 'all' | AuditAction;

const ACTION_COLOR: Record<AuditAction, string> = {
  created: '#1C9A55',
  updated: BRAND.amber,
  deleted: '#D6442F',
};

/**
 * Admin activity log viewer — surfaces the audit log feed in the same visual
 * pattern as the request_logs viewer in the design. Each entry shows a
 * colored action pill (CREATED/UPDATED/DELETED), entity type, entity id, and
 * actor + timestamp.
 *
 * NOTE: backend `request_logs` (HTTP traffic) is web-only today. When the V1
 * API for request logs ships, we can extend this screen with a method/status
 * pill layout matching the design exactly. Until then, audit logs are the
 * closest equivalent in scope.
 */
export function ActivityLogsScreen() {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  const [filter, setFilter] = useState<FilterId>('all');

  const query = useAuditLogs({ per_page: 100 });
  const all: AuditLog[] = query.data?.data ?? [];

  const counts = useMemo(() => {
    const c = { all: all.length, created: 0, updated: 0, deleted: 0 };
    for (const l of all) {
      if (l.action in c) c[l.action] += 1;
    }
    return c;
  }, [all]);

  const filtered = useMemo(() => {
    if (filter === 'all') return all;
    return all.filter((l) => l.action === filter);
  }, [all, filter]);

  return (
    <ListScreen
      title={t('Activity logs')}
      eyebrow={`${t('ADMIN').toUpperCase()} · ${all.length} ${t('EVENTS').toUpperCase()} · ${t('LIVE').toUpperCase()}`}
      filters={[
        { id: 'all', label: t('All'), count: counts.all },
        { id: 'created', label: t('Created'), count: counts.created },
        { id: 'updated', label: t('Updated'), count: counts.updated },
        { id: 'deleted', label: t('Deleted'), count: counts.deleted },
      ]}
      activeFilter={filter}
      onFilterChange={(id) => setFilter(id as FilterId)}
      extraHeader={
        <View style={styles.liveRow}>
          <View style={[styles.liveDot, { backgroundColor: palette.success }]} />
          <Mono size={10.5} color={palette.success} weight="700" letterSpacing={0.5}>
            {t('LIVE').toUpperCase()}
          </Mono>
          <Mono size={10.5} color={palette.textFaint} letterSpacing={0.4}>
            · {all.length} {t('EVENTS').toUpperCase()} / 24H
          </Mono>
        </View>
      }
      items={filtered}
      keyExtractor={(l) => String(l.id)}
      isLoading={query.isLoading}
      isError={query.isError}
      error={query.error}
      isFetching={query.isFetching}
      onRefresh={query.refetch}
      emptyTitle={t('No activity yet')}
      renderItem={(log) => {
        const color = ACTION_COLOR[log.action] ?? palette.textMuted;
        const ts = (() => {
          try {
            return format(parseISO(log.created_at), 'HH:mm:ss');
          } catch {
            return '';
          }
        })();
        return (
          <Card>
            <View style={styles.row}>
              <Mono
                size={10}
                color={palette.textFaint}
                style={{ width: 60, flexShrink: 0 }}>
                {ts}
              </Mono>
              <View
                style={[
                  styles.actionPill,
                  { backgroundColor: `${color}22` },
                ]}>
                <Mono size={9.5} color={color} weight="700" letterSpacing={0.4}>
                  {log.action.toUpperCase().slice(0, 4)}
                </Mono>
              </View>
              <View style={{ flex: 1, minWidth: 0 }}>
                <Mono
                  size={10.5}
                  color={palette.text}
                  numberOfLines={1}
                  weight="600">
                  {log.entity_type}#{log.entity_id}
                </Mono>
                {log.entity_name ? (
                  <Text
                    style={[styles.entityName, { color: palette.textMuted }]}
                    numberOfLines={1}>
                    {log.entity_name}
                  </Text>
                ) : null}
              </View>
              <Mono
                size={10}
                color={palette.textFaint}
                style={{ width: 80, textAlign: 'right' }}
                numberOfLines={1}>
                {(log.user?.username ?? 'system').toUpperCase()}
              </Mono>
            </View>
          </Card>
        );
      }}
    />
  );
}

const styles = StyleSheet.create({
  liveRow: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  liveDot: { width: 8, height: 8, borderRadius: 4 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  actionPill: {
    paddingVertical: 1,
    paddingHorizontal: 5,
    borderRadius: 3,
    width: 50,
    alignItems: 'center',
    flexShrink: 0,
  },
  entityName: { fontSize: 11, marginTop: 2, fontFamily: MONO },
});
