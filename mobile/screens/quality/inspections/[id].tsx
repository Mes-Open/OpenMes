/**
 * Inspection detail — mirrors the web inspections Show page: header + status
 * pill, a bordered key/value info box, the Disposition section, and the
 * per-criterion results table. While pending a "Run inspection" CTA jumps into
 * the wizard; once completed, results are read-only and a supervisor can apply
 * a disposition. All hooks / mutations / navigation are preserved.
 */
import { FontAwesome } from '@expo/vector-icons';
import { format, parseISO } from 'date-fns';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import { colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import {
  useApplyDisposition,
  useInspection,
} from '@/hooks/queries/useInspections';
import { isSupervisorOrAdmin, useAuthStore } from '@/stores/authStore';
import type {
  DispositionAction,
  Inspection,
  InspectionResult,
  InspectionStatus,
} from '@/api/inspections';

const STATUS_PILL: Record<InspectionStatus, string> = {
  pending: 'pending',
  pass: 'accepted',
  fail: 'blocked',
  conditional_pass: 'paused',
};

const STATUS_LABEL: Record<InspectionStatus, string> = {
  pending: 'Pending',
  pass: 'Pass',
  fail: 'Fail',
  conditional_pass: 'Conditional',
};

const humanize = (s: string) => s.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

export function InspectionDetail() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();
  const { t } = useTranslation();

  const query = useInspection(numericId);
  const user = useAuthStore((s) => s.user);
  const canDispose = isSupervisorOrAdmin(user);
  const dispositionMutation = useApplyDisposition();

  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data)
    return <ErrorState error={query.error} onRetry={query.refetch} />;

  const insp: Inspection = query.data;
  const status = insp.status as InspectionStatus;
  const pending = status === 'pending';
  // Disposition is locked while the inspection is still pending — the operator
  // has to record results + complete first. Once completed, re-disposition is
  // allowed (supervisor judgment call), matching web.
  const canApplyDisposition = canDispose && status !== 'pending';
  const hasDecision = insp.disposition && insp.disposition !== 'pending';
  const results = insp.results ?? [];

  const applyDisposition = (action: DispositionAction) => {
    Alert.alert(
      t('Confirm disposition'),
      t('Apply {{action}} to this inspection?', {
        action: action.replace(/_/g, ' '),
      }),
      [
        { text: t('Cancel'), style: 'cancel' },
        {
          text: t('Apply'),
          style: action === 'scrap' || action === 'reject' ? 'destructive' : 'default',
          onPress: () =>
            dispositionMutation.mutate(
              { id: insp.id, payload: { disposition: action } },
              {
                onError: (e: Error) => Alert.alert(t('Failed'), e.message),
              },
            ),
        },
      ],
    );
  };

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <View style={styles.headerRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.h1}>{`${t('Inspection')} #${insp.id}`}</Text>
          <Mono size={10} color={colors.faint} letterSpacing={0.4} style={{ marginTop: 6 }}>
            {(insp.material?.name ?? `#${insp.material_id}`).toUpperCase()}
          </Mono>
        </View>
        <StatusPill status={STATUS_PILL[status] ?? 'pending'} label={t(STATUS_LABEL[status] ?? humanize(insp.status))} />
      </View>

      {/* Info box */}
      <View style={{ gap: 8 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Details').toUpperCase()}</Mono>
        <View style={styles.box}>
          <KVRow label={t('Material')} value={insp.material?.name ?? '—'} />
          <KVRow label={t('Lot number')} value={insp.lot_number} mono />
          {insp.supplier_lot_ref ? <KVRow label={t('Supplier ref')} value={insp.supplier_lot_ref} mono /> : null}
          {insp.quantity_received != null ? <KVRow label={t('Qty received')} value={String(insp.quantity_received)} mono /> : null}
          <KVRow label={t('Inspector')} value={insp.inspector?.name ?? insp.inspector?.username ?? '—'} />
          <KVRow label={t('Started')} value={safeDate(insp.started_at) || '—'} mono />
          {insp.completed_at ? <KVRow label={t('Completed')} value={safeDate(insp.completed_at)} mono /> : null}
          <KVRow label={t('Disposition')} value={hasDecision ? humanize(insp.disposition) : '—'} last />
        </View>
      </View>

      {/* Disposition section */}
      <View style={{ gap: 8 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Disposition').toUpperCase()}</Mono>
        <View style={[styles.box, { paddingVertical: 12 }]}>
          {hasDecision ? (
            <View style={{ gap: 6 }}>
              <View style={{ flexDirection: 'row', alignItems: 'center', gap: 10 }}>
                <StatusPill status={dispositionPill(insp.disposition)} label={humanize(insp.disposition)} />
                <Mono size={10} color={colors.muted}>
                  {insp.disposition_by_id != null ? `#${insp.disposition_by_id}` : '—'}
                  {insp.disposition_at ? ` · ${safeDate(insp.disposition_at)}` : ''}
                </Mono>
              </View>
              {insp.disposition_notes ? (
                <Text style={styles.notes}>{insp.disposition_notes}</Text>
              ) : null}
            </View>
          ) : (
            <Text style={styles.cellText}>{t('No disposition recorded yet.')}</Text>
          )}
        </View>
      </View>

      {/* Results */}
      <View style={{ gap: 8 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>
          {`${t('Results')} · ${results.length}`.toUpperCase()}
        </Mono>
        {results.length === 0 ? (
          <Text style={styles.empty}>{t('No results recorded.')}</Text>
        ) : (
          <View style={styles.box}>
            <View style={[styles.row, styles.tableHead]}>
              <HCell flex={1}>{t('Criterion')}</HCell>
              <HCell w={72}>{t('Value')}</HCell>
              <HCell w={78}>{t('Result')}</HCell>
            </View>
            {results.map((r, i, arr) => (
              <ResultRow key={r.id} result={r} last={i === arr.length - 1} />
            ))}
          </View>
        )}
      </View>

      {/* CTA */}
      {pending ? (
        <Button
          title={t('Run inspection')}
          variant="accent"
          onPress={() => router.push(`/quality/inspections/${insp.id}/run` as never)}
        />
      ) : canApplyDisposition ? (
        <View style={{ gap: 8 }}>
          <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Record disposition').toUpperCase()}</Mono>
          <View style={styles.dispositionGrid}>
            {DISPOSITION_OPTIONS.map((opt) => (
              <Pressable
                key={opt.action}
                onPress={() => applyDisposition(opt.action)}
                disabled={dispositionMutation.isPending}
                style={({ pressed }) => [
                  styles.dispositionBtn,
                  {
                    borderColor: opt.color,
                    opacity: dispositionMutation.isPending ? 0.5 : pressed ? 0.85 : 1,
                  },
                ]}>
                <FontAwesome name={opt.icon} size={15} color={opt.color} />
                <Mono size={11} color={opt.color} weight="600" letterSpacing={0.4}>
                  {t(opt.label).toUpperCase()}
                </Mono>
              </Pressable>
            ))}
          </View>
        </View>
      ) : null}
    </ScrollView>
  );
}

const DISPOSITION_OPTIONS: Array<{
  action: DispositionAction;
  label: string;
  icon: React.ComponentProps<typeof FontAwesome>['name'];
  color: string;
}> = [
  { action: 'accept', label: 'Release', icon: 'check-circle', color: colors.running },
  { action: 'accept_with_deviation', label: 'Release w/ deviation', icon: 'exclamation-circle', color: colors.downtime },
  { action: 'rework', label: 'Rework', icon: 'refresh', color: colors.downtime },
  { action: 'quarantine', label: 'Quarantine', icon: 'lock', color: colors.pending },
  { action: 'return_to_supplier', label: 'Return to supplier', icon: 'reply', color: colors.downtime },
  { action: 'scrap', label: 'Scrap', icon: 'trash', color: colors.blocked },
  { action: 'reject', label: 'Reject', icon: 'times-circle', color: colors.blocked },
];

// Disposition → design-system pill kind, mirroring the web colour map.
function dispositionPill(disposition: string): string {
  const map: Record<string, string> = {
    accept: 'accepted',
    accept_with_deviation: 'accepted',
    rework: 'paused',
    quarantine: 'pending',
    scrap: 'blocked',
    reject: 'blocked',
    return_to_supplier: 'paused',
  };
  return map[disposition] ?? 'pending';
}

function ResultRow({ result, last }: { result: InspectionResult; last: boolean }) {
  const { t } = useTranslation();
  const passed = result.is_passed;
  const value = useMemo(() => formatResultValue(result), [result]);
  const pillKind = passed === true ? 'accepted' : passed === false ? 'blocked' : 'pending';
  const pillLabel = passed === true ? t('Pass') : passed === false ? t('Fail') : '—';

  return (
    <View style={[styles.row, last ? null : styles.tableRow, { paddingVertical: 11 }]}>
      <View style={{ flex: 1, minWidth: 0 }}>
        <Text style={styles.criterion} numberOfLines={1}>
          {result.criterion_name}
          {result.required ? <Text style={{ color: colors.accent }}> *</Text> : null}
        </Text>
        <Mono size={9} color={colors.faint} letterSpacing={0.4} style={{ marginTop: 3 }}>
          {String(result.criterion_type).toUpperCase()}
          {specRange(result) ? ` · ${specRange(result)}` : ''}
          {result.unit ? ` · ${result.unit}` : ''}
        </Mono>
      </View>
      <View style={{ width: 72 }}>
        <Mono size={11} color={colors.ink} numberOfLines={1}>{value ?? '—'}</Mono>
      </View>
      <View style={{ width: 78 }}>
        <StatusPill status={pillKind} label={pillLabel} />
      </View>
    </View>
  );
}

function KVRow({ label, value, mono, last }: { label: string; value: string; mono?: boolean; last?: boolean }) {
  return (
    <View style={[styles.kvRow, last ? null : styles.kvBorder]}>
      <Mono size={9} color={colors.faint} letterSpacing={0.6}>{label.toUpperCase()}</Mono>
      {mono ? (
        <Mono size={11} color={colors.ink}>{value}</Mono>
      ) : (
        <Text style={styles.kvValue} numberOfLines={1}>{value}</Text>
      )}
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

function formatResultValue(r: InspectionResult): string | null {
  if (r.value_numeric != null) return String(r.value_numeric);
  if (r.value_boolean != null) return r.value_boolean ? 'YES' : 'NO';
  if (r.value_text) return r.value_text;
  return null;
}

function specRange(r: InspectionResult): string {
  if (r.spec_min != null && r.spec_max != null) return `${r.spec_min} – ${r.spec_max}`;
  if (r.spec_min != null) return `≥ ${r.spec_min}`;
  if (r.spec_max != null) return `≤ ${r.spec_max}`;
  return '';
}

function safeDate(iso?: string | null): string {
  if (!iso) return '';
  try {
    return format(parseISO(iso), 'yyyy-MM-dd HH:mm');
  } catch {
    return '';
  }
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  headerRow: { flexDirection: 'row', alignItems: 'flex-start', gap: 12 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  box: { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, paddingHorizontal: 14 },
  kvRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10, paddingVertical: 11 },
  kvBorder: { borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.line2 },
  kvValue: { fontSize: 12.5, color: colors.ink, fontFamily: fonts.sans.native.medium },
  notes: { fontSize: 13, lineHeight: 20, color: colors.muted, fontFamily: fonts.sans.native.regular },
  row: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingHorizontal: 6 },
  tableHead: { paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: colors.line },
  tableRow: { borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.line2 },
  criterion: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
  cellText: { fontSize: 12.5, color: colors.muted, fontFamily: fonts.sans.native.regular },
  empty: { fontSize: 13, color: colors.faint, fontFamily: fonts.sans.native.regular, padding: 16 },
  dispositionGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  dispositionBtn: {
    flexBasis: '47%',
    flexGrow: 1,
    flexDirection: 'row',
    gap: 8,
    alignItems: 'center',
    justifyContent: 'center',
    height: 44,
    borderRadius: radius.md,
    borderWidth: 1,
    backgroundColor: colors.card,
  },
});
