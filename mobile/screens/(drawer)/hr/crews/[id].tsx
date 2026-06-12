import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert, StyleSheet, Text, View } from 'react-native';

import { CrewForm } from '@/components/admin/CrewForm';
import { Card } from '@/components/ui/Card';
import { DangerZone, DetailScreen } from '@/components/ui/Detail';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useCrew, useCrewWorkers } from '@/hooks/queries/useHr';
import { useDeleteCrew, useToggleCrewActive, useUpdateCrew } from '@/hooks/mutations/hr';

export function EditCrewScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  const query = useCrew(numericId);
  const workersQuery = useCrewWorkers(numericId);
  const updateMutation = useUpdateCrew();
  const deleteMutation = useDeleteCrew();
  const toggleMutation = useToggleCrewActive();

  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data) return <ErrorState error={query.error} onRetry={query.refetch} />;
  const crew = query.data;
  const workers = workersQuery.data ?? [];

  return (
    <DetailScreen>
      <CrewForm
        mode="edit"
        initial={{
          code: crew.code,
          name: crew.name,
          description: crew.description ?? '',
          leader_id: crew.leader_id ?? null,
          is_active: crew.is_active,
        }}
        submitting={updateMutation.isPending}
        onSubmit={(values) =>
          updateMutation.mutate(
            {
              id: crew.id,
              payload: {
                code: values.code,
                name: values.name,
                description: values.description || undefined,
                leader_id: values.leader_id ?? undefined,
                is_active: values.is_active,
              },
            },
            { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert('Could not update', e.message) },
          )
        }
      />

      <Card style={{ gap: 10 }}>
        <SectionLabel
          right={<Mono size={11} color={palette.textFaint}>{`${workers.length} TOTAL`}</Mono>}>
          Workers
        </SectionLabel>
        {workers.length === 0 ? (
          <Mono size={11} color={palette.textFaint}>NO WORKERS ASSIGNED</Mono>
        ) : (
          <View style={{ gap: 6 }}>
            {workers.slice(0, 5).map((w) => (
              <View key={w.id} style={styles.row}>
                <Text style={[styles.name, { color: palette.text }]}>{w.name}</Text>
                <Mono size={11} color={palette.textFaint}>{w.code}</Mono>
              </View>
            ))}
            {workers.length > 5 ? (
              <Mono size={11} color={palette.textFaint} style={{ marginTop: 2 }}>
                +{workers.length - 5} MORE
              </Mono>
            ) : null}
          </View>
        )}
      </Card>

      <DangerZone
        toggleLabel={crew.is_active ? 'Deactivate' : 'Activate'}
        toggleLoading={toggleMutation.isPending}
        onToggle={() =>
          toggleMutation.mutate(crew.id, { onError: (e: Error) => Alert.alert('Failed', e.message) })
        }
        deleteLabel="Delete crew"
        deleteConfirmTitle="Delete crew"
        deleteConfirmMessage={`Delete "${crew.name}"?`}
        deleteLoading={deleteMutation.isPending}
        onDelete={() =>
          deleteMutation.mutate(crew.id, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert('Could not delete', e.message),
          })
        }
      />
    </DetailScreen>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  name: { fontSize: 14, fontWeight: '500' },
});
