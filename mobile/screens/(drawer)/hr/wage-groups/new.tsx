import { useRouter } from 'expo-router';
import { Alert, ScrollView, StyleSheet, View } from 'react-native';

import { WageGroupForm } from '@/components/admin/WageGroupForm';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useCreateWageGroup } from '@/hooks/mutations/hr';

export function NewWageGroupScreen() {
  const router = useRouter();
  const scheme = useColorScheme();
  const palette = Colors[scheme];
  const m = useCreateWageGroup();

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader back title="New wage group" subtitle="HR · WAGE GROUPS" />
      <ScrollView
        style={{ backgroundColor: palette.background }}
        contentContainerStyle={styles.container}
        keyboardShouldPersistTaps="handled">
        <WageGroupForm
          mode="create"
          submitting={m.isPending}
          onSubmit={(values) =>
            m.mutate(
              {
                code: values.code,
                name: values.name,
                description: values.description || undefined,
                base_hourly_rate: values.base_hourly_rate ? Number(values.base_hourly_rate) : undefined,
                currency: values.currency || undefined,
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
