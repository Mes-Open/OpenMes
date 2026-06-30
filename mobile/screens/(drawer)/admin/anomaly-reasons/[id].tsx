import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { CategorizedCodeNameForm } from '@/components/admin/CategorizedCodeNameForm';
import { DangerZone, DetailScreen } from '@/components/ui/Detail';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useAnomalyReason, useDeleteAnomalyReason, useUpdateAnomalyReason } from '@/hooks/queries/useOps';

export function EditAnomalyReasonScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();

  const query = useAnomalyReason(numericId);
  const updateMutation = useUpdateAnomalyReason();
  const deleteMutation = useDeleteAnomalyReason();

  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data) return <ErrorState error={query.error} onRetry={query.refetch} />;
  const r = query.data;

  return (
    <DetailScreen>
      <CategorizedCodeNameForm
        mode="edit"
        entityLabel="reason"
        initial={{
          code: r.code,
          name: r.name,
          category: r.category ?? '',
          description: r.description ?? '',
          is_active: r.is_active,
        }}
        submitting={updateMutation.isPending}
        onSubmit={(v) =>
          updateMutation.mutate(
            {
              id: r.id,
              payload: {
                code: v.code,
                name: v.name,
                category: v.category || undefined,
                description: v.description || undefined,
                is_active: v.is_active,
              },
            },
            { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert('Could not update', e.message) },
          )
        }
      />

      <DangerZone
        deleteLabel="Delete reason"
        deleteConfirmTitle="Delete reason"
        deleteConfirmMessage={`Delete "${r.name}"?`}
        deleteLoading={deleteMutation.isPending}
        onDelete={() =>
          deleteMutation.mutate(r.id, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert('Could not delete', e.message),
          })
        }
      />
    </DetailScreen>
  );
}
