import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { ConnectionForm } from '@/components/admin/ConnectionForm';
import { DetailScreen } from '@/components/ui/Detail';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useConnection, useUpdateConnection } from '@/hooks/queries/useConnectivity';

export function EditConnectionScreen() {
  const router = useRouter();
  const { id: idParam } = useLocalSearchParams<{ id: string }>();
  const id = Number(idParam);
  const q = useConnection(Number.isFinite(id) ? id : undefined);
  const m = useUpdateConnection();

  if (q.isLoading) return <LoadingState />;
  if (q.isError || !q.data)
    return <ErrorState error={q.error ?? new Error('Connection not found')} onRetry={q.refetch} />;

  return (
    <DetailScreen>
      <ConnectionForm
        mode="edit"
        initial={q.data}
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
