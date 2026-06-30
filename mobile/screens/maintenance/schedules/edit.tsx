import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { MaintenanceScheduleForm } from '@/components/maintenance/MaintenanceScheduleForm';
import { DetailScreen } from '@/components/ui/Detail';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useCostSources } from '@/hooks/queries/useOps';
import {
  useMaintenanceSchedule,
  useTools,
  useUpdateMaintenanceSchedule,
} from '@/hooks/queries/useMaintenance';
import { useLines, useUsers } from '@/hooks/queries/useUsers';

export function EditMaintenanceScheduleScreen() {
  const router = useRouter();
  const { id: idParam } = useLocalSearchParams<{ id: string }>();
  const id = Number(idParam);

  const q = useMaintenanceSchedule(Number.isFinite(id) ? id : undefined);
  const toolsQ = useTools({});
  const linesQ = useLines();
  const usersQ = useUsers({});
  const costSourcesQ = useCostSources(false);
  const m = useUpdateMaintenanceSchedule();

  if (q.isLoading || toolsQ.isLoading || linesQ.isLoading || usersQ.isLoading || costSourcesQ.isLoading) {
    return <LoadingState />;
  }
  if (q.isError || !q.data) {
    return <ErrorState error={q.error ?? new Error('Schedule not found')} onRetry={q.refetch} />;
  }

  return (
    <DetailScreen>
      <MaintenanceScheduleForm
        mode="edit"
        initial={q.data}
        tools={(toolsQ.data ?? []).map((t) => ({ id: t.id, name: t.name }))}
        lines={(linesQ.data ?? []).map((l) => ({ id: l.id, name: l.name }))}
        workstations={[]}
        users={(usersQ.data?.data ?? []).map((u) => ({
          id: u.id,
          name: u.name ?? u.username ?? `#${u.id}`,
        }))}
        costSources={(costSourcesQ.data ?? []).map((c) => ({ id: c.id, name: c.name }))}
        submitting={m.isPending}
        onSubmit={(input) =>
          m.mutate(
            { id, input },
            {
              onSuccess: () => router.back(),
              onError: (e: Error) => Alert.alert('Could not save changes', e.message),
            },
          )
        }
      />
    </DetailScreen>
  );
}
