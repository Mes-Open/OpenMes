import { format, parseISO } from 'date-fns';
import { useMemo, useState } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';

import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { Mono } from '@/components/ui/Mono';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useMessages } from '@/hooks/queries/useConnectivity';
import type { MachineMessage } from '@/api/connectivity';

const DARK = Colors.dark;

type StatusFilter = 'all' | 'ok' | 'error' | 'skipped';

export function MessagesList() {
  const [statusFilter, setStatusFilter] = useState<StatusFilter>('all');
  const query = useMessages(
    statusFilter === 'all' ? {} : { processing_status: statusFilter },
  );
  const items = query.data?.data ?? [];

  const counts = useMemo(() => {
    const all = query.data?.data ?? [];
    return {
      all: all.length,
      ok: all.filter((m) => m.processing_status === 'ok').length,
      error: all.filter((m) => m.processing_status === 'error').length,
      skipped: all.filter((m) => m.processing_status === 'skipped').length,
    };
  }, [query.data?.data]);

  if (query.isLoading) return <LoadingState />;
  if (query.isError) return <ErrorState error={query.error} onRetry={query.refetch} />;

  return (
    <View style={{ flex: 1, backgroundColor: DARK.background }}>
      <ScreenHeader
        variant="dark"
        title="Live trace"
        subtitle={`MQTT MESSAGES · ${items.length} RECENT`}
      />

      <ScrollView
        contentContainerStyle={styles.scroll}
        refreshControl={
          <RefreshControl
            refreshing={query.isFetching}
            onRefresh={query.refetch}
            tintColor={DARK.text}
          />
        }>
        <View style={styles.chipsRow}>
          {(
            [
              { id: 'all', label: 'All', n: counts.all },
              { id: 'ok', label: 'OK', n: counts.ok },
              { id: 'error', label: 'Error', n: counts.error },
              { id: 'skipped', label: 'Skipped', n: counts.skipped },
            ] as { id: StatusFilter; label: string; n: number }[]
          ).map((c) => {
            const active = c.id === statusFilter;
            return (
              <Pressable
                key={c.id}
                onPress={() => setStatusFilter(c.id)}
                style={[
                  styles.chip,
                  {
                    backgroundColor: active ? '#241a08' : 'transparent',
                    borderColor: active ? BRAND.amber : DARK.border,
                  },
                ]}>
                <Mono
                  size={11}
                  color={active ? BRAND.amber : DARK.textMuted}
                  weight="600"
                  letterSpacing={0.4}>
                  {c.label.toUpperCase()}
                </Mono>
                <Mono size={10} color={active ? BRAND.amber : DARK.textFaint}>
                  {c.n}
                </Mono>
              </Pressable>
            );
          })}
        </View>

        <View style={styles.traceBlock}>
          {items.length === 0 ? (
            <Mono size={11} color={DARK.textFaint} style={{ textAlign: 'center', padding: 16 }}>
              NO MESSAGES YET — POLLING EVERY 15s
            </Mono>
          ) : (
            items.map((m) => <TraceLine key={m.id} message={m} />)
          )}
          <Text style={[styles.dots, { color: DARK.textFaint }]}>…</Text>
        </View>
      </ScrollView>
    </View>
  );
}

function TraceLine({ message }: { message: MachineMessage }) {
  // Map status → timestamp color (green / red / amber).
  const tsColor =
    message.processing_status === 'error'
      ? '#ff6b6b'
      : message.processing_status === 'skipped'
      ? BRAND.amber
      : '#1C9A55';

  // Compact payload display: parsed JSON if present, else raw.
  const payload = (() => {
    if (message.parsed_data) {
      try {
        return JSON.stringify(message.parsed_data);
      } catch {
        // Fall through to raw.
      }
    }
    return (message.raw_payload ?? '').slice(0, 200);
  })();

  const time = (() => {
    try {
      return format(parseISO(message.received_at), 'HH:mm:ss');
    } catch {
      return '--:--:--';
    }
  })();

  return (
    <View style={styles.lineWrap}>
      <Text style={styles.line} numberOfLines={2}>
        <Text style={{ color: tsColor }}>{time}</Text>{' '}
        <Text style={{ color: BRAND.amber }}>{message.topic}</Text>{' '}
        <Text style={{ color: '#1A1917' }}>{payload}</Text>
      </Text>
      {message.processing_error ? (
        <Text style={[styles.errLine]} numberOfLines={2}>
          ✕ {message.processing_error}
        </Text>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  scroll: { padding: 16, gap: 14, paddingBottom: 32 },
  chipsRow: { flexDirection: 'row', gap: 6, flexWrap: 'wrap' },
  chip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 7,
    paddingHorizontal: 12,
    borderRadius: 999,
    borderWidth: 1,
  },
  traceBlock: {
    backgroundColor: '#F6F5F1',
    borderRadius: 12,
    padding: 14,
    minHeight: 200,
  },
  lineWrap: { paddingVertical: 4 },
  line: {
    fontFamily: MONO,
    fontSize: 11,
    lineHeight: 18,
    letterSpacing: 0.2,
  },
  errLine: {
    fontFamily: MONO,
    fontSize: 10.5,
    color: '#ff6b6b',
    marginTop: 2,
    paddingLeft: 12,
  },
  dots: {
    fontFamily: MONO,
    fontSize: 11,
    marginTop: 6,
  },
});
