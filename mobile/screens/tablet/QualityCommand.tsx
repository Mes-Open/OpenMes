import { FontAwesome } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import { TabletShell } from '@/components/tablet/TabletShell';
import { Mono } from '@/components/ui/Mono';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import {
  useApplyDisposition,
  useInspection,
  useInspectionPlans,
  useInspections,
  useInspectionStats,
} from '@/hooks/queries/useInspections';
import { isSupervisorOrAdmin, useAuthStore } from '@/stores/authStore';
import type {
  DispositionAction,
  Inspection,
  InspectionPlan,
  InspectionStatus,
} from '@/api/inspections';

const STATE_COLOR: Record<string, string> = {
  pending: BRAND.amber,
  pass: '#1C9A55',
  fail: '#D6442F',
  conditional_pass: '#EA5A2B',
};

/**
 * Tablet Quality Command — 3-pane supervisor workflow:
 *  - LEFT 260px: inspection plans (filter source).
 *  - CENTER: inspections table scoped to the selected plan.
 *  - RIGHT 400px: selected inspection detail with steps + 3 disposition CTAs
 *    (Release / Quarantine / Scrap), all wired to /complete + /disposition.
 */
export function TabletQualityCommand() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();

  const plansQ = useInspectionPlans();
  const inspectionsQ = useInspections({ limit: 100 });
  const statsQ = useInspectionStats({ days: 7 });

  const [planId, setPlanId] = useState<number | null>(null);
  const [selectedId, setSelectedId] = useState<number | null>(null);

  const plans = plansQ.data ?? [];
  const all: Inspection[] = inspectionsQ.data ?? [];

  const filtered = useMemo(() => {
    if (planId == null) return all;
    return all.filter((i) => i.inspection_plan_id === planId);
  }, [all, planId]);

  const selected = useMemo(() => {
    if (filtered.length === 0) return null;
    return filtered.find((i) => i.id === selectedId) ?? filtered[0];
  }, [filtered, selectedId]);

  const detail = useInspection(selected?.id);

  const planCounts = useMemo(() => {
    const c: Record<number, number> = {};
    for (const i of all) {
      if (i.inspection_plan_id == null) continue;
      c[i.inspection_plan_id] = (c[i.inspection_plan_id] ?? 0) + 1;
    }
    return c;
  }, [all]);

  const openCount = all.filter((i) => i.status === 'pending').length;
  const failThisWeek = statsQ.data?.fail_count ?? 0;

  const user = useAuthStore((s) => s.user);
  const canDispose = isSupervisorOrAdmin(user);
  const dispositionMutation = useApplyDisposition();

  const applyDisposition = (action: DispositionAction) => {
    if (!selected) return;
    Alert.alert(
      t('Confirm disposition'),
      t('Apply {{action}} to {{id}}?', {
        action: action.replace(/_/g, ' '),
        id: `INSP-${String(selected.id).padStart(4, '0')}`,
      }),
      [
        { text: t('Cancel'), style: 'cancel' },
        {
          text: t('Apply'),
          style:
            action === 'scrap' || action === 'reject' ? 'destructive' : 'default',
          onPress: () =>
            dispositionMutation.mutate({
              id: selected.id,
              payload: { disposition: action },
            }),
        },
      ],
    );
  };

  return (
    <TabletShell
      eyebrow={`${t('QUALITY COMMAND').toUpperCase()} · ${openCount} ${t('OPEN').toUpperCase()} · ${failThisWeek} ${t('FAIL THIS WEEK').toUpperCase()}`}
      title={t('Inspections & dispositions')}
      right={
        <View style={{ flexDirection: 'row', gap: 8 }}>
          <Pressable
            style={({ pressed }) => [
              styles.headerBtnGhost,
              { borderColor: palette.border, opacity: pressed ? 0.7 : 1 },
            ]}>
            <Mono size={11.5} color={palette.text} weight="600" letterSpacing={0.4}>
              {t('Export').toUpperCase()}
            </Mono>
          </Pressable>
        </View>
      }>
      <View style={styles.grid}>
        {/* LEFT — plans */}
        <View
          style={[
            styles.panel,
            styles.plansPanel,
            { backgroundColor: palette.surface, borderColor: palette.border },
          ]}>
          <Mono size={10.5} color={palette.textFaint} letterSpacing={0.8}>
            {t('INSPECTION PLANS').toUpperCase()} · {plans.length}
          </Mono>
          <ScrollView style={{ flex: 1, marginTop: 12 }} contentContainerStyle={{ gap: 4 }}>
            <Pressable
              onPress={() => setPlanId(null)}
              style={({ pressed }) => [
                styles.planRow,
                {
                  backgroundColor: planId == null ? BRAND.amberSoft : 'transparent',
                  borderColor: planId == null ? BRAND.amber : 'transparent',
                  opacity: pressed ? 0.85 : 1,
                },
              ]}>
              <FontAwesome
                name="th-list"
                size={14}
                color={planId == null ? BRAND.amber : palette.textMuted}
              />
              <Text
                style={[
                  styles.planLabel,
                  { color: palette.text, fontWeight: planId == null ? '700' : '500' },
                ]}>
                {t('All plans')}
              </Text>
              <Mono
                size={10}
                color={planId == null ? BRAND.amber : palette.textFaint}
                weight="700">
                {all.length}
              </Mono>
            </Pressable>
            {plans.map((p) => {
              const on = planId === p.id;
              return (
                <Pressable
                  key={p.id}
                  onPress={() => setPlanId(p.id)}
                  style={({ pressed }) => [
                    styles.planRow,
                    {
                      backgroundColor: on ? BRAND.amberSoft : 'transparent',
                      borderColor: on ? BRAND.amber : 'transparent',
                      opacity: pressed ? 0.85 : 1,
                    },
                  ]}>
                  <FontAwesome
                    name="shield"
                    size={14}
                    color={on ? BRAND.amber : palette.textMuted}
                  />
                  <Text
                    style={[
                      styles.planLabel,
                      { color: palette.text, fontWeight: on ? '700' : '500' },
                    ]}
                    numberOfLines={1}>
                    {p.name}
                  </Text>
                  <Mono
                    size={10}
                    color={on ? BRAND.amber : palette.textFaint}
                    weight="700">
                    {planCounts[p.id] ?? 0}
                  </Mono>
                </Pressable>
              );
            })}
          </ScrollView>
        </View>

        {/* CENTER — inspections table */}
        <View
          style={[
            styles.panel,
            styles.tablePanel,
            { backgroundColor: palette.surface, borderColor: palette.border },
          ]}>
          <View style={[styles.tableHead, { borderBottomColor: palette.border }]}>
            <Mono size={10} color={palette.textFaint} letterSpacing={0.7} style={{ width: 130 }}>
              {t('INSPECTION').toUpperCase()}
            </Mono>
            <Mono size={10} color={palette.textFaint} letterSpacing={0.7} style={{ flex: 1 }}>
              {t('PLAN').toUpperCase()}
            </Mono>
            <Mono size={10} color={palette.textFaint} letterSpacing={0.7} style={{ width: 160 }}>
              {t('LOT').toUpperCase()}
            </Mono>
            <Mono size={10} color={palette.textFaint} letterSpacing={0.7} style={{ width: 90 }}>
              {t('INSPECTOR').toUpperCase()}
            </Mono>
            <Mono size={10} color={palette.textFaint} letterSpacing={0.7} style={{ width: 76 }}>
              {t('STATE').toUpperCase()}
            </Mono>
          </View>
          <ScrollView style={{ flex: 1 }}>
            {filtered.map((ins, i, arr) => {
              const color = STATE_COLOR[ins.status] ?? palette.textMuted;
              const sel = ins.id === selected?.id;
              return (
                <Pressable
                  key={ins.id}
                  onPress={() => setSelectedId(ins.id)}
                  style={({ pressed }) => [
                    styles.tableRow,
                    {
                      backgroundColor: sel ? BRAND.amberSoft : 'transparent',
                      borderLeftColor: sel ? BRAND.amber : 'transparent',
                      borderBottomColor: palette.border,
                      borderBottomWidth:
                        i === arr.length - 1 ? 0 : StyleSheet.hairlineWidth,
                      opacity: pressed ? 0.85 : 1,
                    },
                  ]}>
                  <Mono size={11} color={palette.text} weight="700" style={{ width: 130 }}>
                    INSP-{String(ins.id).padStart(4, '0')}
                  </Mono>
                  <Text
                    style={[styles.cellPlan, { color: palette.text }]}
                    numberOfLines={1}>
                    {ins.plan?.name ?? t('Ad-hoc')}
                  </Text>
                  <Mono
                    size={10.5}
                    color={palette.textMuted}
                    style={{ width: 160 }}
                    numberOfLines={1}>
                    {ins.lot_number}
                  </Mono>
                  <Mono
                    size={10.5}
                    color={palette.textMuted}
                    style={{ width: 90 }}
                    numberOfLines={1}>
                    {(ins.inspector?.username ?? '—').toUpperCase()}
                  </Mono>
                  <View
                    style={[
                      styles.statePill,
                      { backgroundColor: `${color}22` },
                    ]}>
                    <Mono size={9.5} color={color} weight="700" letterSpacing={0.5}>
                      {(ins.status as InspectionStatus).toUpperCase()}
                    </Mono>
                  </View>
                </Pressable>
              );
            })}
            {filtered.length === 0 ? (
              <View style={{ padding: 24, alignItems: 'center' }}>
                <Mono size={11} color={palette.textFaint}>
                  {t('No inspections').toUpperCase()}
                </Mono>
              </View>
            ) : null}
          </ScrollView>
        </View>

        {/* RIGHT — detail */}
        <View
          style={[
            styles.panel,
            styles.detailPanel,
            { backgroundColor: palette.surface, borderColor: palette.border },
          ]}>
          {selected ? (
            <ScrollView contentContainerStyle={{ gap: 14 }}>
              <View>
                <Mono size={10.5} color={BRAND.amber} weight="700" letterSpacing={0.8}>
                  {(selected.status as InspectionStatus).toUpperCase()} ·{' '}
                  INSP-{String(selected.id).padStart(4, '0')}
                </Mono>
                <Text style={[styles.detailTitle, { color: palette.text }]}>
                  {selected.plan?.name ?? t('Ad-hoc inspection')}
                </Text>
                <Mono size={10.5} color={palette.textFaint} style={{ marginTop: 4 }}>
                  LOT {selected.lot_number.toUpperCase()}
                  {selected.quantity_received != null
                    ? ` · ${selected.quantity_received}`
                    : ''}
                </Mono>
              </View>

              <View
                style={[
                  styles.statusHero,
                  {
                    backgroundColor: BRAND.amberSoft,
                    borderColor: BRAND.amber,
                  },
                ]}>
                <Mono size={10} color="#7a5410" weight="700" letterSpacing={0.5}>
                  {selected.status === 'pending'
                    ? t('IN PROGRESS').toUpperCase()
                    : t('WAITING ON DISPOSITION').toUpperCase()}
                </Mono>
                <Mono size={11} color="#7a5410" letterSpacing={0.3} style={{ marginTop: 4 }}>
                  {(detail.data?.results ?? selected.results ?? []).length}{' '}
                  {t('STEPS COMPLETE').toUpperCase()}
                </Mono>
              </View>

              <Mono size={10.5} color={palette.textFaint} letterSpacing={0.8}>
                {t('STEPS').toUpperCase()} ·{' '}
                {(detail.data?.results ?? selected.results ?? []).length}
              </Mono>
              <View style={{ gap: 6 }}>
                {(detail.data?.results ?? selected.results ?? []).map((r, i) => (
                  <View
                    key={r.id}
                    style={[styles.stepRow, { backgroundColor: palette.surfaceAlt }]}>
                    <View
                      style={[
                        styles.stepCheck,
                        {
                          backgroundColor:
                            r.is_passed === true
                              ? palette.success
                              : r.is_passed === false
                                ? palette.danger
                                : palette.border,
                        },
                      ]}>
                      <FontAwesome
                        name={r.is_passed === false ? 'times' : 'check'}
                        size={11}
                        color="#fff"
                      />
                    </View>
                    <Text
                      style={[styles.stepName, { color: palette.text }]}
                      numberOfLines={1}>
                      {i + 1}. {r.criterion_name}
                    </Text>
                    <Mono
                      size={9}
                      color={
                        r.is_passed === true
                          ? palette.success
                          : r.is_passed === false
                            ? palette.danger
                            : palette.textFaint
                      }
                      weight="700"
                      letterSpacing={0.5}>
                      {r.is_passed === true
                        ? 'PASS'
                        : r.is_passed === false
                          ? 'FAIL'
                          : 'OPEN'}
                    </Mono>
                  </View>
                ))}
              </View>

              {canDispose && selected.status !== 'pending' ? (
                <>
                  <Mono size={10.5} color={palette.textFaint} letterSpacing={0.8}>
                    {t('DISPOSITION').toUpperCase()}
                  </Mono>
                  <View style={styles.dispoRow}>
                    {(
                      [
                        { action: 'accept', label: 'Release', color: '#1C9A55' },
                        { action: 'quarantine', label: 'Quarantine', color: '#7c3aed' },
                        { action: 'scrap', label: 'Scrap', color: '#D6442F' },
                      ] as const
                    ).map((opt) => (
                      <Pressable
                        key={opt.action}
                        onPress={() => applyDisposition(opt.action)}
                        disabled={dispositionMutation.isPending}
                        style={({ pressed }) => [
                          styles.dispoBtn,
                          {
                            backgroundColor: opt.color,
                            opacity: dispositionMutation.isPending
                              ? 0.5
                              : pressed
                                ? 0.9
                                : 1,
                          },
                        ]}>
                        <Mono size={11} color="#fff" weight="700" letterSpacing={0.5}>
                          {t(opt.label).toUpperCase()}
                        </Mono>
                      </Pressable>
                    ))}
                  </View>
                </>
              ) : null}

              <Pressable
                onPress={() =>
                  router.push(`/quality/inspections/${selected.id}` as never)
                }
                style={({ pressed }) => [
                  styles.openBtn,
                  {
                    backgroundColor: palette.text,
                    opacity: pressed ? 0.85 : 1,
                  },
                ]}>
                <Mono size={11} color={palette.background} weight="700" letterSpacing={0.5}>
                  {t('Open detail').toUpperCase()}
                </Mono>
              </Pressable>
            </ScrollView>
          ) : (
            <Mono
              size={11}
              color={palette.textFaint}
              style={{ textAlign: 'center', padding: 24 }}>
              {t('Pick an inspection to view details').toUpperCase()}
            </Mono>
          )}
        </View>
      </View>
    </TabletShell>
  );
}

const styles = StyleSheet.create({
  grid: { flex: 1, flexDirection: 'row', gap: 14, minHeight: 0 },
  panel: { borderRadius: 16, borderWidth: 1, padding: 14 },
  plansPanel: { width: 260, flexDirection: 'column' },
  tablePanel: { flex: 1, padding: 0, overflow: 'hidden' },
  detailPanel: { width: 400 },

  headerBtnGhost: {
    height: 38,
    paddingHorizontal: 14,
    borderRadius: 10,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },

  planRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 10,
    paddingHorizontal: 12,
    borderRadius: 8,
    borderWidth: 1,
  },
  planLabel: { flex: 1, fontSize: 12, minWidth: 0 },

  tableHead: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  tableRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderLeftWidth: 3,
  },
  cellPlan: { flex: 1, fontSize: 13, fontWeight: '600' },
  statePill: { paddingVertical: 2, paddingHorizontal: 6, borderRadius: 4 },

  detailTitle: { fontSize: 18, fontWeight: '700', marginTop: 4, letterSpacing: -0.3 },
  statusHero: { padding: 12, borderRadius: 10, borderWidth: 1 },
  stepRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    padding: 10,
    borderRadius: 8,
  },
  stepCheck: {
    width: 22,
    height: 22,
    borderRadius: 11,
    alignItems: 'center',
    justifyContent: 'center',
  },
  stepName: { flex: 1, fontSize: 12, fontWeight: '500' },
  dispoRow: { flexDirection: 'row', gap: 6 },
  dispoBtn: {
    flex: 1,
    height: 56,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  openBtn: {
    height: 44,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
