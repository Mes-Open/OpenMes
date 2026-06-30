import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { ToolForm } from '@/components/maintenance/ToolForm';
import { ToolProfile } from '@/components/maintenance/ToolProfile';
import { DangerZone, DetailScreen } from '@/components/ui/Detail';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useDeleteTool, useTool, useUpdateTool } from '@/hooks/queries/useMaintenance';

export function EditToolScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();

  const query = useTool(numericId);
  const updateMutation = useUpdateTool();
  const deleteMutation = useDeleteTool();

  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data) return <ErrorState error={query.error} onRetry={query.refetch} />;
  const t = query.data;

  return (
    <DetailScreen title={t.name} subtitle={`${t.code.toUpperCase()} · MAINTENANCE`}>
      <ToolProfile tool={t} />
      <ToolForm
        mode="edit"
        initial={t}
        submitting={updateMutation.isPending}
        onSubmit={(v) =>
          updateMutation.mutate(
            {
              id: t.id,
              payload: {
                code: v.code,
                name: v.name,
                description: v.description || undefined,
                status: v.status,
                next_service_at: v.next_service_at || undefined,
              },
            },
            {
              onSuccess: () => router.back(),
              onError: (e: Error) => Alert.alert('Could not update', e.message),
            },
          )
        }
      />

      <DangerZone
        deleteLabel="Delete tool"
        deleteConfirmTitle="Delete tool"
        deleteConfirmMessage={`Delete "${t.name}"?`}
        deleteLoading={deleteMutation.isPending}
        onDelete={() =>
          deleteMutation.mutate(t.id, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert('Could not delete', e.message),
          })
        }
      />
    </DetailScreen>
  );
}
