// Entry for the employee-schedule routes. The active view is driven by the
// `view` URL param (day | week | month). Team Day is a separate route
// (./team) because it's multi-worker, not part of the per-worker tabs.

import { useLocalSearchParams, useRouter } from 'expo-router';
import { useMemo } from 'react';
import { View } from 'react-native';

import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { DayPlanScreen } from './DayPlanScreen';
import { WeekScreen } from './WeekScreen';
import { MonthScreen } from './MonthScreen';
import type { ViewMode } from '@/components/employee-schedule/PlannerHeader';

export function EmployeeScheduleScreen() {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const router = useRouter();
  const params = useLocalSearchParams<{ view?: ViewMode; worker_id?: string }>();

  const mode: ViewMode = useMemo(() => {
    const v = (params.view ?? 'day') as ViewMode;
    return v === 'week' || v === 'month' ? v : 'day';
  }, [params.view]);

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      {mode === 'day' ? (
        <DayPlanScreen
          onAdd={(workerId, date) =>
            router.push({
              pathname: '/admin/employee-schedule/add',
              params: { worker_id: String(workerId), date: date.toISOString() },
            })
          }
        />
      ) : mode === 'week' ? (
        <WeekScreen />
      ) : (
        <MonthScreen />
      )}
    </View>
  );
}
