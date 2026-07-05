/**
 * Operator route group — sidebar-less chrome mirroring the web OperatorLayout.
 * Each screen renders the OperatorTopBar itself; on handsets this layout adds
 * the operator bottom navigation (Queue / Workstation / Switch Line), since the
 * top bar's pills don't fit a phone width.
 */
import { Stack } from 'expo-router';
import { View } from 'react-native';

import { OperatorBottomNav } from '@/components/operator/OperatorBottomNav';
import { useDeviceClass } from '@/hooks/useDeviceClass';

export default function OperatorLayout() {
  const { useTabletLayout: isTablet } = useDeviceClass();

  return (
    <Stack
      screenOptions={{ headerShown: false }}
      screenLayout={
        isTablet
          ? undefined
          : ({ children }: { children: React.ReactNode }) => (
              <View style={{ flex: 1 }}>
                <View style={{ flex: 1 }}>{children}</View>
                <OperatorBottomNav />
              </View>
            )
      }>
      <Stack.Screen name="queue" />
      <Stack.Screen name="workstation" />
    </Stack>
  );
}
