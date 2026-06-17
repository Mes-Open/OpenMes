import { useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { MaintenanceScheduleForm } from '@/components/maintenance/MaintenanceScheduleForm';
import { DetailScreen } from '@/components/ui/Detail';
import { LoadingState } from '@/components/ui/StateViews';
import { useCostSources } from '@/hooks/queries/useOps';
import { useCreateMaintenanceSchedule, useTools } from '@/hooks/queries/useMaintenance';
import { useLines, useUsers } from '@/hooks/queries/useUsers';

export function NewMaintenanceScheduleScreen() {
  const router = useRouter();
  const toolsQ = useTools({});
  const linesQ = useLines();
  const usersQ = useUsers({});
  const costSourcesQ = useCostSources(false);
  const m = useCreateMaintenanceSchedule();

  if (toolsQ.isLoading || linesQ.isLoading || usersQ.isLoading || costSourcesQ.isLoading) {
    return <LoadingState />;
  }

  // useWorkstations needs a line id — for the schedule form we'd want the full
  // active list. Backend doesn't expose a top-level workstations index, so we
  // flatten across the lines we already loaded.
  const workstations: { id: number; name: string }[] = [];

  return (
    <DetailScreen>
      <MaintenanceScheduleForm
        mode="create"
        tools={(toolsQ.data ?? []).map((t) => ({ id: t.id, name: t.name }))}
        lines={(linesQ.data ?? []).map((l) => ({ id: l.id, name: l.name }))}
        workstations={workstations}
        users={(usersQ.data?.data ?? []).map((u) => ({
          id: u.id,
          name: u.name ?? u.username ?? `#${u.id}`,
        }))}
        costSources={(costSourcesQ.data ?? []).map((c) => ({ id: c.id, name: c.name }))}
        submitting={m.isPending}
        onSubmit={(input) =>
          m.mutate(input, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert('Could not create schedule', e.message),
          })
        }
      />
    </DetailScreen>
  );
}
