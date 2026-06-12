import { FontAwesome } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import { TabletShell } from '@/components/tablet/TabletShell';
import { Mono } from '@/components/ui/Mono';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import {
  useAnomalyReasons,
  useCompanies,
  useCostSources,
  useSubassemblies,
} from '@/hooks/queries/useOps';
import { useLotSequences } from '@/hooks/queries/useLot';
import { useWorkstationTypes } from '@/hooks/queries/useWorkstationTypes';
import { useProductTypes } from '@/hooks/queries/useProductTypes';
import type { AnomalyReason } from '@/api/ops';

type EntityKey =
  | 'anomaly-reasons'
  | 'cost-sources'
  | 'companies'
  | 'subassemblies'
  | 'lot-sequences'
  | 'product-types'
  | 'workstation-types';

const ENTITIES: { key: EntityKey; label: string; icon: React.ComponentProps<typeof FontAwesome>['name']; route: string }[] = [
  { key: 'anomaly-reasons', label: 'Anomaly reasons', icon: 'flag', route: '/(drawer)/admin/anomaly-reasons' },
  { key: 'cost-sources', label: 'Cost sources', icon: 'archive', route: '/(drawer)/admin/cost-sources' },
  { key: 'companies', label: 'Companies', icon: 'building', route: '/(drawer)/admin/companies' },
  { key: 'subassemblies', label: 'Subassemblies', icon: 'cubes', route: '/(drawer)/admin/subassemblies' },
  { key: 'lot-sequences', label: 'LOT sequences', icon: 'qrcode', route: '/(drawer)/admin/lot-sequences' },
  { key: 'product-types', label: 'Product types', icon: 'tag', route: '/(drawer)/production/product-types' },
  { key: 'workstation-types', label: 'Workstation types', icon: 'cog', route: '/(drawer)/structure/workstation-types' },
];

const SEV_COLORS: Record<string, string> = {
  minor: BRAND.amber,
  major: '#D6442F',
  scrap: '#7c3aed',
};

const SEV_OPTIONS: { id: string; label: string; color: string }[] = [
  { id: '', label: 'Cosmetic', color: '#9B9892' },
  { id: 'minor', label: 'Minor', color: BRAND.amber },
  { id: 'major', label: 'Major', color: '#D6442F' },
  { id: 'scrap', label: 'Scrap', color: '#7c3aed' },
];

/**
 * Tablet Catalog Admin — 3-pane: entity sidebar + center table + right edit
 * panel. Only the Anomaly Reasons table is wired today (it's the entity with
 * the richest schema — severity + active + code + name). Other entities show
 * their count in the sidebar and link to their mobile list via the router.
 */
export function TabletCatalogAdmin() {
  const router = useRouter();
  const { t } = useTranslation();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  const [selectedEntity, setSelectedEntity] = useState<EntityKey>('anomaly-reasons');
  const [selectedReasonId, setSelectedReasonId] = useState<number | null>(null);

  const reasonsQ = useAnomalyReasons({ include_inactive: true });
  const costSourcesQ = useCostSources(true);
  const companiesQ = useCompanies();
  const subassembliesQ = useSubassemblies();
  const lotSeqsQ = useLotSequences();
  const productTypesQ = useProductTypes();
  const workstationTypesQ = useWorkstationTypes();

  const counts: Record<EntityKey, number> = {
    'anomaly-reasons': reasonsQ.data?.length ?? 0,
    'cost-sources': costSourcesQ.data?.length ?? 0,
    companies: companiesQ.data?.length ?? 0,
    subassemblies: subassembliesQ.data?.length ?? 0,
    'lot-sequences': lotSeqsQ.data?.length ?? 0,
    'product-types': productTypesQ.data?.length ?? 0,
    'workstation-types': workstationTypesQ.data?.length ?? 0,
  };

  const reasons = reasonsQ.data ?? [];
  const selectedReason: AnomalyReason | null = useMemo(
    () => reasons.find((r) => r.id === selectedReasonId) ?? reasons[0] ?? null,
    [reasons, selectedReasonId],
  );

  const goToEntity = (key: EntityKey) => {
    setSelectedEntity(key);
    if (key !== 'anomaly-reasons') {
      const e = ENTITIES.find((x) => x.key === key);
      if (e) router.push(e.route as never);
    }
  };

  return (
    <TabletShell
      eyebrow={`${t('CATALOG ADMIN').toUpperCase()} · ${ENTITIES.length} ${t('ENTITIES').toUpperCase()}`}
      title={t('Reference data')}
      right={
        <Pressable
          onPress={() => router.push('/admin/anomaly-reasons/new' as never)}
          style={({ pressed }) => [
            styles.newBtn,
            { backgroundColor: BRAND.amber, opacity: pressed ? 0.9 : 1 },
          ]}>
          <FontAwesome name="plus" size={14} color="#1a1208" />
          <Mono size={12} color="#1a1208" weight="700" letterSpacing={0.5}>
            {t('NEW REASON').toUpperCase()}
          </Mono>
        </Pressable>
      }>
      <View style={styles.grid}>
        {/* LEFT — entity nav */}
        <View style={[styles.panel, styles.navPanel, { backgroundColor: palette.surface, borderColor: palette.border }]}>
          <Mono size={10.5} color={palette.textFaint} letterSpacing={0.8}>
            {t('CATALOG').toUpperCase()}
          </Mono>
          <ScrollView style={{ flex: 1, marginTop: 8 }} contentContainerStyle={{ gap: 4 }}>
            {ENTITIES.map((e) => {
              const on = e.key === selectedEntity;
              return (
                <Pressable
                  key={e.key}
                  onPress={() => goToEntity(e.key)}
                  style={({ pressed }) => [
                    styles.navItem,
                    {
                      backgroundColor: on ? BRAND.amberSoft : 'transparent',
                      borderColor: on ? BRAND.amber : 'transparent',
                      opacity: pressed ? 0.85 : 1,
                    },
                  ]}>
                  <FontAwesome
                    name={e.icon}
                    size={15}
                    color={on ? BRAND.amber : palette.textMuted}
                  />
                  <Text
                    style={[
                      styles.navLabel,
                      { color: palette.text, fontWeight: on ? '700' : '500' },
                    ]}>
                    {t(e.label)}
                  </Text>
                  <Mono
                    size={11}
                    color={on ? BRAND.amber : palette.textFaint}
                    weight="700">
                    {counts[e.key]}
                  </Mono>
                </Pressable>
              );
            })}
          </ScrollView>
        </View>

        {/* CENTER — anomaly reasons table */}
        <View style={[styles.panel, styles.tablePanel, { backgroundColor: palette.surface, borderColor: palette.border }]}>
          <View style={[styles.tableHead, { borderBottomColor: palette.border }]}>
            <Mono size={10} color={palette.textFaint} letterSpacing={0.7} style={{ width: 110 }}>
              {t('CODE').toUpperCase()}
            </Mono>
            <Mono size={10} color={palette.textFaint} letterSpacing={0.7} style={{ flex: 1 }}>
              {t('NAME').toUpperCase()}
            </Mono>
            <Mono size={10} color={palette.textFaint} letterSpacing={0.7} style={{ width: 80 }}>
              {t('SEVERITY').toUpperCase()}
            </Mono>
            <Mono size={10} color={palette.textFaint} letterSpacing={0.7} style={{ width: 60, textAlign: 'right' }}>
              {t('STATE').toUpperCase()}
            </Mono>
          </View>
          <ScrollView style={{ flex: 1 }}>
            {reasons.map((r, i) => {
              const sel = r.id === selectedReason?.id;
              const sev = (r.category ?? '').toLowerCase();
              const sevColor = SEV_COLORS[sev] ?? palette.textMuted;
              return (
                <Pressable
                  key={r.id}
                  onPress={() => setSelectedReasonId(r.id)}
                  style={({ pressed }) => [
                    styles.tableRow,
                    {
                      backgroundColor: sel ? BRAND.amberSoft : 'transparent',
                      borderLeftColor: sel ? BRAND.amber : 'transparent',
                      borderBottomColor: palette.border,
                      borderBottomWidth: i === reasons.length - 1 ? 0 : StyleSheet.hairlineWidth,
                      opacity: pressed ? 0.85 : 1,
                    },
                  ]}>
                  <Mono size={11} color={palette.text} weight="700" style={{ width: 110 }}>
                    {r.code}
                  </Mono>
                  <Text
                    style={[styles.rowName, { color: palette.text }]}
                    numberOfLines={1}>
                    {r.name}
                  </Text>
                  <View style={{ width: 80, flexDirection: 'row' }}>
                    {sev ? (
                      <View style={[styles.sevPill, { backgroundColor: `${sevColor}22` }]}>
                        <Mono size={9.5} color={sevColor} weight="700" letterSpacing={0.5}>
                          {sev.toUpperCase()}
                        </Mono>
                      </View>
                    ) : null}
                  </View>
                  <View style={{ width: 60, alignItems: 'flex-end' }}>
                    <View
                      style={[
                        styles.statePill,
                        {
                          backgroundColor: r.is_active ? `${palette.success}22` : palette.surfaceAlt,
                        },
                      ]}>
                      <Mono
                        size={9.5}
                        color={r.is_active ? palette.success : palette.textFaint}
                        weight="700"
                        letterSpacing={0.5}>
                        {r.is_active ? t('ON').toUpperCase() : t('OFF').toUpperCase()}
                      </Mono>
                    </View>
                  </View>
                </Pressable>
              );
            })}
            {reasons.length === 0 ? (
              <View style={{ padding: 40, alignItems: 'center' }}>
                <Mono size={11} color={palette.textFaint}>
                  {t('No entries').toUpperCase()}
                </Mono>
              </View>
            ) : null}
          </ScrollView>
        </View>

        {/* RIGHT — detail/edit */}
        <View style={[styles.panel, styles.editPanel, { backgroundColor: palette.surface, borderColor: palette.border }]}>
          <ScrollView contentContainerStyle={{ gap: 14 }} showsVerticalScrollIndicator={false}>
            {selectedReason ? (
              <>
                <View>
                  <Mono size={10.5} color={BRAND.amber} letterSpacing={0.8} weight="700">
                    {t('EDITING').toUpperCase()} · {selectedReason.code}
                  </Mono>
                  <Text style={[styles.editTitle, { color: palette.text }]}>
                    {selectedReason.name}
                  </Text>
                  {selectedReason.description ? (
                    <Mono size={10.5} color={palette.textFaint} style={{ marginTop: 4 }}>
                      {selectedReason.description}
                    </Mono>
                  ) : null}
                </View>

                <Field label={t('Code')} palette={palette}>
                  <Mono size={13} color={palette.text} weight="700">
                    {selectedReason.code}
                  </Mono>
                </Field>

                <View>
                  <Mono size={10} color={palette.textFaint} letterSpacing={0.7}>
                    {t('SEVERITY').toUpperCase()}
                  </Mono>
                  <View style={styles.sevGrid}>
                    {SEV_OPTIONS.map((s) => {
                      const on = (selectedReason.category ?? '').toLowerCase() === s.id;
                      return (
                        <View
                          key={s.label}
                          style={[
                            styles.sevOption,
                            {
                              backgroundColor: on ? `${s.color}22` : palette.surface,
                              borderColor: on ? s.color : palette.border,
                            },
                          ]}>
                          <Mono
                            size={10.5}
                            color={on ? s.color : palette.textMuted}
                            weight="700"
                            letterSpacing={0.4}>
                            {t(s.label).toUpperCase()}
                          </Mono>
                        </View>
                      );
                    })}
                  </View>
                </View>

                <Field label={t('Category label')} palette={palette}>
                  <Text style={{ color: palette.text, fontSize: 13, fontWeight: '600' }}>
                    {selectedReason.category ?? '—'}
                  </Text>
                </Field>

                {/* Active toggle */}
                <View style={[styles.toggleBlock, { backgroundColor: palette.surfaceAlt }]}>
                  <View style={{ flex: 1 }}>
                    <Text style={{ color: palette.text, fontSize: 13, fontWeight: '600' }}>
                      {t('Active')}
                    </Text>
                    <Mono size={10} color={palette.textFaint} style={{ marginTop: 2 }}>
                      {t('Available to operators')}
                    </Mono>
                  </View>
                  <View
                    style={[
                      styles.switchTrack,
                      {
                        backgroundColor: selectedReason.is_active ? palette.success : palette.border,
                      },
                    ]}>
                    <View
                      style={[
                        styles.switchThumb,
                        { left: selectedReason.is_active ? 20 : 2 },
                      ]}
                    />
                  </View>
                </View>

                <View style={styles.actionRow}>
                  <Pressable
                    style={({ pressed }) => [
                      styles.btnDanger,
                      { borderColor: palette.danger, opacity: pressed ? 0.8 : 1 },
                    ]}>
                    <Mono size={11} color={palette.danger} weight="700" letterSpacing={0.5}>
                      {t('DELETE').toUpperCase()}
                    </Mono>
                  </Pressable>
                  <Pressable
                    onPress={() =>
                      router.push(`/admin/anomaly-reasons/${selectedReason.id}` as never)
                    }
                    style={({ pressed }) => [
                      styles.btnPrimary,
                      { backgroundColor: BRAND.amber, opacity: pressed ? 0.9 : 1 },
                    ]}>
                    <Mono size={11} color="#1a1208" weight="700" letterSpacing={0.5}>
                      {t('EDIT').toUpperCase()}
                    </Mono>
                  </Pressable>
                </View>
              </>
            ) : (
              <View style={{ padding: 40, alignItems: 'center' }}>
                <Mono size={11} color={palette.textFaint}>
                  {t('Pick an entry to view details').toUpperCase()}
                </Mono>
              </View>
            )}
          </ScrollView>
        </View>
      </View>
    </TabletShell>
  );
}

function Field({
  label,
  palette,
  children,
}: {
  label: string;
  palette: typeof Colors.light;
  children: React.ReactNode;
}) {
  return (
    <View>
      <Mono size={10} color={palette.textFaint} letterSpacing={0.7}>
        {label.toUpperCase()}
      </Mono>
      <View
        style={[
          styles.fieldBox,
          { backgroundColor: palette.surfaceAlt, borderColor: palette.border },
        ]}>
        {children}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  grid: { flex: 1, flexDirection: 'row', gap: 14, minHeight: 0 },
  panel: { borderRadius: 16, borderWidth: 1, padding: 14 },
  navPanel: { width: 240 },
  tablePanel: { flex: 1, padding: 0, overflow: 'hidden' },
  editPanel: { width: 380 },

  newBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    height: 38,
    paddingHorizontal: 14,
    borderRadius: 10,
  },

  navItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingVertical: 10,
    paddingHorizontal: 12,
    borderRadius: 8,
    borderWidth: 1,
  },
  navLabel: { flex: 1, fontSize: 13 },

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
  rowName: { flex: 1, fontSize: 13, fontWeight: '600' },
  sevPill: { paddingVertical: 2, paddingHorizontal: 6, borderRadius: 3, alignSelf: 'flex-start' },
  statePill: { paddingVertical: 2, paddingHorizontal: 5, borderRadius: 3 },

  editTitle: { fontSize: 20, fontWeight: '700', marginTop: 4, letterSpacing: -0.3 },
  fieldBox: { marginTop: 6, paddingVertical: 10, paddingHorizontal: 12, borderRadius: 8, borderWidth: 1 },

  sevGrid: { marginTop: 6, flexDirection: 'row', gap: 6 },
  sevOption: {
    flex: 1,
    paddingVertical: 10,
    borderRadius: 8,
    borderWidth: 1,
    alignItems: 'center',
  },

  toggleBlock: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 12,
    borderRadius: 10,
  },
  switchTrack: { width: 40, height: 22, borderRadius: 11, position: 'relative' },
  switchThumb: {
    position: 'absolute',
    top: 2,
    width: 18,
    height: 18,
    borderRadius: 9,
    backgroundColor: '#fff',
  },

  actionRow: { flexDirection: 'row', gap: 8 },
  btnDanger: {
    flex: 1,
    height: 44,
    borderRadius: 10,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  btnPrimary: {
    flex: 1,
    height: 44,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
