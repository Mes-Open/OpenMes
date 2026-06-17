import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { SimpleCodeNameForm } from '@/components/admin/SimpleCodeNameForm';
import { DangerZone, DetailScreen } from '@/components/ui/Detail';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useDivision } from '@/hooks/queries/useOrgStructure';
import {
  useDeleteDivision,
  useToggleDivisionActive,
  useUpdateDivision,
} from '@/hooks/queries/useOrgStructure';

export function EditDivisionScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();

  const query = useDivision(numericId);
  const updateMutation = useUpdateDivision();
  const deleteMutation = useDeleteDivision();
  const toggleMutation = useToggleDivisionActive();

  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data) return <ErrorState error={query.error} onRetry={query.refetch} />;
  const d = query.data;

  return (
    <DetailScreen>
      <SimpleCodeNameForm
        mode="edit"
        entityLabel="division"
        initial={{
          code: d.code,
          name: d.name,
          description: d.description ?? '',
          is_active: d.is_active,
        }}
        submitting={updateMutation.isPending}
        onSubmit={(values) =>
          updateMutation.mutate(
            {
              id: d.id,
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
        toggleLabel={d.is_active ? 'Deactivate' : 'Activate'}
        toggleLoading={toggleMutation.isPending}
        onToggle={() =>
          toggleMutation.mutate(d.id, { onError: (e: Error) => Alert.alert('Failed', e.message) })
        }
        deleteLabel="Delete division"
        deleteConfirmTitle="Delete division"
        deleteConfirmMessage={`Delete "${d.name}"?`}
        deleteLoading={deleteMutation.isPending}
        onDelete={() =>
          deleteMutation.mutate(d.id, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert('Could not delete', e.message),
          })
        }
      />
    </DetailScreen>
  );
}
