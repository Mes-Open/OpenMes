import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { WorkstationForm } from '@/components/admin/WorkstationForm';
import { DangerZone, DetailScreen } from '@/components/ui/Detail';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useWorkstation } from '@/hooks/queries/useLines';
import {
  useDeleteWorkstation,
  useToggleWorkstationActive,
  useUpdateWorkstation,
} from '@/hooks/mutations/lines';

export function EditWorkstationScreen() {
  const { workstationId } = useLocalSearchParams<{ id: string; workstationId: string }>();
  const numericId = Number(workstationId);
  const router = useRouter();

  const query = useWorkstation(numericId);
  const updateMutation = useUpdateWorkstation();
  const deleteMutation = useDeleteWorkstation();
  const toggleMutation = useToggleWorkstationActive();

  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data) return <ErrorState error={query.error} onRetry={query.refetch} />;
  const ws = query.data;

  return (
    <DetailScreen>
      <WorkstationForm
        mode="edit"
        initial={ws}
        submitting={updateMutation.isPending}
        onSubmit={(values) =>
          updateMutation.mutate(
            {
              id: ws.id,
              payload: {
                code: values.code,
                name: values.name,
                workstation_type: values.workstation_type || undefined,
                is_active: values.is_active,
              },
            },
            { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert('Could not update', e.message) },
          )
        }
      />

      <DangerZone
        toggleLabel={ws.is_active ? 'Deactivate' : 'Activate'}
        toggleLoading={toggleMutation.isPending}
        onToggle={() =>
          toggleMutation.mutate(ws.id, { onError: (e: Error) => Alert.alert('Failed', e.message) })
        }
        deleteLabel="Delete workstation"
        deleteConfirmTitle="Delete workstation"
        deleteConfirmMessage={`Delete "${ws.name}"?`}
        deleteLoading={deleteMutation.isPending}
        onDelete={() =>
          deleteMutation.mutate(ws.id, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert('Could not delete', e.message),
          })
        }
      />
    </DetailScreen>
  );
}
