import { Drawer } from 'expo-router/drawer';
import { GestureHandlerRootView } from 'react-native-gesture-handler';

import { DrawerContent } from '@/components/drawer/DrawerContent';
import { DrawerToggleButton } from '@/components/drawer/DrawerToggleButton';
import { TabletSidebar } from '@/components/tablet/TabletSidebar';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useDeviceClass } from '@/hooks/useDeviceClass';

export default function DrawerLayout() {
  const scheme = useColorScheme();
  const palette = Colors[scheme];
  // Only enable the permanent sidebar when there's room for the tablet layout.
  // A tablet held in portrait (or a Slide Over window) falls back to phone
  // chrome — the slide-over drawer + bottom tab bar.
  const { useTabletLayout: isTablet } = useDeviceClass();

  return (
    <GestureHandlerRootView style={{ flex: 1 }}>
      <Drawer
        drawerContent={(props) =>
          isTablet ? <TabletSidebar {...props} /> : <DrawerContent {...props} />
        }
        screenOptions={{
          // Tablet: permanent 96px icon rail. Phone: 296px slide-over.
          drawerStyle: {
            backgroundColor: palette.surface,
            width: isTablet ? 96 : 296,
          },
          drawerType: isTablet ? 'permanent' : 'front',
          // No hamburger needed when the sidebar is always visible.
          swipeEnabled: !isTablet,
          headerStyle: {
            backgroundColor: palette.background,
            borderBottomWidth: 0,
            elevation: 0,
          },
          headerShadowVisible: false,
          headerTintColor: palette.text,
          headerTitleStyle: { fontWeight: '700', fontSize: 17, letterSpacing: -0.2 },
          headerTitleAlign: 'center',
          headerLeft: isTablet ? () => null : () => <DrawerToggleButton />,
        }}>
        {/* Routes whose own nested Stack renders the header — hide the drawer header */}
        <Drawer.Screen name="(tabs)" options={{ headerShown: false, title: 'Home' }} />
        <Drawer.Screen name="operator" options={{ headerShown: false, title: 'Operator' }} />
        <Drawer.Screen name="supervisor" options={{ headerShown: false, title: 'Supervisor' }} />
        <Drawer.Screen name="admin" options={{ headerShown: false, title: 'Admin' }} />
        <Drawer.Screen name="production" options={{ headerShown: false, title: 'Production' }} />
        <Drawer.Screen name="structure" options={{ headerShown: false, title: 'Structure' }} />
        <Drawer.Screen name="hr" options={{ headerShown: false, title: 'HR' }} />
        <Drawer.Screen name="maintenance" options={{ headerShown: false, title: 'Maintenance' }} />
        <Drawer.Screen name="connectivity" options={{ headerShown: false, title: 'Connectivity' }} />
        <Drawer.Screen name="pakowanie" options={{ headerShown: false, title: 'Pakowanie' }} />
        <Drawer.Screen name="orders" options={{ headerShown: false, title: 'Orders' }} />
        <Drawer.Screen
          name="work-orders/[id]"
          options={{ headerShown: false, title: 'Work order' }}
        />

        {/* Single-file routes — drawer header applies */}
        <Drawer.Screen name="schedule" options={{ headerShown: false, title: 'Schedule' }} />
      </Drawer>
    </GestureHandlerRootView>
  );
}
