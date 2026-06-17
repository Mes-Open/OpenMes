import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { MaintenanceEventForm } from '@/components/maintenance/MaintenanceEventForm';
import { DetailScreen } from '@/components/ui/Detail';
import { LoadingState } from '@/components/ui/StateViews';
import { useCostSources } from '@/hooks/queries/useOps';
import { useCreateMaintenanceEvent, useTools } from '@/hooks/queries/useMaintenance';
import { useLines, useUsers } from '@/hooks/queries/useUsers';

export function NewMaintenanceEventScreen() {
  const router = useRouter();
  const params = useLocalSearchParams<{ tool_id?: string }>();
  const presetToolId = params.tool_id ? Number(params.tool_id) : undefined;

  const toolsQ = useTools({});
  const linesQ = useLines();
  const usersQ = useUsers({});
  const costSourcesQ = useCostSources(false);
  const m = useCreateMaintenanceEvent();

  if (toolsQ.isLoading || linesQ.isLoading || usersQ.isLoading || costSourcesQ.isLoading) {
    return <LoadingState />;
  }

  return (
    <DetailScreen title="New maintenance" subtitle="Maintenance · schedule event">
      <MaintenanceEventForm
        mode="create"
        initial={presetToolId ? { tool_id: presetToolId } : undefined}
        tools={toolsQ.data ?? []}
        lines={(linesQ.data ?? []).map((l) => ({ id: l.id, name: l.name }))}
        workstations={[]}
        users={(usersQ.data?.data ?? []).map((u) => ({
          id: u.id,
          name: u.name ?? u.username ?? `#${u.id}`,
        }))}
        costSources={(costSourcesQ.data ?? []).map((c) => ({ id: c.id, name: c.name }))}
        submitting={m.isPending}
        onSubmit={(input) =>
          m.mutate(input, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert('Could not create event', e.message),
          })
        }
      />
    </DetailScreen>
  );
}
