/**
 * Machine monitor (GET /api/v1/machine-monitor) — live workstation fleet status
 * tiles (state, availability, good/reject, quality), polled. Tap a tile to set
 * its state (POST .../{id}/state). Geist White, light-only v1.
 */
import { useEffect, useState } from 'react';
import { useRouter } from 'expo-router';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts } from '@openmes/ui';
import { ActionSheet } from '@openmes/ui/native';

import { Mono } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useMachineMonitor, useSetWorkstationState } from '@/hooks/queries/useMachineMonitor';
import type { MachineTile } from '@/api/machineMonitor';

// Map the backend's color token (App\Support colour vocabulary) to theme colors,
// mirroring the web BORDER/BADGE maps. Never hand the raw token to RN styles.
const TILE_COLOR: Record<string, string> = {
  green: colors.running,
  amber: colors.downtime,
  yellow: colors.downtime,
  orange: colors.downtime,
  red: colors.blocked,
  blue: colors.accent,
  gray: colors.faint,
  slate: colors.faint,
  purple: '#7C3AED',
};
const tileColor = (token?: string | null) => TILE_COLOR[token ?? ''] ?? colors.faint;

// Raw percentage (parity with web — no rounding).
const pct = (v?: number | null) => (v == null ? '—' : `${Number(v)}%`);

// Elapsed time since the tile entered its current state (web timeInState).
function timeInState(sinceIso: string | null | undefined, now: number): string | null {
  if (!sinceIso) return null;
  const start = new Date(sinceIso).getTime();
  if (Number.isNaN(start)) return null;
  const sec = Math.max(0, Math.floor((now - start) / 1000));
  if (sec < 60) return `${sec}s`;
  if (sec < 3600) return `${Math.floor(sec / 60)}m`;
  return `${Math.floor(sec / 3600)}h ${Math.floor((sec % 3600) / 60)}m`;
}

export default function MachineMonitorPage() {
  const { t, i18n } = useTranslation();
  const router = useRouter();
  const q = useMachineMonitor();
  const setState = useSetWorkstationState();
  const [picker, setPicker] = useState<MachineTile | null>(null);
  const [now, setNow] = useState(() => Date.now());

  // Tick once a second so the "in state for" labels stay fresh (web parity).
  useEffect(() => {
    const id = setInterval(() => setNow(Date.now()), 1000);
    return () => clearInterval(id);
  }, []);

  const tiles = q.data?.tiles ?? [];
  const states = q.data?.states ?? [];
  const fmtNum = (v: number | null | undefined) => Number(v ?? 0).toLocaleString(i18n.language);

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Machine Monitor" subtitle={`${t('Fleet')} · ${tiles.length} ${t('stations')}`} />
      {q.isLoading ? (
        <LoadingState />
      ) : q.isError ? (
        <ErrorState error={q.error} onRetry={q.refetch} />
      ) : tiles.length === 0 ? (
        <View style={styles.emptyWrap}>
          <EmptyState
            title={t('No machines')}
            subtitle={t('No workstations are wired to a machine connection yet.')}
          />
          <Pressable onPress={() => router.push('/connectivity/modbus' as never)} hitSlop={8}>
            <Text style={styles.emptyLink}>{t('Configure a Modbus connection')} →</Text>
          </Pressable>
        </View>
      ) : (
        <ScrollView
          contentContainerStyle={styles.grid}
          refreshControl={<RefreshControl refreshing={q.isFetching} onRefresh={q.refetch} />}>
          {tiles.map((tile) => {
            const c = tileColor(tile.color);
            const elapsed = timeInState(tile.since, now);
            const metadata =
              tile.metadata && typeof tile.metadata === 'object'
                ? Object.entries(tile.metadata as Record<string, unknown>)
                : [];
            return (
              <Pressable
                key={tile.id}
                onPress={() => setPicker(tile)}
                style={({ pressed }) => [styles.tile, { borderLeftColor: c, opacity: pressed ? 0.9 : 1 }]}>
                <View style={styles.tileTop}>
                  <View style={{ flex: 1, minWidth: 0 }}>
                    <Text style={styles.tileName} numberOfLines={1}>
                      {tile.name}
                    </Text>
                    <Mono size={9} color={colors.faint}>
                      {tile.line ?? '—'}
                    </Mono>
                  </View>
                  <View style={styles.stateBadge}>
                    <Mono size={9} color={c} weight="700" letterSpacing={0.5}>
                      {tile.state}
                    </Mono>
                  </View>
                </View>
                {elapsed ? (
                  <Mono size={9} color={colors.faint}>
                    {t('in state for')} {elapsed}
                  </Mono>
                ) : null}
                <View style={styles.tileStats}>
                  <Stat label={t('Availability')} value={pct(tile.availability)} />
                  <Stat label={t('Good')} value={fmtNum(tile.good)} tone={colors.running} />
                  <Stat label={t('Reject')} value={fmtNum(tile.reject)} tone={colors.blocked} />
                </View>
                {tile.quality != null ? (
                  <Mono size={9} color={colors.faint}>
                    {t('Quality')} {Number(tile.quality)}%
                  </Mono>
                ) : null}
                {metadata.length > 0 ? (
                  <View style={styles.metaWrap}>
                    {metadata.map(([k, v]) => (
                      <View key={k} style={styles.metaChip}>
                        <Mono size={9} color={colors.faint}>
                          {k}:
                        </Mono>
                        <Mono size={9} color={colors.muted}>
                          {' '}
                          {String(v)}
                        </Mono>
                      </View>
                    ))}
                  </View>
                ) : null}
              </Pressable>
            );
          })}
        </ScrollView>
      )}

      <ActionSheet
        open={!!picker}
        onClose={() => setPicker(null)}
        title={picker ? `${picker.name} · ${t('Set state')}` : undefined}
        options={states.map((s) => ({
          key: s,
          label: s,
          onPress: () => {
            if (picker) setState.mutate({ id: picker.id, state: s });
          },
        }))}
      />
    </View>
  );
}

function Stat({ label, value, tone }: { label: string; value: string; tone?: string }) {
  return (
    <View>
      <Mono size={8} color={colors.faint} letterSpacing={0.8}>
        {label.toUpperCase()}
      </Mono>
      <Mono size={14} color={tone ?? colors.ink} style={{ marginTop: 2 }}>
        {value}
      </Mono>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  grid: { padding: 18, flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  emptyWrap: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  emptyLink: { color: colors.accent, fontFamily: fonts.sans.native.medium, fontSize: 13, marginTop: -8 },
  tile: {
    flexGrow: 1,
    flexBasis: 160,
    minWidth: 150,
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.line,
    borderLeftWidth: 3,
    borderRadius: 12,
    padding: 14,
    gap: 6,
  },
  tileTop: { flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between', gap: 8 },
  tileName: { fontSize: 14, fontFamily: fonts.sans.native.semibold, color: colors.ink },
  stateBadge: { backgroundColor: colors.chip, borderRadius: 20, paddingVertical: 3, paddingHorizontal: 8 },
  tileStats: { flexDirection: 'row', gap: 20, marginTop: 6 },
  metaWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 6, marginTop: 6, paddingTop: 8, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: colors.line2 },
  metaChip: { flexDirection: 'row', alignItems: 'center', backgroundColor: colors.panel, borderWidth: 1, borderColor: colors.line2, borderRadius: 6, paddingHorizontal: 6, paddingVertical: 2 },
});
