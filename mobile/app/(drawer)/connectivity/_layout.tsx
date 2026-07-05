import { Stack } from 'expo-router';

import { DrawerToggleButton } from '@/components/drawer/DrawerToggleButton';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';

export default function ConnectivityLayout() {
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
      <Stack.Screen name="index" options={{ headerShown: false, title: 'Connectivity' }} />
      <Stack.Screen name="connections/index" options={{ title: 'Machine connections' }} />
      <Stack.Screen name="mqtt/index" options={{ headerShown: false, title: 'MQTT' }} />
      <Stack.Screen name="modbus/index" options={{ headerShown: false, title: 'Modbus' }} />
      <Stack.Screen name="opcua/index" options={{ headerShown: false, title: 'OPC UA' }} />
      <Stack.Screen name="connections/[id]/index" options={{ headerShown: false, title: 'Connection' }} />
      <Stack.Screen name="connections/[id]/edit" options={{ headerShown: false, title: 'Edit connection' }} />
      <Stack.Screen name="connections/new" options={{ headerShown: false, title: 'New connection' }} />
      <Stack.Screen name="topics/index" options={{ title: 'Topics' }} />
      <Stack.Screen name="topics/[id]/index" options={{ headerShown: false, title: 'Topic' }} />
      <Stack.Screen name="topics/[id]/edit" options={{ headerShown: false, title: 'Edit topic' }} />
      <Stack.Screen name="topics/new" options={{ headerShown: false, title: 'New topic' }} />
      <Stack.Screen name="mappings/[id]/edit" options={{ headerShown: false, title: 'Edit mapping' }} />
      <Stack.Screen name="mappings/new" options={{ headerShown: false, title: 'New mapping' }} />
      <Stack.Screen name="messages/index" options={{ title: 'Messages' }} />
    </Stack>
  );
}
