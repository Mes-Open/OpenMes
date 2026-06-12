import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { TopicForm } from '@/components/admin/TopicForm';
import { DetailScreen } from '@/components/ui/Detail';
import { LoadingState } from '@/components/ui/StateViews';
import { useConnections, useCreateTopic } from '@/hooks/queries/useConnectivity';

export function NewTopicScreen() {
  const router = useRouter();
  // Optional ?connection_id pre-selects + locks the picker — used when
  // navigating in from a specific connection's detail page.
  const { connection_id } = useLocalSearchParams<{ connection_id?: string }>();
  const lockedId = connection_id ? Number(connection_id) : undefined;

  const connsQ = useConnections(true);
  const m = useCreateTopic();

  if (connsQ.isLoading) return <LoadingState />;

  return (
    <DetailScreen>
      <TopicForm
        mode="create"
        connections={connsQ.data ?? []}
        lockedConnectionId={lockedId}
        submitting={m.isPending}
        onSubmit={(input) =>
          m.mutate(input, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert('Could not create topic', e.message),
          })
        }
      />
    </DetailScreen>
  );
}
