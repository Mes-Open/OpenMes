import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { AreaForm } from '@/components/admin/AreaForm';
import { DetailScreen } from '@/components/ui/Detail';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useArea, useSites, useUpdateArea } from '@/hooks/queries/useStructureIsa95';

export function EditAreaScreen() {
  const router = useRouter();
  const { id: idParam } = useLocalSearchParams<{ id: string }>();
  const id = Number(idParam);

  const q = useArea(Number.isFinite(id) ? id : undefined);
  const sitesQ = useSites();
  const m = useUpdateArea();

  if (q.isLoading || sitesQ.isLoading) return <LoadingState />;
  if (q.isError || !q.data)
    return <ErrorState error={q.error ?? new Error('Area not found')} onRetry={q.refetch} />;

  return (
    <DetailScreen>
      <AreaForm
        mode="edit"
        initial={q.data}
        sites={(sitesQ.data ?? []).map((s) => ({ id: s.id, name: s.name }))}
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
