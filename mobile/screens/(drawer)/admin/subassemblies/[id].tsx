import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { SimpleCodeNameForm } from '@/components/admin/SimpleCodeNameForm';
import { DangerZone, DetailScreen } from '@/components/ui/Detail';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useDeleteSubassembly, useSubassembly, useUpdateSubassembly } from '@/hooks/queries/useOps';

export function EditSubassemblyScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();

  const query = useSubassembly(numericId);
  const updateMutation = useUpdateSubassembly();
  const deleteMutation = useDeleteSubassembly();

  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data) return <ErrorState error={query.error} onRetry={query.refetch} />;
  const s = query.data;

  return (
    <DetailScreen>
      <SimpleCodeNameForm
        mode="edit"
        entityLabel="subassembly"
        initial={{
          code: s.code,
          name: s.name,
          description: s.description ?? '',
          is_active: s.is_active,
        }}
        submitting={updateMutation.isPending}
        onSubmit={(v) =>
          updateMutation.mutate(
            {
              id: s.id,
              payload: {
                code: v.code,
                name: v.name,
                description: v.description || undefined,
                is_active: v.is_active,
              },
            },
            { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert('Could not update', e.message) },
          )
        }
      />

      <DangerZone
        deleteLabel="Delete subassembly"
        deleteConfirmTitle="Delete"
        deleteConfirmMessage={`Delete "${s.name}"?`}
        deleteLoading={deleteMutation.isPending}
        onDelete={() =>
          deleteMutation.mutate(s.id, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert('Could not delete', e.message),
          })
        }
      />
    </DetailScreen>
  );
}
