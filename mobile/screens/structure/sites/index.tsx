import { FontAwesome } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import { Card } from '@/components/ui/Card';
import { Mono } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/StateViews';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useAreas, useSites } from '@/hooks/queries/useStructureIsa95';
import type { Site, Area } from '@/api/sites';

type Tab = 'sites' | 'areas';

/**
 * ISA-95 structure tabs — Sites and Areas live above Lines. Tapping a site
 * scopes the Areas tab to that site. Lines tab links to the existing Factories
 * screen since lines already have a structure surface there.
 */
export function SitesAreasScreen() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  const [tab, setTab] = useState<Tab>('sites');

  const sitesQ = useSites();
  const areasQ = useAreas();
  const sites = sitesQ.data ?? [];
  const areas = areasQ.data ?? [];

  const totalAreas = useMemo(
    () => sites.reduce((sum, s) => sum + (s.areas_count ?? 0), 0),
    [sites],
  );

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader
        back
        title={t('Sites & areas')}
        subtitle={`ISA-95 · ${sites.length} ${t('sites').toUpperCase()} · ${totalAreas || areas.length} ${t('areas').toUpperCase()}`}
      />
      {sitesQ.isLoading ? (
        <LoadingState />
      ) : sitesQ.isError ? (
        <ErrorState error={sitesQ.error} onRetry={sitesQ.refetch} />
      ) : (
        <ScrollView contentContainerStyle={styles.container}>
          {/* Segmented tab control */}
          <View style={[styles.tabBar, { backgroundColor: palette.surfaceAlt }]}>
            {(['sites', 'areas'] as Tab[]).map((id) => {
              const on = id === tab;
              return (
                <Pressable
                  key={id}
                  onPress={() => setTab(id)}
                  style={[
                    styles.tab,
                    on
                      ? {
                          backgroundColor: palette.surface,
                          boxShadow: '0px 1px 2px rgba(0, 0, 0, 0.06)',
                        }
                      : null,
                  ]}>
                  <Mono
                    size={11}
                    weight="600"
                    color={on ? palette.text : palette.textMuted}
                    letterSpacing={0.5}>
                    {t(id === 'sites' ? 'SITES' : 'AREAS').toUpperCase()}
                  </Mono>
                </Pressable>
              );
            })}
          </View>

          <Mono size={11} color={palette.textFaint} letterSpacing={0.8}>
            {tab === 'sites'
              ? 'SITES · ISA-95 ENTERPRISE › SITE'
              : 'AREAS · ISA-95 SITE › AREA'}
          </Mono>

          {tab === 'sites' ? (
            sites.length === 0 ? (
              <EmptyState title={t('No sites')} />
            ) : (
              <Card style={{ padding: 0 }}>
                {sites.map((s, i, arr) => (
                  <SiteRow
                    key={s.id}
                    site={s}
                    last={i === arr.length - 1}
                    palette={palette}
                    onPress={() => router.push(`/structure/sites/${s.id}/edit` as never)}
                  />
                ))}
              </Card>
            )
          ) : areas.length === 0 ? (
            <EmptyState title={t('No areas')} />
          ) : (
            <Card style={{ padding: 0 }}>
              {areas.map((a, i, arr) => (
                <AreaRow
                  key={a.id}
                  area={a}
                  last={i === arr.length - 1}
                  palette={palette}
                  onPress={() => router.push(`/structure/areas/${a.id}/edit` as never)}
                />
              ))}
            </Card>
          )}

          {/* + NEW SITE / AREA */}
          <Pressable
            onPress={() =>
              router.push(
                tab === 'sites' ? '/structure/sites/new' : '/structure/areas/new' as never,
              )
            }
            style={({ pressed }) => [
              styles.addBtn,
              { borderColor: palette.border, opacity: pressed ? 0.7 : 1 },
            ]}>
            <FontAwesome name="plus" size={12} color={palette.text} />
            <Mono size={11} color={palette.text} weight="700" letterSpacing={0.5}>
              {tab === 'sites' ? t('NEW SITE') : t('NEW AREA')}
            </Mono>
          </Pressable>

          <View style={[styles.helpBlock, { backgroundColor: palette.surfaceAlt }]}>
            <Mono size={11} color={palette.textMuted} letterSpacing={0.3}>
              ⓘ {t('Areas now live between Sites and Lines (ISA-95 compliance). Existing lines migrated to a default area per site.')}
            </Mono>
          </View>
        </ScrollView>
      )}
    </View>
  );
}

function SiteRow({
  site,
  last,
  palette,
  onPress,
}: {
  site: Site;
  last: boolean;
  palette: typeof Colors.light;
  onPress?: () => void;
}) {
  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [
        styles.row,
        last
          ? null
          : { borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: palette.border },
        site.is_active ? null : { opacity: 0.55 },
        pressed ? { opacity: 0.7 } : null,
      ]}>
      <View style={[styles.iconBadge, { backgroundColor: palette.surfaceAlt }]}>
        <FontAwesome name="building" size={20} color={palette.textMuted} />
      </View>
      <View style={{ flex: 1, minWidth: 0 }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
          <Text style={[styles.rowTitle, { color: palette.text }]} numberOfLines={1}>
            {site.name}
          </Text>
          {site.country ? (
            <View style={[styles.countryPill, { backgroundColor: palette.surfaceAlt }]}>
              <Mono size={9} color={palette.textMuted} weight="700" letterSpacing={0.5}>
                {site.country.toUpperCase()}
              </Mono>
            </View>
          ) : null}
        </View>
        <Mono size={10.5} color={palette.textFaint} letterSpacing={0.4} style={{ marginTop: 4 }}>
          {site.code} · {site.areas_count ?? 0} {('AREAS')} · {site.lines_count ?? 0} {('LINES')}
        </Mono>
      </View>
      <FontAwesome name="chevron-right" size={12} color={palette.textFaint} />
    </Pressable>
  );
}

function AreaRow({
  area,
  last,
  palette,
  onPress,
}: {
  area: Area;
  last: boolean;
  palette: typeof Colors.light;
  onPress: () => void;
}) {
  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [
        styles.row,
        {
          opacity: pressed ? 0.85 : 1,
        },
        last
          ? null
          : { borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: palette.border },
      ]}>
      <View style={[styles.iconBadge, { backgroundColor: palette.surfaceAlt }]}>
        <FontAwesome name="sitemap" size={20} color={palette.textMuted} />
      </View>
      <View style={{ flex: 1, minWidth: 0 }}>
        <Text style={[styles.rowTitle, { color: palette.text }]} numberOfLines={1}>
          {area.name}
        </Text>
        <Mono size={10.5} color={palette.textFaint} letterSpacing={0.4} style={{ marginTop: 4 }}>
          {area.code} · {area.site?.name?.toUpperCase() ?? '—'} · {area.lines_count ?? 0} LINES
        </Mono>
      </View>
      <FontAwesome name="chevron-right" size={12} color={palette.textFaint} />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  container: { padding: 16, gap: 12 },
  tabBar: { flexDirection: 'row', gap: 4, padding: 4, borderRadius: 10 },
  tab: { flex: 1, paddingVertical: 8, borderRadius: 7, alignItems: 'center' },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingVertical: 14,
    paddingHorizontal: 14,
  },
  iconBadge: {
    width: 44,
    height: 44,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
  },
  rowTitle: { fontSize: 14, fontWeight: '700' },
  countryPill: { paddingVertical: 1, paddingHorizontal: 5, borderRadius: 3 },
  helpBlock: { padding: 12, borderRadius: 10 },
  addBtn: {
    marginTop: 6,
    height: 44,
    borderRadius: 10,
    borderWidth: 1,
    borderStyle: 'dashed',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
});
