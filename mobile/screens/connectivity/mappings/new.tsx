import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { MappingForm } from '@/components/admin/MappingForm';
import { DetailScreen } from '@/components/ui/Detail';
import { LoadingState } from '@/components/ui/StateViews';
import { useCreateMapping, useTopics } from '@/hooks/queries/useConnectivity';

export function NewMappingScreen() {
  const router = useRouter();
  const { topic_id } = useLocalSearchParams<{ topic_id?: string }>();
  const lockedId = topic_id ? Number(topic_id) : undefined;

  const topicsQ = useTopics({ include_inactive: true });
  const m = useCreateMapping();

  if (topicsQ.isLoading) return <LoadingState />;

  return (
    <DetailScreen>
      <MappingForm
        mode="create"
        topics={topicsQ.data ?? []}
        lockedTopicId={lockedId}
        submitting={m.isPending}
        onSubmit={(input) =>
          m.mutate(input, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert('Could not create mapping', e.message),
          })
        }
        onValidationError={(msg) => Alert.alert('Form error', msg)}
      />
    </DetailScreen>
  );
}
