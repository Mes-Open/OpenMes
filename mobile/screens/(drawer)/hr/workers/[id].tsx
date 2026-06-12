import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { WorkerForm } from '@/components/admin/WorkerForm';
import { WorkerProfile } from '@/components/admin/WorkerProfile';
import { DangerZone, DetailScreen } from '@/components/ui/Detail';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useWorker } from '@/hooks/queries/useHr';
import { useDeleteWorker, useSyncWorkerSkills, useUpdateWorker } from '@/hooks/mutations/hr';

export function EditWorkerScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();

  const query = useWorker(numericId);
  const updateMutation = useUpdateWorker();
  const skillsMutation = useSyncWorkerSkills();
  const deleteMutation = useDeleteWorker();

  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data) return <ErrorState error={query.error} onRetry={query.refetch} />;
  const worker = query.data;

  return (
    <DetailScreen>
      <WorkerProfile worker={worker} />
      <WorkerForm
        mode="edit"
        initial={worker}
        submitting={updateMutation.isPending || skillsMutation.isPending}
        onSubmit={async (values) => {
          updateMutation.mutate(
            {
              id: worker.id,
              payload: {
                code: values.code,
                name: values.name,
                email: values.email || undefined,
                phone: values.phone || undefined,
                crew_id: values.crew_id ?? undefined,
                wage_group_id: values.wage_group_id ?? undefined,
                is_active: values.is_active,
              },
            },
            {
              onSuccess: () => {
                skillsMutation.mutate(
                  { id: worker.id, skills: values.skills },
                  {
                    onSuccess: () => router.back(),
                    onError: (e: Error) => Alert.alert('Skills update failed', e.message),
                  },
                );
              },
              onError: (e: Error) => Alert.alert('Could not update', e.message),
            },
          );
        }}
      />

      <DangerZone
        deleteLabel="Delete worker"
        deleteConfirmTitle="Delete worker"
        deleteConfirmMessage={`Delete "${worker.name}"?`}
        deleteLoading={deleteMutation.isPending}
        onDelete={() =>
          deleteMutation.mutate(worker.id, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert('Could not delete', e.message),
          })
        }
      />
    </DetailScreen>
  );
}
