import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { ShiftForm } from '@/components/admin/ShiftForm';
import { DangerZone, DetailScreen } from '@/components/ui/Detail';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useDeleteShift, useShift, useUpdateShift } from '@/hooks/queries/useOps';

export function EditShiftScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();

  const query = useShift(numericId);
  const updateMutation = useUpdateShift();
  const deleteMutation = useDeleteShift();

  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data) return <ErrorState error={query.error} onRetry={query.refetch} />;
  const s = query.data;

  return (
    <DetailScreen>
      <ShiftForm
        mode="edit"
        initial={s}
        submitting={updateMutation.isPending}
        onSubmit={(v) =>
          updateMutation.mutate(
            {
              id: s.id,
              payload: {
                name: v.name,
                start_time: v.start_time,
                end_time: v.end_time,
                days_of_week: v.days_of_week,
                line_id: v.line_id ?? undefined,
                is_active: v.is_active,
              },
            },
            { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert('Could not update', e.message) },
          )
        }
      />

      <DangerZone
        deleteLabel="Delete shift"
        deleteConfirmTitle="Delete shift"
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
