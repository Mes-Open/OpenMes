/**
 * Sites & areas — the ISA-95 structure catalog above Lines. Mirrors the web
 * admin Sites (Pages/admin/sites/Index.jsx) and Areas (Pages/admin/areas/
 * Index.jsx) tables via the shared DataTable (search + Columns menu +
 * pagination); a view switcher toggles between the two column sets and
 * datasets, matching each web table's columns (Code / Name / Company / City /
 * Areas / Status for sites, Code / Name / Site / Lines / Status for areas)
 * and per-row actions (edit / toggle-active / delete). "Toggle active" reuses
 * the existing update mutation (no separate toggle endpoint on the mobile
 * REST API). Data via the existing REST hooks; navigation targets unchanged.
 */
import { useMemo, useState } from 'react';
import { Alert, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts, radius } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import {
  useAreas,
  useDeleteArea,
  useDeleteSite,
  useSites,
  useUpdateArea,
  useUpdateSite,
} from '@/hooks/queries/useStructureIsa95';
import type { Site, Area } from '@/api/sites';

type View_ = 'sites' | 'areas';

export function SitesAreasScreen() {
  const router = useRouter();
  const { t } = useTranslation();
  const [view, setView] = useState<View_>('sites');

  const sitesQ = useSites();
  const areasQ = useAreas();
  const sites = sitesQ.data ?? [];
  const areas = areasQ.data ?? [];

  const toggleSite = useUpdateSite();
  const deleteSite = useDeleteSite();
  const toggleArea = useUpdateArea();
  const deleteArea = useDeleteArea();

  const options = useMemo(
    () => [
      { value: 'sites', label: t('Sites') },
      { value: 'areas', label: t('Areas') },
    ],
    [t],
  );

  const active = view === 'sites' ? sitesQ : areasQ;

  const onToggleSite = (site: Site) =>
    toggleSite.mutate(
      {
        id: site.id,
        input: {
          name: site.name,
          code: site.code,
          company_id: site.company_id ?? null,
          description: site.description ?? null,
          address: site.address ?? null,
          city: site.city ?? null,
          country: site.country ?? null,
          timezone: site.timezone ?? null,
          is_active: !site.is_active,
        },
      },
      { onError: (e: Error) => Alert.alert(t('Could not update'), e.message) },
    );

  const onDeleteSite = (site: Site) =>
    Alert.alert(t('Delete site'), t('Delete "{{name}}"?', { name: site.name }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () => deleteSite.mutate(site.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
      },
    ]);

  const onToggleArea = (area: Area) =>
    toggleArea.mutate(
      {
        id: area.id,
        input: {
          name: area.name,
          code: area.code,
          site_id: area.site_id,
          description: area.description ?? null,
          is_active: !area.is_active,
        },
      },
      { onError: (e: Error) => Alert.alert(t('Could not update'), e.message) },
    );

  const onDeleteArea = (area: Area) =>
    Alert.alert(t('Delete area'), t('Delete "{{name}}"?', { name: area.name }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () => deleteArea.mutate(area.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
      },
    ]);

  return (
    <ScrollView
      style={styles.screen}
      contentContainerStyle={styles.content}
      refreshControl={<RefreshControl refreshing={active.isFetching} onRefresh={active.refetch} tintColor={colors.accent} />}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Sites & areas')}</Text>
        <View style={{ flex: 1 }} />
        <View style={{ width: 130 }}>
          <Dropdown value={view} onChange={(v) => setView(v as View_)} options={options} />
        </View>
        <Button
          title={view === 'sites' ? t('New site') : t('New area')}
          size="sm"
          onPress={() =>
            router.push((view === 'sites' ? '/structure/sites/new' : '/structure/areas/new') as never)
          }
        />
      </View>

      {active.isLoading && !active.data ? (
        <LoadingState />
      ) : active.isError && !active.data ? (
        <ErrorState error={active.error} onRetry={active.refetch} />
      ) : view === 'sites' ? (
        <DataTable<Site>
          data={sites}
          searchPlaceholder={t('Search…')}
          columnsLabel={t('Columns')}
          columnsMenuLabel={t('Toggle columns')}
          searchKeys={['code', 'name', 'city']}
          emptyText={t('No sites yet.')}
          onRowPress={(s) => router.push(`/structure/sites/${s.id}/edit` as never)}
          columns={[
            { key: 'code', label: t('Code'), width: 80, render: (s) => <Mono size={11} color={colors.muted}>{s.code}</Mono> },
            { key: 'name', label: t('Name'), flex: 1.2, render: (s) => <Text numberOfLines={1} style={styles.name}>{s.name}</Text> },
            { key: 'company', label: t('Company'), flex: 1, render: (s) => s.company?.name ?? '—' },
            { key: 'city', label: t('City'), flex: 0.8, render: (s) => s.city ?? '—' },
            { key: 'areas_count', label: t('Areas'), width: 70, render: (s) => String(s.areas_count ?? 0) },
            {
              key: 'is_active',
              label: t('Status'),
              width: 90,
              render: (s) => (
                <StatusPill status={s.is_active ? 'IN_PROGRESS' : 'CANCELLED'} label={s.is_active ? t('Active') : t('Inactive')} />
              ),
            },
          ]}
          actions={(s) => [
            { label: t('Edit'), icon: 'edit', onPress: () => router.push(`/structure/sites/${s.id}/edit` as never) },
            {
              label: s.is_active ? t('Deactivate') : t('Activate'),
              icon: s.is_active ? 'deactivate' : 'activate',
              onPress: () => onToggleSite(s),
            },
            { label: t('Delete'), icon: 'delete', onPress: () => onDeleteSite(s) },
          ]}
        />
      ) : (
        <DataTable<Area>
          data={areas}
          searchPlaceholder={t('Search…')}
          columnsLabel={t('Columns')}
          columnsMenuLabel={t('Toggle columns')}
          searchKeys={['code', 'name']}
          emptyText={t('No areas yet.')}
          onRowPress={(a) => router.push(`/structure/areas/${a.id}/edit` as never)}
          columns={[
            { key: 'code', label: t('Code'), width: 80, render: (a) => <Mono size={11} color={colors.muted}>{a.code}</Mono> },
            { key: 'name', label: t('Name'), flex: 1.2, render: (a) => <Text numberOfLines={1} style={styles.name}>{a.name}</Text> },
            { key: 'site', label: t('Site'), flex: 1, render: (a) => a.site?.name ?? '—' },
            { key: 'lines_count', label: t('Lines'), width: 70, render: (a) => String(a.lines_count ?? 0) },
            {
              key: 'is_active',
              label: t('Status'),
              width: 90,
              render: (a) => (
                <StatusPill status={a.is_active ? 'IN_PROGRESS' : 'CANCELLED'} label={a.is_active ? t('Active') : t('Inactive')} />
              ),
            },
          ]}
          actions={(a) => [
            { label: t('Edit'), icon: 'edit', onPress: () => router.push(`/structure/areas/${a.id}/edit` as never) },
            {
              label: a.is_active ? t('Deactivate') : t('Activate'),
              icon: a.is_active ? 'deactivate' : 'activate',
              onPress: () => onToggleArea(a),
            },
            { label: t('Delete'), icon: 'delete', onPress: () => onDeleteArea(a) },
          ]}
        />
      )}

      <View style={styles.helpBlock}>
        <Mono size={10.5} color={colors.muted} letterSpacing={0.3}>
          ⓘ {t('Areas now live between Sites and Lines (ISA-95 compliance). Existing lines migrated to a default area per site.')}
        </Mono>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, paddingBottom: 32 },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, marginBottom: 14 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  name: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
  helpBlock: {
    marginTop: 16,
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.md,
    padding: 12,
  },
});
