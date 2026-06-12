import { Tabs } from 'expo-router';

import { BottomTabBar } from '@/components/ui/BottomTabBar';
import { useDeviceClass } from '@/hooks/useDeviceClass';

export default function TabLayout() {
  const { useTabletLayout } = useDeviceClass();
  return (
    <Tabs
      // On tablet (landscape) the permanent sidebar replaces the bottom tab bar
      // — render an empty tab bar so the sidebar is the only nav surface. In
      // portrait or on phone we keep the bottom tab bar.
      tabBar={useTabletLayout ? () => null : (props) => <BottomTabBar {...props} />}
      screenOptions={{ headerShown: false }}>
      <Tabs.Screen name="index" />
      <Tabs.Screen name="orders" />
      <Tabs.Screen name="scan" />
      <Tabs.Screen name="issues" />
      <Tabs.Screen name="profile" />
    </Tabs>
  );
}
