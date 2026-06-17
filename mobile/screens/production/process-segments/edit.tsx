import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { ProcessSegmentForm } from '@/components/admin/ProcessSegmentForm';
import { DetailScreen } from '@/components/ui/Detail';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import {
  useProcessSegment,
  useUpdateProcessSegment,
} from '@/hooks/queries/useProcessSegments';
import { useWorkstationTypes } from '@/hooks/queries/useWorkstationTypes';

export function EditProcessSegmentScreen() {
  const router = useRouter();
  const { id: idParam } = useLocalSearchParams<{ id: string }>();
  const id = Number(idParam);

  const q = useProcessSegment(Number.isFinite(id) ? id : undefined);
  const wsQ = useWorkstationTypes({});
  const m = useUpdateProcessSegment();

  if (q.isLoading || wsQ.isLoading) return <LoadingState />;
  if (q.isError || !q.data)
    return <ErrorState error={q.error ?? new Error('Segment not found')} onRetry={q.refetch} />;

  return (
    <DetailScreen>
      <ProcessSegmentForm
        mode="edit"
        initial={q.data}
        workstationTypes={wsQ.data ?? []}
        submitting={m.isPending}
        onSubmit={(payload) =>
          m.mutate(
            { id, payload },
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
