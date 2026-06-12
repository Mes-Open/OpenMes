import { isPast, parseISO } from 'date-fns';
import { useRouter } from 'expo-router';
import { useMemo } from 'react';
import { ScrollView, StyleSheet, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Banner } from '@/components/ui/Banner';
import { HubGrid, HubTile } from '@/components/ui/HubTile';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useMaintenanceEvents, useTools } from '@/hooks/queries/useMaintenance';

export function MaintenanceHub() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();

  const toolsQ = useTools();
  const eventsQ = useMaintenanceEvents({ status: 'pending' });
  const inProgressQ = useMaintenanceEvents({ status: 'in_progress' });

  const toolCount = toolsQ.data?.length ?? 0;
  const openEvents =
    (eventsQ.data?.meta?.total ?? eventsQ.data?.data.length ?? 0) +
    (inProgressQ.data?.meta?.total ?? inProgressQ.data?.data.length ?? 0);

  // Overdue = scheduled_at in the past and still pending. Backend doesn't
  // expose `is_overdue` directly so we compute it from the open list.
  const overdue = useMemo(() => {
    const all = [
      ...(eventsQ.data?.data ?? []),
      ...(inProgressQ.data?.data ?? []),
    ];
    return all.filter((e) => {
      if (!e.scheduled_at) return false;
      try {
        return isPast(parseISO(e.scheduled_at));
      } catch {
        return false;
      }
    });
  }, [eventsQ.data?.data, inProgressQ.data?.data]);

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader title={t('Maintenance')} subtitle={`${t('EQUIPMENT').toUpperCase()} & ${t('SERVICE').toUpperCase()}`} />
      <ScrollView contentContainerStyle={styles.scroll}>
        <HubGrid>
          <HubTile
            icon="wrench"
            label={t('Events')}
            sub={`${openEvents} ${t('OPEN').toUpperCase()}${overdue.length > 0 ? ` · ${overdue.length} ${t('OVERDUE').toUpperCase()}` : ''}`}
            count={openEvents || undefined}
            accent
            onPress={() => router.push('/(drawer)/maintenance/events' as never)}
          />
          <HubTile
            icon="cog"
            label={t('Tools')}
            sub={t('Inventory')}
            count={toolCount}
            onPress={() => router.push('/(drawer)/maintenance/tools' as never)}
          />
          <HubTile
            icon="refresh"
            label={t('Schedules')}
            sub={t('Recurring PMs')}
            onPress={() => router.push('/(drawer)/maintenance/schedules' as never)}
          />
        </HubGrid>

        {overdue.length > 0 ? (
          <Banner
            tone="danger"
            title={
              overdue.length === 1
                ? t('1 event overdue')
                : t('{{count}} events overdue').replace('{{count}}', String(overdue.length))
            }
            detail={overdue
              .slice(0, 3)
              .map((e) => (e.tool?.code ?? e.title).toUpperCase())
              .join(' · ')}
            cta={t('Review')}
            onPress={() => router.push('/(drawer)/maintenance/events' as never)}
          />
        ) : null}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  scroll: { padding: 18, gap: 14, paddingBottom: 32 },
});
