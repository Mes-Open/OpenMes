/**
 * Message log — mirrors the web "Live Message Log": a monospace trace of recent
 * machine messages (time · topic · payload), one line per message, with a
 * status filter. Colours track the processing status (ok / error / skipped).
 */
import { format, parseISO } from 'date-fns';
import { useMemo, useState } from 'react';
import { RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts, radius } from '@openmes/ui';

import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useMessages } from '@/hooks/queries/useConnectivity';
import type { MachineMessage } from '@/api/connectivity';

type StatusFilter = 'all' | 'ok' | 'error' | 'skipped';

function statusColor(status: string): string {
  if (status === 'error') return colors.blocked;
  if (status === 'skipped') return colors.downtime;
  return colors.running;
}

export function MessagesList() {
  const { t } = useTranslation();
  const [statusFilter, setStatusFilter] = useState<StatusFilter>('all');
  const query = useMessages(statusFilter === 'all' ? {} : { processing_status: statusFilter });
  const items = query.data?.data ?? [];

  const options = useMemo(
    () => [
      { value: 'all', label: t('All statuses') },
      { value: 'ok', label: t('OK') },
      { value: 'error', label: t('Error') },
      { value: 'skipped', label: t('Skipped') },
    ],
    [t],
  );

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Messages')}</Text>
        <View style={{ flex: 1 }} />
        <View style={{ width: 160 }}>
          <Dropdown value={statusFilter} onChange={(v) => setStatusFilter(v as StatusFilter)} options={options} />
        </View>
      </View>

      {query.isLoading && !query.data ? (
        <LoadingState />
      ) : query.isError && !query.data ? (
        <ErrorState error={query.error} onRetry={query.refetch} />
      ) : (
        <ScrollView
          style={styles.screen}
          contentContainerStyle={styles.tableWrap}
          refreshControl={<RefreshControl refreshing={query.isFetching} onRefresh={query.refetch} tintColor={colors.accent} />}>
          <View style={styles.log}>
            {items.length === 0 ? (
              <Text style={styles.empty}>{t('No messages yet.')}</Text>
            ) : (
              items.map((m) => <TraceLine key={m.id} message={m} />)
            )}
          </View>
        </ScrollView>
      )}
    </View>
  );
}

function TraceLine({ message }: { message: MachineMessage }) {
  const tsColor = statusColor(message.processing_status);
  const payload = (() => {
    if (message.parsed_data) {
      try {
        return JSON.stringify(message.parsed_data);
      } catch {}
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
      <View style={styles.lineRow}>
        <Mono size={10.5} color={tsColor}>{time}</Mono>
        <Mono size={10.5} color={colors.accent} numberOfLines={1} style={{ flexShrink: 0 }}>{message.topic}</Mono>
        <Mono size={10.5} color={colors.muted} numberOfLines={1} style={{ flex: 1 }}>{payload}</Mono>
      </View>
      {message.processing_error ? (
        <Mono size={10} color={colors.blocked} numberOfLines={2} style={{ marginTop: 2 }}>✕ {message.processing_error}</Mono>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingVertical: 16 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  tableWrap: { paddingHorizontal: 14, paddingBottom: 24 },
  log: { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 12 },
  lineWrap: { paddingVertical: 4, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.line2 },
  lineRow: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  empty: { fontSize: 13, color: colors.faint, fontFamily: fonts.sans.native.regular, padding: 12 },
});
