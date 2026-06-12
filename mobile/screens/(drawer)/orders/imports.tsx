import { FontAwesome } from '@expo/vector-icons';
import { format, formatDistanceToNowStrict, isValid, parseISO } from 'date-fns';
import * as WebBrowser from 'expo-web-browser';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { Card } from '@/components/ui/Card';
import { ListScreen } from '@/components/ui/ListScreen';
import { Mono } from '@/components/ui/Mono';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useCsvImports } from '@/hooks/queries/useCsvImports';
import { useSettingsStore } from '@/stores/settingsStore';
import type { CsvImport } from '@/api/csvImports';

type StatusKey = 'processing' | 'done' | 'failed' | 'pending';

const STATUS_MAP: Record<
  StatusKey,
  { label: string; color: string; bg: string }
> = {
  processing: { label: 'PROCESSING', color: '#8a5a0e', bg: '#fbe9c8' },
  done: { label: 'DONE', color: '#1f9d6c', bg: '#dff5e9' },
  failed: { label: 'FAILED', color: '#c0392b', bg: '#fbe2e2' },
  pending: { label: 'PENDING', color: '#5f5d56', bg: '#ebe8e0' },
};

function statusKeyFor(status: string | undefined): StatusKey {
  switch ((status ?? '').toLowerCase()) {
    case 'completed':
      return 'done';
    case 'failed':
      return 'failed';
    case 'processing':
      return 'processing';
    default:
      return 'pending';
  }
}

export function CsvImportsScreen() {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const serverUrl = useSettingsStore((s) => s.serverUrl);

  const query = useCsvImports();
  const imports = query.data ?? [];

  return (
    <ListScreen
      title="CSV imports"
      eyebrow={`HISTORY · ${imports.length} IMPORT${imports.length === 1 ? '' : 'S'}`}
      items={imports}
      keyExtractor={(i) => String(i.id)}
      isLoading={query.isLoading}
      isError={query.isError}
      error={query.error}
      isFetching={query.isFetching}
      onRefresh={query.refetch}
      emptyTitle="No imports yet"
      emptySubtitle="Upload a CSV from the web admin to see it here."
      extraHeader={
        <View style={{ gap: 14 }}>
          <View style={[styles.banner, { backgroundColor: palette.surfaceAlt, borderColor: palette.border }]}>
            <FontAwesome name="shield" size={14} color={palette.textMuted} style={{ marginTop: 2 }} />
            <Text style={[styles.bannerText, { color: palette.textMuted }]}>
              New imports require column mapping — start them on the web admin. Mobile shows status
              and lets you cancel a running import.
            </Text>
          </View>

          <Pressable
            onPress={() =>
              WebBrowser.openBrowserAsync(`${serverUrl}/admin/csv-imports`)
            }
            style={({ pressed }) => [
              styles.openWeb,
              { borderColor: palette.text, opacity: pressed ? 0.85 : 1 },
            ]}>
            <Mono size={12} color={palette.text} weight="700" letterSpacing={0.6}>
              OPEN IMPORTER ON WEB
            </Mono>
            <FontAwesome name="external-link" size={12} color={palette.text} />
          </Pressable>

          <Mono size={11} color={palette.textFaint} letterSpacing={0.8}>RECENT</Mono>
        </View>
      }
      renderItem={(item) => <ImportRow item={item} />}
    />
  );
}

function ImportRow({ item }: { item: CsvImport }) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  const key = statusKeyFor(item.status);
  const meta = STATUS_MAP[key];
  const created = item.created_at ? parseISO(item.created_at) : null;
  const ago =
    created && isValid(created)
      ? formatDistanceToNowStrict(created, { addSuffix: false })
      : '';
  const total = item.total_rows ?? 0;
  const ok = item.successful_rows ?? 0;
  const fail = item.failed_rows ?? 0;
  const pct = key === 'processing' && total > 0 ? Math.min(100, Math.round((ok / total) * 100)) : 0;

  // Compact error summary for failed rows.
  const errSummary = (() => {
    if (key !== 'failed') return null;
    const log = item.error_log;
    if (!log) return `${fail || total} ROW${(fail || total) === 1 ? '' : 'S'} FAILED`;
    if (Array.isArray(log)) {
      const sample = log[0]?.toString();
      return sample ? `${log.length} ERROR${log.length === 1 ? '' : 'S'} · ${sample}` : null;
    }
    return String(log).split('\n')[0] ?? null;
  })();

  return (
    <Card style={{ gap: 8 }}>
      <View style={styles.row}>
        <FontAwesome name="file-text-o" size={16} color={palette.textMuted} />
        <View style={{ flex: 1, minWidth: 0 }}>
          <Text style={[styles.filename, { color: palette.text, fontFamily: MONO }]} numberOfLines={1}>
            {item.filename || `Import #${item.id}`}
          </Text>
          <Mono size={10} color={palette.textFaint} letterSpacing={0.4} style={{ marginTop: 3 }}>
            {[
              total > 0 ? `${total} ROWS` : null,
              ago ? ago.toUpperCase() : null,
              created && isValid(created) ? format(created, 'd MMM').toUpperCase() : null,
            ]
              .filter(Boolean)
              .join(' · ')}
          </Mono>
        </View>
        <View style={[styles.statusPill, { backgroundColor: meta.bg }]}>
          <Mono size={9.5} color={meta.color} weight="700" letterSpacing={0.5}>
            {meta.label}
          </Mono>
        </View>
      </View>

      {key === 'processing' ? (
        <View style={[styles.barTrack, { backgroundColor: palette.surfaceAlt }]}>
          <View style={[styles.barFill, { width: `${pct}%`, backgroundColor: BRAND.amber }]} />
        </View>
      ) : null}

      {errSummary ? (
        <Mono size={10} color={palette.danger} letterSpacing={0.3}>
          {errSummary}
        </Mono>
      ) : null}
    </Card>
  );
}

const styles = StyleSheet.create({
  banner: {
    padding: 14,
    borderRadius: 12,
    borderWidth: 1,
    flexDirection: 'row',
    gap: 10,
  },
  bannerText: { fontSize: 12, lineHeight: 18, flex: 1 },
  openWeb: {
    height: 48,
    borderRadius: 12,
    borderWidth: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  filename: { fontSize: 12, fontWeight: '600' },
  statusPill: { paddingVertical: 3, paddingHorizontal: 6, borderRadius: 4 },
  barTrack: { height: 4, borderRadius: 2, overflow: 'hidden' },
  barFill: { height: '100%' },
});
