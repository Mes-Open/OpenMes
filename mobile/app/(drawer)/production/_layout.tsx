import { Stack } from 'expo-router';

import { DrawerToggleButton } from '@/components/drawer/DrawerToggleButton';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';

export default function ProductionLayout() {
  const scheme = useColorScheme();
  const palette = Colors[scheme];

  return (
    <Stack
      screenOptions={{
        headerShown: false,
        headerStyle: { backgroundColor: palette.surface },
        headerTintColor: palette.text,
        headerTitleStyle: { fontWeight: '700' },
      }}>
      <Stack.Screen
        name="index"
        // Hub provides its own header; suppress the Stack header to avoid the
        // double bar.
        options={{ headerShown: false, title: 'Production' }}
      />
      <Stack.Screen name="product-types/index" options={{ title: 'Product types' }} />
      <Stack.Screen
        name="product-types/[id]/templates/index"
        options={{ title: 'Process templates' }}
      />
      <Stack.Screen
        name="templates/[id]/steps/index"
        options={{ title: 'Edit steps' }}
      />
      <Stack.Screen
        name="templates/[id]/qc-templates/index"
        options={{ title: 'QC templates' }}
      />
      <Stack.Screen name="shifts/index" options={{ title: 'Shifts' }} />
      <Stack.Screen name="downtime/index" options={{ headerShown: false }} />
    </Stack>
  );
}
