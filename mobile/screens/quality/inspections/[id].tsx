import { FontAwesome } from '@expo/vector-icons';
import { format, parseISO } from 'date-fns';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Alert, Pressable, StyleSheet, Text, View } from 'react-native';

import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { DetailScreen, DetailHero } from '@/components/ui/Detail';
import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
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

const STATUS_COLOR: Record<InspectionStatus, string> = {
  pending: BRAND.amber,
  pass: '#1C9A55',
  fail: '#D6442F',
  conditional_pass: '#EA5A2B',
};

/**
 * Inspection detail — shows plan name, material, lot, inspector, status hero,
 * and the per-criterion result row. While the inspection is pending, a "Run"
 * CTA jumps into the wizard; once completed, results are read-only.
 */
export function InspectionDetail() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
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
  const color = STATUS_COLOR[status] ?? palette.textMuted;
  const pending = status === 'pending';
  // Disposition is locked while the inspection is still pending — the operator
  // has to record results + complete first. Once `disposition !== 'pending'`,
  // re-disposition is allowed (supervisor judgment call), matching web.
  const canApplyDisposition =
    canDispose && status !== 'pending';

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
                onError: (e: Error) =>
                  Alert.alert(t('Failed'), e.message),
              },
            ),
        },
      ],
    );
  };

  return (
    <DetailScreen title={insp.material?.name ?? `MATERIAL #${insp.material_id}`} subtitle={`LOT ${insp.lot_number}`}>
      {/* Status hero */}
      <Card style={{ borderColor: color, borderWidth: 1 }}>
        <DetailHero
          eyebrow={`${t('INSPECTION').toUpperCase()} · ${(insp.plan?.name ?? t('Ad-hoc')).toUpperCase()}`}
          title={status.toUpperCase().replace('_', ' ')}
          subtitle={[
            insp.inspector?.name ?? insp.inspector?.username,
            safeDate(insp.started_at),
          ]
            .filter(Boolean)
            .join(' · ')}
          trailing={
            <View style={[styles.statusPill, { backgroundColor: `${color}22` }]}>
              <Mono size={10} color={color} weight="700" letterSpacing={0.5}>
                {status.toUpperCase().replace('_', ' ')}
              </Mono>
            </View>
          }
        />
      </Card>

      {/* KV grid */}
      <Card style={{ padding: 0 }}>
        <KV label={t('Material')} value={insp.material?.name ?? '—'} palette={palette} />
        <KV label={t('Lot number')} value={insp.lot_number} palette={palette} divider />
        {insp.supplier_lot_ref ? (
          <KV
            label={t('Supplier ref')}
            value={insp.supplier_lot_ref}
            palette={palette}
            divider
          />
        ) : null}
        {insp.quantity_received != null ? (
          <KV
            label={t('Qty received')}
            value={String(insp.quantity_received)}
            palette={palette}
            divider
          />
        ) : null}
        <KV
          label={t('Started')}
          value={safeDate(insp.started_at) || '—'}
          palette={palette}
          divider
        />
        {insp.completed_at ? (
          <KV
            label={t('Completed')}
            value={safeDate(insp.completed_at)}
            palette={palette}
            divider
          />
        ) : null}
        <KV
          label={t('Disposition')}
          value={(insp.disposition ?? '—').toUpperCase()}
          palette={palette}
          divider
        />
      </Card>

      {/* Results */}
      <View style={{ gap: 8 }}>
        <Mono size={10.5} color={palette.textFaint} letterSpacing={0.8}>
          {t('CRITERIA').toUpperCase()} · {insp.results?.length ?? 0}
        </Mono>
        <Card style={{ padding: 0 }}>
          {(insp.results ?? []).length === 0 ? (
            <View style={{ padding: 14 }}>
              <Mono size={11} color={palette.textFaint}>
                {t('No criteria recorded').toUpperCase()}
              </Mono>
            </View>
          ) : (
            (insp.results ?? []).map((r, i, arr) => (
              <ResultRow
                key={r.id}
                result={r}
                divider={i < arr.length - 1}
                palette={palette}
              />
            ))
          )}
        </Card>
      </View>

      {pending ? (
        <Button
          title={t('Run inspection')}
          variant="primary"
          onPress={() =>
            router.push(`/quality/inspections/${insp.id}/run` as never)
          }
        />
      ) : canApplyDisposition ? (
        <View style={{ gap: 8 }}>
          <Mono size={10.5} color={palette.textFaint} letterSpacing={0.8}>
            {t('DISPOSITION').toUpperCase()}
          </Mono>
          <View style={styles.dispositionGrid}>
            {DISPOSITION_OPTIONS.map((opt) => (
              <Pressable
                key={opt.action}
                onPress={() => applyDisposition(opt.action)}
                disabled={dispositionMutation.isPending}
                style={({ pressed }) => [
                  styles.dispositionBtn,
                  {
                    backgroundColor: palette.surface,
                    borderColor: opt.color,
                    opacity:
                      dispositionMutation.isPending ? 0.5 : pressed ? 0.85 : 1,
                  },
                ]}>
                <FontAwesome name={opt.icon} size={16} color={opt.color} />
                <Mono size={11} color={opt.color} weight="700" letterSpacing={0.5}>
                  {t(opt.label).toUpperCase()}
                </Mono>
              </Pressable>
            ))}
          </View>
        </View>
      ) : null}
    </DetailScreen>
  );
}

const DISPOSITION_OPTIONS: Array<{
  action: DispositionAction;
  label: string;
  icon: React.ComponentProps<typeof FontAwesome>['name'];
  color: string;
}> = [
  { action: 'accept', label: 'Release', icon: 'check-circle', color: '#1C9A55' },
  { action: 'accept_with_deviation', label: 'Release w/ deviation', icon: 'exclamation-circle', color: '#EA5A2B' },
  { action: 'rework', label: 'Rework', icon: 'refresh', color: BRAND.amber },
  { action: 'quarantine', label: 'Quarantine', icon: 'lock', color: '#8a5a0e' },
  { action: 'return_to_supplier', label: 'Return to supplier', icon: 'reply', color: '#7c3aed' },
  { action: 'scrap', label: 'Scrap', icon: 'trash', color: '#D6442F' },
  { action: 'reject', label: 'Reject', icon: 'times-circle', color: '#D6442F' },
];

function ResultRow({
  result,
  divider,
  palette,
}: {
  result: InspectionResult;
  divider: boolean;
  palette: typeof Colors.light;
}) {
  const { t } = useTranslation();
  const passed = result.is_passed;
  const passColor =
    passed === true
      ? palette.success
      : passed === false
        ? palette.danger
        : palette.textFaint;
  const valueText = useMemo(() => formatResultValue(result), [result]);

  return (
    <View
      style={[
        styles.resultRow,
        divider
          ? { borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: palette.border }
          : null,
      ]}>
      <FontAwesome
        name={passed === true ? 'check-circle' : passed === false ? 'times-circle' : 'circle-o'}
        size={16}
        color={passColor}
      />
      <View style={{ flex: 1, minWidth: 0 }}>
        <Text style={[styles.criterionName, { color: palette.text }]}>
          {result.criterion_name}
          {result.required ? (
            <Text style={{ color: palette.danger }}> *</Text>
          ) : null}
        </Text>
        <Mono size={10} color={palette.textFaint} letterSpacing={0.4} style={{ marginTop: 2 }}>
          {result.criterion_type.toUpperCase()}
          {specRange(result) ? ` · ${specRange(result)}` : ''}
          {result.unit ? ` · ${result.unit}` : ''}
        </Mono>
      </View>
      <Mono size={12} color={palette.text} weight="700">
        {valueText ?? t('—')}
      </Mono>
    </View>
  );
}

function KV({
  label,
  value,
  palette,
  divider,
}: {
  label: string;
  value: string;
  palette: typeof Colors.light;
  divider?: boolean;
}) {
  return (
    <View
      style={[
        styles.kvRow,
        divider
          ? { borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: palette.border }
          : null,
      ]}>
      <Text style={{ color: palette.textMuted, fontSize: 12, flex: 1 }}>{label}</Text>
      <Mono size={12} color={palette.text} weight="600" style={{ textAlign: 'right' }}>
        {value}
      </Mono>
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
  statusPill: { paddingVertical: 3, paddingHorizontal: 7, borderRadius: 4 },
  resultRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingVertical: 12,
    paddingHorizontal: 14,
  },
  criterionName: { fontSize: 13, fontWeight: '600' },
  kvRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 10,
    paddingVertical: 11,
    paddingHorizontal: 14,
  },
  dispositionGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  dispositionBtn: {
    flexBasis: '47%',
    flexGrow: 1,
    flexDirection: 'row',
    gap: 8,
    alignItems: 'center',
    justifyContent: 'center',
    height: 44,
    borderRadius: 10,
    borderWidth: 1,
  },
});
