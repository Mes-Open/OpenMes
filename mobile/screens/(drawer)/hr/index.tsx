import { useRouter } from 'expo-router';
import { ScrollView, StyleSheet, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { HubGrid, HubTile } from '@/components/ui/HubTile';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import {
  useCrews,
  useSkills,
  useWageGroups,
  useWorkers,
} from '@/hooks/queries/useHr';

export function HrHub() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();

  const workersQ = useWorkers({});
  const crewsQ = useCrews(true);
  const skillsQ = useSkills();
  const wageQ = useWageGroups(true);

  const workerCount = workersQ.data?.data.length ?? 0;
  const crewCount = crewsQ.data?.length ?? 0;

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader
        title={t('People')}
        subtitle={`HR · ${workerCount} ${t('WORKERS').toUpperCase()} · ${crewCount} ${t('CREWS').toUpperCase()}`}
      />
      <ScrollView contentContainerStyle={styles.scroll}>
        <HubGrid>
          <HubTile
            icon="user"
            label={t('Workers')}
            sub={t('Employees')}
            count={workerCount}
            accent
            onPress={() => router.push('/(drawer)/hr/workers' as never)}
          />
          <HubTile
            icon="users"
            label={t('Crews')}
            sub={t('Groups')}
            count={crewCount}
            onPress={() => router.push('/(drawer)/hr/crews' as never)}
          />
          <HubTile
            icon="graduation-cap"
            label={t('Skills')}
            sub={t('Competencies')}
            count={skillsQ.data?.length ?? 0}
            onPress={() => router.push('/(drawer)/hr/skills' as never)}
          />
          <HubTile
            icon="money"
            label={t('Wage groups')}
            sub={t('Pay tiers')}
            count={wageQ.data?.length ?? 0}
            onPress={() => router.push('/(drawer)/hr/wage-groups' as never)}
          />
        </HubGrid>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  scroll: { padding: 18, gap: 18, paddingBottom: 32 },
});
