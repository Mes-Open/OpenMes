import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { TopicForm } from '@/components/admin/TopicForm';
import { DetailScreen } from '@/components/ui/Detail';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useTopic, useUpdateTopic } from '@/hooks/queries/useConnectivity';

export function EditTopicScreen() {
  const router = useRouter();
  const { id: idParam } = useLocalSearchParams<{ id: string }>();
  const id = Number(idParam);
  const q = useTopic(Number.isFinite(id) ? id : undefined);
  const m = useUpdateTopic();

  if (q.isLoading) return <LoadingState />;
  if (q.isError || !q.data)
    return <ErrorState error={q.error ?? new Error('Topic not found')} onRetry={q.refetch} />;

  return (
    <DetailScreen>
      <TopicForm
        mode="edit"
        connections={[]}
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
