import { useRouter } from 'expo-router';
import { ScrollView, StyleSheet, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { HubGrid, HubTile } from '@/components/ui/HubTile';
import { SectionLabel } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useAlertCounts } from '@/hooks/queries/useSystem';

/**
 * Supervisor hub — supervisors + admins land here. Adds production
 * oversight surfaces (schedule, alerts wall, analytics, reports,
 * maintenance scheduling) on top of everything the Operator hub offers.
 * Most analytics/reports tiles still point at MissingScreen stubs until
 * those views are built.
 */
export default function SupervisorHubPage() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();

  const alertsQ = useAlertCounts();
  const alerts = alertsQ.data?.total ?? 0;

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader title={t('Supervisor')} subtitle={t('OVERSIGHT').toUpperCase()} />
      <ScrollView contentContainerStyle={styles.scroll}>
        <SectionLabel>{t('Floor')}</SectionLabel>
        <HubGrid>
          <HubTile
            icon="calendar"
            label={t('Schedule')}
            sub={t('Gantt · today')}
            accent
            onPress={() => router.push('/(drawer)/schedule' as never)}
          />
          <HubTile
            icon="desktop"
            label={t('Plant Wall')}
            sub={t('Live ops dashboard')}
            onPress={() => router.push('/(drawer)/admin/wall' as never)}
          />
          <HubTile
            icon="bell"
            label={t('Alerts')}
            sub={t('Andon · triage')}
            count={alerts > 0 ? alerts : undefined}
            onPress={() => router.push('/(drawer)/(tabs)/issues' as never)}
          />
          <HubTile
            icon="list-alt"
            label={t('Orders')}
            sub={t('Work orders')}
            onPress={() => router.push('/(drawer)/(tabs)/orders' as never)}
          />
        </HubGrid>

        <View style={{ height: 18 }} />
        <SectionLabel>{t('Analytics')}</SectionLabel>
        <HubGrid>
          <HubTile
            icon="bar-chart"
            label={t('Overview')}
            sub={t('KPIs · trend')}
            onPress={() => router.push('/supervisor/analytics/overview' as never)}
          />
          <HubTile
            icon="line-chart"
            label={t('By line')}
            sub={t('Production')}
            onPress={() => router.push('/supervisor/analytics/production-by-line' as never)}
          />
          <HubTile
            icon="clock-o"
            label={t('Cycle time')}
            sub={t('Per step')}
            onPress={() => router.push('/supervisor/analytics/cycle-time' as never)}
          />
          <HubTile
            icon="tachometer"
            label={t('Throughput')}
            sub={t('Units / hr')}
            onPress={() => router.push('/supervisor/analytics/throughput' as never)}
          />
          <HubTile
            icon="exclamation-triangle"
            label={t('Issue stats')}
            sub={t('Andon · MTTR')}
            onPress={() => router.push('/supervisor/analytics/issue-stats' as never)}
          />
          <HubTile
            icon="cogs"
            label={t('Step perf')}
            sub={t('Bottlenecks')}
            onPress={() => router.push('/supervisor/analytics/step-performance' as never)}
          />
        </HubGrid>

        <View style={{ height: 18 }} />
        <SectionLabel>{t('Reports')}</SectionLabel>
        <HubGrid>
          <HubTile
            icon="file-text-o"
            label={t('Production')}
            sub={t('Daily summary')}
            onPress={() => router.push('/(drawer)/admin/reports' as never)}
          />
          <HubTile
            icon="check-square-o"
            label={t('Batch completion')}
            sub={t('Per batch')}
            onPress={() => router.push('/supervisor/reports/batch-completion' as never)}
          />
          <HubTile
            icon="ban"
            label={t('Downtime')}
            sub={t('Reasons · duration')}
            onPress={() => router.push('/supervisor/reports/downtime' as never)}
          />
        </HubGrid>

        <View style={{ height: 18 }} />
        <SectionLabel>{t('Maintenance')}</SectionLabel>
        <HubGrid>
          <HubTile
            icon="wrench"
            label={t('Events')}
            sub={t('Schedule · assign')}
            onPress={() => router.push('/(drawer)/maintenance/events' as never)}
          />
          <HubTile
            icon="briefcase"
            label={t('Tools')}
            sub={t('Status · transitions')}
            onPress={() => router.push('/(drawer)/maintenance/tools' as never)}
          />
        </HubGrid>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  scroll: { padding: 18, gap: 8, paddingBottom: 32 },
});
