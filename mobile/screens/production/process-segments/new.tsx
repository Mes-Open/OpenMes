import { useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { ProcessSegmentForm } from '@/components/admin/ProcessSegmentForm';
import { DetailScreen } from '@/components/ui/Detail';
import { LoadingState } from '@/components/ui/StateViews';
import { useCreateProcessSegment } from '@/hooks/queries/useProcessSegments';
import { useWorkstationTypes } from '@/hooks/queries/useWorkstationTypes';

export function NewProcessSegmentScreen() {
  const router = useRouter();
  const wsQ = useWorkstationTypes({});
  const m = useCreateProcessSegment();

  if (wsQ.isLoading) return <LoadingState />;

  return (
    <DetailScreen>
      <ProcessSegmentForm
        mode="create"
        workstationTypes={wsQ.data ?? []}
        submitting={m.isPending}
        onSubmit={(payload) =>
          m.mutate(payload, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert('Could not create segment', e.message),
          })
        }
      />
    </DetailScreen>
  );
}
