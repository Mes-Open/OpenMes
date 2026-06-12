import { FontAwesome } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Card } from '@/components/ui/Card';
import { Mono } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useFactories } from '@/hooks/queries/useOrgStructure';
import { useAdminLines, useWorkstations } from '@/hooks/queries/useLines';
import { useWorkstationTypes } from '@/hooks/queries/useWorkstationTypes';
import type { Factory, Division } from '@/api/orgStructure';
import type { Workstation } from '@/api/workstations';

type Tab = 'factories' | 'divisions' | 'lines' | 'workstations';

const TABS: { id: Tab; label: string }[] = [
  { id: 'factories',    label: 'Factories' },
  { id: 'divisions',    label: 'Divisions' },
  { id: 'lines',        label: 'Lines' },
  { id: 'workstations', label: 'Workstations' },
];

export function StructureHub() {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const router = useRouter();
  const { t } = useTranslation();

  const [tab, setTab] = useState<Tab>('lines');

  const factoriesQ = useFactories(true);
  const linesQ = useAdminLines({ include_inactive: true });
  // Workstations endpoint is per-line. Pull all workstations across the first
  // line for the Workstations tab; without a flat /workstations endpoint we
  // aggregate client-side.
  const linesData = linesQ.data ?? [];
  const firstLineId = linesData[0]?.id;
  const workstationsQ = useWorkstations(firstLineId, true);
  // Aggregate divisions across all factories so the count is meaningful even
  // before drilling into a factory.
  const divisions = useMemo<Division[]>(() => {
    return (factoriesQ.data ?? []).flatMap((f: Factory) => f.divisions ?? []);
  }, [factoriesQ.data]);
  // Surface workstation types as a fallback aggregate when there are no real
  // workstations to render in this demo environment.
  const workstationTypesQ = useWorkstationTypes({ include_inactive: true });

  const factoryCount = factoriesQ.data?.length ?? 0;
  const divisionCount = divisions.length;
  const lineCount = linesData.length;
  const workstationCount = workstationsQ.data?.length ?? 0;

  const isLoading = factoriesQ.isLoading || linesQ.isLoading;
  const isError = factoriesQ.isError || linesQ.isError;

  const onAdd = () => {
    if (tab === 'factories') router.push('/structure/factories/new' as never);
    else if (tab === 'lines') router.push('/structure/lines/new' as never);
    else if (tab === 'workstations' && firstLineId)
      router.push(`/structure/lines/${firstLineId}/workstations/new` as never);
    else if (tab === 'divisions' && factoriesQ.data?.[0])
      router.push(
        `/structure/factories/${factoriesQ.data[0].id}/divisions/new` as never,
      );
  };

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader
        title={t('Structure')}
        subtitle={`${t('PLANT HIERARCHY').toUpperCase()} · ${factoryCount} / ${divisionCount} / ${lineCount} / ${workstationCount}`}
        rightSlot={
          <Pressable
            onPress={onAdd}
            hitSlop={8}
            style={({ pressed }) => [
              styles.addBtn,
              { backgroundColor: palette.surfaceInverse, opacity: pressed ? 0.85 : 1 },
            ]}>
            <FontAwesome name="plus" size={14} color={scheme === 'dark' ? '#171715' : '#fff'} />
          </Pressable>
        }
      />

      <ScrollView contentContainerStyle={styles.scroll}>
        {/* Tabs — soft pill background with white active fill (matches design).
            Horizontally scrollable so longer labels never wrap. */}
        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.tabsRow}>
          <View
            style={[
              styles.tabsTrack,
              { backgroundColor: palette.surfaceAlt, borderColor: palette.border },
            ]}>
            {TABS.map((tabItem) => {
              const active = tabItem.id === tab;
              return (
                <Pressable
                  key={tabItem.id}
                  onPress={() => setTab(tabItem.id)}
                  style={[
                    styles.tabBtn,
                    active
                      ? {
                          backgroundColor: palette.surface,
                          boxShadow: '0px 1px 2px rgba(0, 0, 0, 0.08)',
                          elevation: 1,
                        }
                      : null,
                  ]}>
                  <Mono
                    size={11}
                    color={active ? palette.text : palette.textMuted}
                    weight="700"
                    letterSpacing={0.4}>
                    {t(tabItem.label).toUpperCase()}
                  </Mono>
                </Pressable>
              );
            })}
          </View>
        </ScrollView>

        {/* Breadcrumb */}
        <Breadcrumb
          tab={tab}
          factories={factoriesQ.data ?? []}
          divisions={divisions}
          lineCount={lineCount}
          workstationCount={workstationCount}
        />

        {/* List */}
        {isLoading ? (
          <LoadingState />
        ) : isError ? (
          <ErrorState error={factoriesQ.error ?? linesQ.error} onRetry={() => {
            factoriesQ.refetch();
            linesQ.refetch();
          }} />
        ) : (
          <Card style={{ padding: 0, overflow: 'hidden' }}>
            {tab === 'factories' ? (
              (factoriesQ.data ?? []).map((f, i, arr) => (
                <StructureRow
                  key={f.id}
                  badge={f.code}
                  title={f.name}
                  subtitle={`${f.divisions_count ?? 0} ${t('DIVISIONS').toUpperCase()}`}
                  active={f.is_active !== false}
                  last={i === arr.length - 1}
                  onPress={() => router.push(`/structure/factories/${f.id}` as never)}
                />
              ))
            ) : tab === 'divisions' ? (
              divisions.map((d, i, arr) => (
                <StructureRow
                  key={d.id}
                  badge={d.code}
                  title={d.name}
                  subtitle={`${d.lines_count ?? 0} ${t('LINES').toUpperCase()}${d.factory ? ` · ${d.factory.name.toUpperCase()}` : ''}`}
                  active={d.is_active !== false}
                  last={i === arr.length - 1}
                  onPress={() => router.push(`/structure/divisions/${d.id}` as never)}
                />
              ))
            ) : tab === 'lines' ? (
              linesData.map((l, i, arr) => (
                <StructureRow
                  key={l.id}
                  badge={l.code ?? `L-${String(l.id).padStart(2, '0')}`}
                  title={l.name}
                  subtitle={`${l.workstations_count ?? 0} ${t('WORKSTATIONS').toUpperCase()} · ${l.users_count ?? 0} ${t('OPERATORS').toUpperCase()}`}
                  active={l.is_active !== false}
                  last={i === arr.length - 1}
                  onPress={() => router.push(`/structure/lines/${l.id}` as never)}
                />
              ))
            ) : (
              <WorkstationsList
                workstations={workstationsQ.data ?? []}
                lineId={firstLineId}
                fallbackTypes={(workstationTypesQ.data ?? []).map((t) => ({
                  id: t.id,
                  code: t.code,
                  name: t.name,
                }))}
                onPressWs={(wsId) =>
                  firstLineId &&
                  router.push(
                    `/structure/lines/${firstLineId}/workstations/${wsId}` as never,
                  )
                }
              />
            )}
          </Card>
        )}

        {/* Soft-delete policy footer — title is mono uppercase, body uses
            regular text for readability (matches the design). */}
        <View style={[styles.policyCard, { backgroundColor: palette.surfaceAlt }]}>
          <Mono size={10} color={palette.textFaint} letterSpacing={0.8}>
            {t('Soft-delete policy').toUpperCase()}
          </Mono>
          <Text style={[styles.policyBody, { color: palette.textMuted }]}>
            {t(
              "Lines with active work orders can't be removed. Deactivate to hide from operator selection.",
            )}
          </Text>
        </View>
      </ScrollView>
    </View>
  );
}

function Breadcrumb({
  tab,
  factories,
  divisions,
  lineCount,
  workstationCount,
}: {
  tab: Tab;
  factories: Factory[];
  divisions: Division[];
  lineCount: number;
  workstationCount: number;
}) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  const FACTORIES = t('FACTORIES').toUpperCase();
  const DIVISIONS = t('DIVISIONS').toUpperCase();
  const LINES = t('LINES').toUpperCase();
  const WORKSTATIONS = t('WORKSTATIONS').toUpperCase();

  // Render a contextual breadcrumb based on the active tab. The current
  // implementation shows aggregate counts (no drill-down state); when a
  // factory/division filter is added later, replace the segments with the
  // selected names.
  const segments = useMemo<string[]>(() => {
    switch (tab) {
      case 'factories':
        return [`${factories.length} ${FACTORIES}`];
      case 'divisions':
        return [
          factories.length === 1 ? factories[0].name.toUpperCase() : `${factories.length} ${FACTORIES}`,
          `${divisions.length} ${DIVISIONS}`,
        ];
      case 'lines':
        return [
          factories.length === 1 ? factories[0].name.toUpperCase() : `${factories.length} ${FACTORIES}`,
          `${divisions.length} ${DIVISIONS}`,
          `${lineCount} ${LINES}`,
        ];
      case 'workstations':
        return [`${lineCount} ${LINES}`, `${workstationCount} ${WORKSTATIONS}`];
    }
  }, [tab, factories, divisions, lineCount, workstationCount, FACTORIES, DIVISIONS, LINES, WORKSTATIONS]);

  return (
    <View style={styles.breadcrumb}>
      {segments.map((s, i) => (
        <View key={`${s}-${i}`} style={styles.breadcrumbItem}>
          {i > 0 ? (
            <FontAwesome
              name="angle-right"
              size={10}
              color={palette.textFaint}
              style={{ marginHorizontal: 6 }}
            />
          ) : null}
          <Mono
            size={11}
            color={i === segments.length - 1 ? palette.text : palette.textFaint}
            weight={i === segments.length - 1 ? '700' : '500'}
            letterSpacing={0.6}>
            {s}
          </Mono>
        </View>
      ))}
    </View>
  );
}

function StructureRow({
  badge,
  title,
  subtitle,
  active,
  status,
  last,
  onPress,
}: {
  badge: string;
  title: string;
  subtitle?: string;
  active: boolean;
  status?: { label: string; color: string };
  last?: boolean;
  onPress: () => void;
}) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const dot = active ? palette.success : palette.textFaint;

  return (
    <Pressable onPress={onPress} style={({ pressed }) => ({ opacity: pressed ? 0.85 : 1 })}>
      <View
        style={[
          styles.row,
          last
            ? null
            : { borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: palette.border },
        ]}>
        <View
          style={[
            styles.badge,
            {
              backgroundColor: active ? palette.surfaceInverse : palette.surfaceAlt,
            },
          ]}>
          <Mono
            size={11}
            color={
              active
                ? scheme === 'dark'
                  ? '#171715'
                  : '#fff'
                : palette.textFaint
            }
            weight="700"
            letterSpacing={0.4}>
            {badge.slice(0, 5).toUpperCase()}
          </Mono>
        </View>
        <View style={{ flex: 1, minWidth: 0 }}>
          <View style={styles.titleRow}>
            <Text style={[styles.rowTitle, { color: palette.text }]} numberOfLines={1}>
              {title}
            </Text>
            {status ? (
              <View style={[styles.statusPill, { backgroundColor: '#fbe9c8' }]}>
                <Mono size={9.5} color={status.color} weight="700" letterSpacing={0.6}>
                  {status.label.toUpperCase()}
                </Mono>
              </View>
            ) : null}
          </View>
          {subtitle ? (
            <Mono size={11} color={palette.textFaint} style={{ marginTop: 3 }}>
              {subtitle}
            </Mono>
          ) : null}
        </View>
        <View style={[styles.dot, { backgroundColor: dot }]} />
        <FontAwesome name="chevron-right" size={11} color={palette.textFaint} />
      </View>
    </Pressable>
  );
}

function WorkstationsList({
  workstations,
  lineId,
  fallbackTypes,
  onPressWs,
}: {
  workstations: Workstation[];
  lineId?: number;
  fallbackTypes: Array<{ id: number; code: string; name: string }>;
  onPressWs: (id: number) => void;
}) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();

  if (workstations.length > 0 && lineId != null) {
    return (
      <>
        {workstations.map((w, i, arr) => (
          <StructureRow
            key={w.id}
            badge={w.code}
            title={w.name}
            subtitle={`${w.workstation_type ?? t('GENERAL').toUpperCase()} · ${w.workers_count ?? 0} ${t('WORKERS').toUpperCase()}`}
            active={w.is_active !== false}
            last={i === arr.length - 1}
            onPress={() => onPressWs(w.id)}
          />
        ))}
      </>
    );
  }

  if (fallbackTypes.length > 0) {
    return (
      <>
        <View style={styles.fallbackNote}>
          <FontAwesome name="info-circle" size={11} color={palette.textFaint} />
          <Mono size={10} color={palette.textFaint} letterSpacing={0.6}>
            {t('Showing workstation types (no line-scoped list available)').toUpperCase()}
          </Mono>
        </View>
        {fallbackTypes.map((ft, i, arr) => (
          <StructureRow
            key={ft.id}
            badge={ft.code}
            title={ft.name}
            subtitle={t('Workstation type').toUpperCase()}
            active
            last={i === arr.length - 1}
            onPress={() => null}
          />
        ))}
      </>
    );
  }

  return (
    <View style={{ padding: 18 }}>
      <Mono size={11} color={palette.textFaint}>{t('No workstations configured').toUpperCase()}</Mono>
    </View>
  );
}

const styles = StyleSheet.create({
  addBtn: {
    width: 36,
    height: 36,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },

  scroll: { padding: 18, gap: 14, paddingBottom: 32 },

  // Horizontal scroll container — lets long labels overflow gracefully when
  // narrow phones can't fit all 4 tabs side-by-side.
  tabsRow: { flexDirection: 'row' },
  tabsTrack: {
    flexDirection: 'row',
    padding: 4,
    gap: 4,
    borderRadius: 10,
    borderWidth: StyleSheet.hairlineWidth,
  },
  tabBtn: {
    paddingVertical: 8,
    paddingHorizontal: 14,
    borderRadius: 7,
    alignItems: 'center',
    justifyContent: 'center',
    minWidth: 80,
  },

  breadcrumb: { flexDirection: 'row', alignItems: 'center', flexWrap: 'wrap', paddingHorizontal: 4 },
  breadcrumbItem: { flexDirection: 'row', alignItems: 'center' },

  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingVertical: 12,
    paddingHorizontal: 14,
  },
  badge: {
    width: 44,
    height: 36,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  titleRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  rowTitle: { fontSize: 14, fontWeight: '600', letterSpacing: -0.2 },
  statusPill: { paddingVertical: 2, paddingHorizontal: 6, borderRadius: 4 },
  dot: { width: 8, height: 8, borderRadius: 4 },

  fallbackNote: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 10,
    paddingHorizontal: 14,
  },

  policyCard: { padding: 14, borderRadius: 12, gap: 6 },
  policyBody: { fontSize: 13, lineHeight: 19 },
});
