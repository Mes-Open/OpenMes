import { useRouter } from 'expo-router';
import { Alert, ScrollView, StyleSheet, View } from 'react-native';

import { CrewForm } from '@/components/admin/CrewForm';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useCreateCrew } from '@/hooks/mutations/hr';

export function NewCrewScreen() {
  const router = useRouter();
  const scheme = useColorScheme();
  const palette = Colors[scheme];
  const m = useCreateCrew();

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader back title="New crew" subtitle="HR · CREWS" />
      <ScrollView
        style={{ backgroundColor: palette.background }}
        contentContainerStyle={styles.container}
        keyboardShouldPersistTaps="handled">
        <CrewForm
          mode="create"
          submitting={m.isPending}
          onSubmit={(values) =>
            m.mutate(
              {
                code: values.code,
                name: values.name,
                description: values.description || undefined,
                leader_id: values.leader_id ?? undefined,
                is_active: values.is_active,
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
