import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { SimpleCodeNameForm } from '@/components/admin/SimpleCodeNameForm';
import { DangerZone, DetailScreen } from '@/components/ui/Detail';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useWorkstationType } from '@/hooks/queries/useWorkstationTypes';
import {
  useDeleteWorkstationType,
  useToggleWorkstationTypeActive,
  useUpdateWorkstationType,
} from '@/hooks/mutations/workstationTypes';

export function EditWorkstationTypeScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();

  const query = useWorkstationType(numericId);
  const updateMutation = useUpdateWorkstationType();
  const deleteMutation = useDeleteWorkstationType();
  const toggleMutation = useToggleWorkstationTypeActive();

  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data) return <ErrorState error={query.error} onRetry={query.refetch} />;
  const wt = query.data;

  return (
    <DetailScreen>
      <SimpleCodeNameForm
        mode="edit"
        entityLabel="workstation type"
        initial={{
          code: wt.code,
          name: wt.name,
          description: wt.description ?? '',
          is_active: wt.is_active,
        }}
        submitting={updateMutation.isPending}
        onSubmit={(values) =>
          updateMutation.mutate(
            {
              id: wt.id,
              payload: {
                code: values.code,
                name: values.name,
                description: values.description || undefined,
                is_active: values.is_active,
              },
            },
            { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert('Could not update', e.message) },
          )
        }
      />

      <DangerZone
        toggleLabel={wt.is_active ? 'Deactivate' : 'Activate'}
        toggleLoading={toggleMutation.isPending}
        onToggle={() =>
          toggleMutation.mutate(wt.id, { onError: (e: Error) => Alert.alert('Failed', e.message) })
        }
        deleteLabel="Delete workstation type"
        deleteConfirmTitle="Delete"
        deleteConfirmMessage={`Delete "${wt.name}"?`}
        deleteLoading={deleteMutation.isPending}
        onDelete={() =>
          deleteMutation.mutate(wt.id, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert('Could not delete', e.message),
          })
        }
      />
    </DetailScreen>
  );
}
