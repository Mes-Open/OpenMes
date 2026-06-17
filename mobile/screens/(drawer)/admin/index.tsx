import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { HubGrid, HubTile } from '@/components/ui/HubTile';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useRouter } from 'expo-router';
import { useAuditLogs } from '@/hooks/queries/useAuditLogs';
import { useEventLogs } from '@/hooks/queries/useEventLogs';
import { useAnomalyReasons, useCompanies, useCostSources } from '@/hooks/queries/useOps';
import { useLotSequences } from '@/hooks/queries/useLot';
import { useModules } from '@/hooks/queries/useSystem';
import { useUsers } from '@/hooks/queries/useUsers';
import { getRole, useAuthStore } from '@/stores/authStore';

export function AdminHub() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const role = getRole(useAuthStore.getState().user);
  const { t } = useTranslation();

  const usersQ = useUsers({});
  const modulesQ = useModules();
  const auditQ = useAuditLogs({ per_page: 1 });
  const eventsQ = useEventLogs({ per_page: 1 });
  const reasonsQ = useAnomalyReasons({ include_inactive: true });
  const sourcesQ = useCostSources(true);
  const companiesQ = useCompanies({ include_inactive: true });
  const lotQ = useLotSequences();

  if (role !== 'Admin') {
    return (
      <View style={[styles.center, { backgroundColor: palette.background }]}>
        <Text style={[styles.warn, { color: palette.text }]}>{t('Admin only')}</Text>
        <Mono size={11} color={palette.textMuted}>{t('You need the Admin role').toUpperCase()}</Mono>
      </View>
    );
  }

  const userCount = usersQ.data?.data.length ?? 0;
  const moduleCount = (modulesQ.data ?? []).length;
  const moduleEnabled = (modulesQ.data ?? []).filter((m) => m.enabled).length;

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader title={t('Admin')} subtitle={`${t('SYSTEM').toUpperCase()} & ${t('CATALOG').toUpperCase()} · ${t('ALL-ACCESS').toUpperCase()}`} />
      <ScrollView contentContainerStyle={styles.scroll}>
        <View>
          <SectionLabel>{t('System')}</SectionLabel>
          <HubGrid>
            <HubTile
              icon="user"
              label={t('Users')}
              sub={t('Accounts')}
              count={userCount}
              accent
              onPress={() => router.push('/(drawer)/admin/users' as never)}
            />
            <HubTile
              icon="bar-chart"
              label={t('Reports')}
              sub={t('Analytics')}
              onPress={() => router.push('/(drawer)/admin/reports' as never)}
            />
            <HubTile
              icon="cog"
              label={t('System settings')}
              sub={t('Policies')}
              onPress={() => router.push('/(drawer)/admin/system-settings' as never)}
            />
            <HubTile
              icon="cube"
              label={t('Modules')}
              sub={`${moduleEnabled} ${t('ENABLED').toUpperCase()}`}
              count={moduleCount || undefined}
              onPress={() => router.push('/(drawer)/admin/modules' as never)}
            />
            <HubTile
              icon="bell"
              label={t('Alerts dashboard')}
              sub={t('System-wide')}
              onPress={() => router.push('/(drawer)/admin/alerts-dashboard' as never)}
            />
            <HubTile
              icon="line-chart"
              label={t('OEE')}
              sub={t('Availability × Perf × Qual')}
              onPress={() => router.push('/(drawer)/admin/oee' as never)}
            />
            <HubTile
              icon="desktop"
              label={t('Plant Wall')}
              sub={t('Live ops dashboard')}
              onPress={() => router.push('/(drawer)/admin/wall' as never)}
            />
            <HubTile
              icon="cube"
              label={t('Subiekt nexo')}
              sub={t('ERP integration')}
              onPress={() => router.push('/(drawer)/admin/subiekt' as never)}
            />
            <HubTile
              icon="cubes"
              label={t('Materials')}
              sub={t('Stock & suppliers')}
              onPress={() => router.push('/(drawer)/admin/materials' as never)}
            />
            <HubTile
              icon="key"
              label={t('API tokens')}
              sub={t('Integrations')}
              onPress={() => router.push('/(drawer)/admin/api-tokens' as never)}
            />
          </HubGrid>
        </View>

        <View>
          <SectionLabel>{t('Audit')}</SectionLabel>
          <HubGrid>
            <HubTile
              icon="shield"
              label={t('Audit logs')}
              sub={t('Immutable')}
              count={fmtCount(auditQ.data?.meta?.total)}
              onPress={() => router.push('/(drawer)/admin/audit-logs' as never)}
            />
            <HubTile
              icon="list"
              label={t('Event logs')}
              sub={t('System')}
              count={fmtCount(eventsQ.data?.meta?.total)}
              onPress={() => router.push('/(drawer)/admin/event-logs' as never)}
            />
            <HubTile
              icon="terminal"
              label={t('System logs')}
              sub={t('Live tail · failed jobs')}
              onPress={() => router.push('/(drawer)/admin/system-logs' as never)}
            />
          </HubGrid>
        </View>

        <View>
          <SectionLabel>{t('Catalog')}</SectionLabel>
          <HubGrid>
            <HubTile
              icon="exclamation-triangle"
              label={t('Anomaly reasons')}
              sub={t('Scrap categories')}
              count={reasonsQ.data?.length ?? 0}
              onPress={() => router.push('/(drawer)/admin/anomaly-reasons' as never)}
            />
            <HubTile
              icon="check-square-o"
              label={t('Inspection plans')}
              sub={t('Criteria templates')}
              onPress={() => router.push('/(drawer)/admin/inspection-plans' as never)}
            />
            <HubTile
              icon="usd"
              label={t('Cost sources')}
              sub={t('Expense types')}
              count={sourcesQ.data?.length ?? 0}
              onPress={() => router.push('/(drawer)/admin/cost-sources' as never)}
            />
            <HubTile
              icon="building"
              label={t('Companies')}
              sub={t('Suppliers')}
              count={companiesQ.data?.length ?? 0}
              onPress={() => router.push('/(drawer)/admin/companies' as never)}
            />
            <HubTile
              icon="barcode"
              label={t('LOT sequences')}
              sub={t('Numbering')}
              count={lotQ.data?.length ?? 0}
              onPress={() => router.push('/(drawer)/admin/lot-sequences' as never)}
            />
          </HubGrid>
        </View>
      </ScrollView>
    </View>
  );
}

function fmtCount(n: number | undefined): string | undefined {
  if (n == null) return undefined;
  if (n >= 1000) return `${(n / 1000).toFixed(1).replace(/\.0$/, '')}K`;
  return String(n);
}

const styles = StyleSheet.create({
  scroll: { padding: 18, gap: 18, paddingBottom: 32 },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: 6 },
  warn: { fontSize: 18, fontWeight: '700' },
});
