/**
 * Shift handover (GET/POST /api/v1/shift-handover) — supervisor shift closeout:
 * the live balance (produced / scrap / good / packed / WIP / shipped + discrepancies),
 * the itemized open-pallets list, and "close shift" (with notes) to snapshot it, plus
 * recent handovers. A line selector scopes the balance. Geist White, light-only v1.
 */
import { useState } from 'react';
import { Alert, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { FontAwesome } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';

import { colors, fonts } from '@openmes/ui';
import { ActionSheet } from '@openmes/ui/native';

import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Field } from '@/components/ui/Field';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { StatTile } from '@/components/ui/StatTile';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useCloseShift, useShiftHandover } from '@/hooks/queries/useShiftHandover';

const n = (v?: number) => Number(v ?? 0).toLocaleString();
const hhmm = (iso?: string) => (iso ? iso.slice(11, 16) : '');
const sevColor = (s: string) =>
  s === 'warning' ? colors.downtime : s === 'error' || s === 'danger' ? colors.blocked : colors.accent;

export default function ShiftHandoverPage() {
  const { t } = useTranslation();
  // null = "All lines" (matches the web default). Driven by the header selector.
  const [lineId, setLineId] = useState<number | null>(null);
  const [linePicker, setLinePicker] = useState(false);
  const [notes, setNotes] = useState('');

  const q = useShiftHandover(lineId);
  const close = useCloseShift();
  const d = q.data;
  const b = d?.balance;
  const lines = d?.lines ?? [];

  const lineName = lineId == null ? t('All lines') : (lines.find((l) => l.id === lineId)?.name ?? t('All lines'));

  const subtitle = !b
    ? 'SUPERVISOR'
    : b.shift
      ? `${b.shift.name} · ${b.shift.start}–${b.shift.end} · ${b.window.business_date}`
      : `${t('No shift configured')} · ${b.window.business_date} · ${hhmm(b.window.start)}–${hhmm(b.window.end)}`;

  const onClose = () => {
    Alert.alert(
      t('Close shift?'),
      t('This snapshots the current balance and saves a handover record.'),
      [
        { text: t('Cancel'), style: 'cancel' },
        {
          text: t('Close shift'),
          style: 'destructive',
          onPress: () =>
            close.mutate(
              { line_id: lineId, notes: notes || undefined },
              {
                onSuccess: () => {
                  setNotes('');
                  Alert.alert(t('Shift closed'), t('Snapshot saved.'));
                },
                onError: (e: Error) => Alert.alert(t('Could not close shift'), e.message),
              },
            ),
        },
      ],
    );
  };

  return (
    <View style={styles.screen}>
      <ScreenHeader
        title="Shift handover"
        subtitle={subtitle}
        rightSlot={
          <Pressable onPress={() => setLinePicker(true)} style={styles.linePill}>
            <Text style={styles.linePillText} numberOfLines={1}>
              {lineName}
            </Text>
            <FontAwesome name="chevron-down" size={11} color={colors.faint} />
          </Pressable>
        }
      />
      {q.isLoading ? (
        <LoadingState />
      ) : q.isError ? (
        <ErrorState error={q.error} onRetry={q.refetch} />
      ) : (
        <ScrollView
          contentContainerStyle={styles.list}
          refreshControl={<RefreshControl refreshing={q.isFetching} onRefresh={q.refetch} />}>
          {/* Headline — six tiles, web parity (#7). */}
          <View style={styles.kpiGrid}>
            <StatTile label={t('Produced')} value={n(b?.produced_qty)} tone="blue" />
            <StatTile label={t('Scrap')} value={n(b?.scrap_qty)} tone="red" />
            <StatTile label={t('Good')} value={n(b?.good_qty)} tone="green" />
            <StatTile label={t('Packed')} value={n(b?.packed_qty)} tone="neutral" />
            <StatTile
              label={t('WIP')}
              value={n(b?.wip_total_qty)}
              tone="amber"
              hint={`${n(b?.wip_open_pallets_qty)} ${t('open pallets')} + ${n(b?.wip_unpacked_qty)} ${t('unpacked')}`}
            />
            <StatTile label={t('Shipped')} value={n(b?.shipped_qty)} tone="neutral" />
          </View>

          {b?.discrepancies?.length ? (
            <Card style={{ gap: 10 }}>
              <Mono size={9} color={colors.faint} letterSpacing={1}>
                {t('DISCREPANCIES')}
              </Mono>
              {b.discrepancies.map((x, i) => (
                <View key={i} style={styles.discRow}>
                  <View style={[styles.dot, { backgroundColor: sevColor(x.severity) }]} />
                  <Text style={styles.discLabel} numberOfLines={2}>
                    {x.label}
                  </Text>
                  <Mono size={13} color={colors.ink}>
                    {n(x.value)}
                  </Mono>
                </View>
              ))}
            </Card>
          ) : null}

          {/* Open pallets — itemized (#3). */}
          <Card style={{ gap: 10 }}>
            <View style={styles.cardHead}>
              <Mono size={9} color={colors.faint} letterSpacing={1}>
                {t('OPEN PALLETS')}
              </Mono>
              <View style={styles.countBadge}>
                <Mono size={10} color={colors.muted} weight="600">
                  {n(b?.wip_open_pallets_count)}
                </Mono>
              </View>
            </View>
            {b?.open_pallets?.length ? (
              b.open_pallets.map((p) => (
                <View key={p.id} style={styles.palletRow}>
                  <Mono size={12} color={colors.accent}>
                    {p.pallet_no ?? '—'}
                  </Mono>
                  <Mono size={12} color={colors.muted} style={{ flex: 1, textAlign: 'center' }}>
                    {p.order_no ?? '—'}
                  </Mono>
                  <Mono size={13} color={colors.ink}>
                    {n(p.qty)} {t('pcs')}
                  </Mono>
                </View>
              ))
            ) : (
              <Text style={styles.emptyText}>{t('No open pallets')}</Text>
            )}
          </Card>

          {/* Close shift — notes + snapshot (#4). */}
          <Card style={{ gap: 10 }}>
            <Mono size={9} color={colors.faint} letterSpacing={1}>
              {t('CLOSE SHIFT')}
            </Mono>
            <Field
              label="Notes"
              placeholder="Handover notes (optional)"
              multiline
              value={notes}
              onChangeText={setNotes}
            />
            <Button title="Close shift & save snapshot" onPress={onClose} loading={close.isPending} />
          </Card>

          {d?.recent?.length ? (
            <>
              <SectionLabel>{t('Recent handovers')}</SectionLabel>
              <Card style={{ gap: 0 }}>
                {d.recent.map((h) => (
                  <View key={h.id} style={styles.recentRow}>
                    <View style={{ flex: 1, minWidth: 0 }}>
                      <Text style={styles.recentTitle} numberOfLines={1}>
                        {h.line_name ?? t('All lines')}
                      </Text>
                      <Mono size={10} color={colors.faint}>
                        {[h.shift_start, h.confirmed_by].filter(Boolean).join(' · ')}
                      </Mono>
                    </View>
                    <View style={styles.recentMetrics}>
                      <Metric label={t('Prod')} value={n(h.produced_qty)} />
                      <Metric label={t('Packed')} value={n(h.packed_qty)} />
                      <Metric label={t('Shipped')} value={n(h.shipped_qty)} />
                    </View>
                  </View>
                ))}
              </Card>
            </>
          ) : null}
        </ScrollView>
      )}

      {/* Line selector — scopes the balance query (#5). */}
      <ActionSheet
        open={linePicker}
        onClose={() => setLinePicker(false)}
        title={t('Line')}
        options={[
          { key: 'all', label: t('All lines'), onSelect: () => setLineId(null) },
          ...lines.map((l) => ({ key: String(l.id), label: l.name, onSelect: () => setLineId(l.id) })),
        ]}
      />
    </View>
  );
}

function Metric({ label, value }: { label: string; value: string }) {
  return (
    <View style={{ alignItems: 'flex-end' }}>
      <Mono size={8} color={colors.faint} letterSpacing={0.6}>
        {label.toUpperCase()}
      </Mono>
      <Mono size={12} color={colors.ink}>
        {value}
      </Mono>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  list: { padding: 18, gap: 14, paddingBottom: 32, maxWidth: 680, width: '100%', alignSelf: 'center' },
  kpiGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  linePill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    maxWidth: 150,
    paddingVertical: 8,
    paddingHorizontal: 10,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.card,
  },
  linePillText: { fontSize: 12, fontFamily: fonts.sans.native.semibold, color: colors.ink },
  cardHead: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  countBadge: { backgroundColor: colors.chip, borderRadius: 999, paddingHorizontal: 8, paddingVertical: 2 },
  palletRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: colors.line2,
    paddingTop: 8,
  },
  emptyText: { fontSize: 13, color: colors.faint, fontFamily: fonts.sans.native.regular, paddingVertical: 6, textAlign: 'center' },
  discRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: colors.line2,
    paddingTop: 8,
  },
  dot: { width: 8, height: 8, borderRadius: 2 },
  discLabel: { flex: 1, fontSize: 13, color: colors.ink },
  recentRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: colors.line2,
    paddingVertical: 10,
  },
  recentTitle: { fontSize: 14, fontFamily: fonts.sans.native.semibold, color: colors.ink },
  recentMetrics: { flexDirection: 'row', gap: 14 },
});
