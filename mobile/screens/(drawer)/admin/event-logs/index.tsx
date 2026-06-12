import { format, parseISO } from 'date-fns';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { StyleSheet, Text, View } from 'react-native';

import { Card } from '@/components/ui/Card';
import { ListScreen } from '@/components/ui/ListScreen';
import { Mono } from '@/components/ui/Mono';
import { SearchBar } from '@/components/ui/SearchBar';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useEventLogs } from '@/hooks/queries/useEventLogs';

type FilterId = 'all' | 'work_orders' | 'mqtt' | 'auth' | 'system';

function categorize(eventType: string): FilterId | 'unknown' {
  const t = eventType.toUpperCase();
  if (t.startsWith('WO_') || t.startsWith('BATCH_') || t.startsWith('WORK_ORDER')) return 'work_orders';
  if (t.startsWith('MQTT_') || t.startsWith('CONNECTION_') || t === 'NEXO_SYNC') return 'mqtt';
  if (t.startsWith('USER_') || t.startsWith('AUTH_') || t === 'LOGIN' || t === 'LOGOUT') return 'auth';
  if (t.includes('DOWNTIME') || t.includes('ISSUE') || t.includes('SYSTEM')) return 'system';
  return 'unknown';
}

function categoryColor(cat: ReturnType<typeof categorize>): string {
  if (cat === 'work_orders') return BRAND.amber;
  if (cat === 'mqtt') return '#EA5A2B';
  if (cat === 'auth') return '#1C9A55';
  if (cat === 'system') return '#7c3aed';
  return '#9B9892';
}

export function EventLogsScreen() {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  const [search, setSearch] = useState('');
  const [filter, setFilter] = useState<FilterId>('all');

  // Fetch a single bucket of events; client-side categorize + filter. The
  // backend doesn't expose a "category" filter, only event_type which is too
  // fine-grained for the design's chips.
  const query = useEventLogs({ per_page: 50 });
  const all = query.data?.data ?? [];

  const counts = useMemo(() => {
    const c = { all: all.length, work_orders: 0, mqtt: 0, auth: 0, system: 0 };
    for (const e of all) {
      const cat = categorize(e.event_type);
      if (cat !== 'unknown') c[cat]++;
    }
    return c;
  }, [all]);

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    return all.filter((e) => {
      if (filter !== 'all' && categorize(e.event_type) !== filter) return false;
      if (q) {
        const blob = `${e.event_type} ${e.entity_type} ${e.description ?? ''} ${e.user?.username ?? ''}`.toLowerCase();
        if (!blob.includes(q)) return false;
      }
      return true;
    });
  }, [all, filter, search]);

  return (
    <ListScreen
      title={t('Event logs')}
      eyebrow={`${t('ADMIN').toUpperCase()} · ${t('SYSTEM EVENTS').toUpperCase()} · ${t('LIVE').toUpperCase()}`}
      filters={[
        { id: 'all', label: t('All'), count: counts.all },
        { id: 'work_orders', label: t('Work orders'), count: counts.work_orders },
        { id: 'mqtt', label: 'MQTT', count: counts.mqtt },
        { id: 'auth', label: t('Auth'), count: counts.auth },
        { id: 'system', label: t('System'), count: counts.system },
      ]}
      activeFilter={filter}
      onFilterChange={(id) => setFilter(id as FilterId)}
      extraHeader={
        <View style={{ gap: 10 }}>
          <View style={[styles.liveRow]}>
            <View style={[styles.liveDot, { backgroundColor: palette.success }]} />
            <Mono size={10.5} color={palette.success} letterSpacing={0.5} weight="700">
              {t('LIVE').toUpperCase()}
            </Mono>
            <Mono size={10.5} color={palette.textFaint} letterSpacing={0.4}>
              · {all.length} {t('EVENTS').toUpperCase()}
            </Mono>
          </View>
          <SearchBar
            placeholder="Filter by event type or entity"
            value={search}
            onChangeText={setSearch}
          />
        </View>
      }
      items={filtered}
      keyExtractor={(l) => String(l.id)}
      isLoading={query.isLoading}
      isError={query.isError}
      error={query.error}
      isFetching={query.isFetching}
      onRefresh={query.refetch}
      emptyTitle={t('No events match these filters')}
      renderItem={(item) => {
        const cat = categorize(item.event_type);
        const color = categoryColor(cat);
        return (
          <Card>
            <View style={styles.eventRow}>
              <View style={[styles.eventDot, { backgroundColor: color }]} />
              <View style={{ flex: 1, minWidth: 0 }}>
                <Mono size={10.5} color={color} weight="700" letterSpacing={0.5}>
                  {item.event_type.toUpperCase()}
                </Mono>
                <Text style={[styles.eventDesc, { color: palette.text }]} numberOfLines={2}>
                  {item.description || `${item.entity_type} #${item.entity_id}`}
                </Text>
                <Mono size={10} color={palette.textFaint} letterSpacing={0.3} style={{ marginTop: 4 }}>
                  {(item.user?.username ?? 'system').toUpperCase()}
                </Mono>
              </View>
              <Mono size={10.5} color={palette.textFaint} letterSpacing={0.4}>
                {format(parseISO(item.created_at), 'HH:mm:ss')}
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
  eventRow: { flexDirection: 'row', alignItems: 'flex-start', gap: 12 },
  eventDot: { width: 8, height: 8, borderRadius: 4, marginTop: 6, flexShrink: 0 },
  eventDesc: { fontSize: 12, marginTop: 4 },
});
