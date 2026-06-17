import { useRouter } from 'expo-router';
import { Alert, ScrollView, StyleSheet, View } from 'react-native';

import { SkillForm } from '@/components/admin/SkillForm';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useCreateSkill } from '@/hooks/mutations/hr';

export function NewSkillScreen() {
  const router = useRouter();
  const scheme = useColorScheme();
  const palette = Colors[scheme];
  const m = useCreateSkill();

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader back title="New skill" subtitle="HR · SKILLS" />
      <ScrollView
        style={{ backgroundColor: palette.background }}
        contentContainerStyle={styles.container}
        keyboardShouldPersistTaps="handled">
        <SkillForm
          mode="create"
          submitting={m.isPending}
          onSubmit={(values) =>
            m.mutate(
              { code: values.code, name: values.name, description: values.description || undefined },
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
