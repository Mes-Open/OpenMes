import { useRouter } from 'expo-router';
import { ScrollView, StyleSheet, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { HubGrid, HubTile } from '@/components/ui/HubTile';
import { SectionLabel } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useAlertCounts } from '@/hooks/queries/useSystem';
import { useAuthStore } from '@/stores/authStore';

/**
 * Operator hub — first screen an operator sees after sign-in. Tile grid
 * routes into the existing feature folders (orders, work-orders, downtime,
 * issues, packaging). Counts are pulled from the same alert/system queries
 * the drawer uses, so values match what the badge shows.
 */
export default function OperatorHubPage() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();

  const user = useAuthStore((s) => s.user);
  const activeLineId = useAuthStore((s) => s.activeLineId);
  const activeLine = user?.lines?.find((l) => l.id === activeLineId);

  const alertsQ = useAlertCounts();
  const alerts = alertsQ.data?.total ?? 0;

  const subtitle = activeLine
    ? `${(activeLine.code ?? activeLine.name).toString().toUpperCase()} · ${t('OPERATOR').toUpperCase()}`
    : t('OPERATOR').toUpperCase();

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader title={t('Today')} subtitle={subtitle} />
      <ScrollView contentContainerStyle={styles.scroll}>
        <SectionLabel>{t('Work')}</SectionLabel>
        <HubGrid>
          <HubTile
            icon="home"
            label={t('Today')}
            sub={t('Live floor view')}
            accent
            onPress={() => router.push('/(drawer)/(tabs)' as never)}
          />
          <HubTile
            icon="list-alt"
            label={t('Orders')}
            sub={t('Work orders')}
            onPress={() => router.push('/(drawer)/(tabs)/orders' as never)}
          />
          <HubTile
            icon="qrcode"
            label={t('Scan')}
            sub={t('EAN · LOT · WO')}
            onPress={() => router.push('/(drawer)/(tabs)/scan' as never)}
          />
          <HubTile
            icon="bell"
            label={t('Alerts')}
            sub={t('Andon · triage')}
            count={alerts > 0 ? alerts : undefined}
            onPress={() => router.push('/(drawer)/(tabs)/issues' as never)}
          />
        </HubGrid>

        <View style={{ height: 18 }} />
        <SectionLabel>{t('Report')}</SectionLabel>
        <HubGrid>
          <HubTile
            icon="exclamation-triangle"
            label={t('Report issue')}
            sub={t('Andon · escalate')}
            onPress={() => router.push('/issues/new' as never)}
          />
          <HubTile
            icon="ban"
            label={t('Downtime')}
            sub={t('Start · stop')}
            onPress={() => router.push('/(drawer)/production/downtime' as never)}
          />
          <HubTile
            icon="cube"
            label={t('Packaging')}
            sub={t('Scan EAN labels')}
            onPress={() => router.push('/(drawer)/pakowanie/' as never)}
          />
          <HubTile
            icon="user"
            label={t('Profile')}
            sub={t('Line · settings')}
            onPress={() => router.push('/(drawer)/(tabs)/profile' as never)}
          />
        </HubGrid>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  scroll: { padding: 18, gap: 8, paddingBottom: 32 },
});
