import { useRouter } from 'expo-router';
import { Alert, ScrollView, StyleSheet, View } from 'react-native';

import { WorkerForm } from '@/components/admin/WorkerForm';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useCreateWorker } from '@/hooks/mutations/hr';

export function NewWorkerScreen() {
  const router = useRouter();
  const scheme = useColorScheme();
  const palette = Colors[scheme];
  const m = useCreateWorker();

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader back title="New worker" subtitle="HR · WORKERS" />
      <ScrollView
        style={{ backgroundColor: palette.background }}
        contentContainerStyle={styles.container}
        keyboardShouldPersistTaps="handled">
        <WorkerForm
          mode="create"
          submitting={m.isPending}
          onSubmit={(values) =>
            m.mutate(
              {
                code: values.code,
                name: values.name,
                email: values.email || undefined,
                phone: values.phone || undefined,
                crew_id: values.crew_id ?? undefined,
                wage_group_id: values.wage_group_id ?? undefined,
                is_active: values.is_active,
                skills: values.skills.length ? values.skills : undefined,
              },
              {
                onSuccess: () => router.back(),
                onError: (e: Error) => Alert.alert('Could not create', e.message),
              },
            )
          }
        />
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({ container: { padding: 16 } });
