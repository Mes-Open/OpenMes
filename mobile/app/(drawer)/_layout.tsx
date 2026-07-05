import { Drawer } from 'expo-router/drawer';
import { View } from 'react-native';
import { GestureHandlerRootView } from 'react-native-gesture-handler';

import { PhoneBottomNav } from '@/components/drawer/PhoneBottomNav';
import { PhoneTopBar } from '@/components/drawer/PhoneTopBar';
import { TopClock } from '@/components/drawer/TopClock';
import { DrawerToggleButton } from '@/components/drawer/DrawerToggleButton';
import { TabletSidebar } from '@/components/tablet/TabletSidebar';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useDeviceClass } from '@/hooks/useDeviceClass';
import { useSettingsStore } from '@/stores/settingsStore';

export default function DrawerLayout() {
  const scheme = useColorScheme();
  const palette = Colors[scheme];
  // Only enable the permanent sidebar when there's room for the tablet layout.
  // A tablet held in portrait (or a Slide Over window) falls back to phone
  // chrome — the slide-over drawer + bottom tab bar.
  const { useTabletLayout: isTablet } = useDeviceClass();
  // Collapsed = the icon rail (96px); expanded = web-style labelled sidebar.
  const collapsed = useSettingsStore((s) => s.sidebarCollapsed);

  return (
    <GestureHandlerRootView style={{ flex: 1 }}>
      <Drawer
        drawerContent={(props) => <TabletSidebar {...props} />}
        screenOptions={{
          // Tablet: permanent sidebar — 96px icon rail (collapsed) or 248px
          // labelled sidebar (expanded), mirroring the web. Phone: 296px slide-over.
          drawerStyle: {
            backgroundColor: palette.surface,
            width: isTablet ? (collapsed ? 96 : 248) : 296,
          },
          drawerType: isTablet ? 'permanent' : 'front',
          // Every screen renders its own chrome — never show the raw drawer header.
          headerShown: false,
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
        }}
        // Web parity: the AppLayout renders a live Europe/Warsaw clock strip
        // above <main> on desktop — mirror it above every tablet screen.
        screenLayout={({ children }: { children: React.ReactNode }) =>
          isTablet ? (
            // Web parity: the AppLayout's live Europe/Warsaw clock strip.
            <View style={{ flex: 1 }}>
              <TopClock />
              {children}
            </View>
          ) : (
            // Phones: no permanent sidebar — breadcrumb top bar (safe-area
            // padded, always offers a way back) + bottom navigation + drawer.
            <View style={{ flex: 1 }}>
              <PhoneTopBar />
              <View style={{ flex: 1 }}>{children}</View>
              <PhoneBottomNav />
            </View>
          )
        }>
        {/* Routes whose own nested Stack renders the header — hide the drawer header */}
        <Drawer.Screen name="supervisor" options={{ headerShown: false, title: 'Supervisor' }} />
        <Drawer.Screen name="admin" options={{ headerShown: false, title: 'Admin' }} />
        <Drawer.Screen name="production" options={{ headerShown: false, title: 'Production' }} />
        <Drawer.Screen name="structure" options={{ headerShown: false, title: 'Structure' }} />
        <Drawer.Screen name="hr" options={{ headerShown: false, title: 'HR' }} />
        <Drawer.Screen name="maintenance" options={{ headerShown: false, title: 'Maintenance' }} />
        <Drawer.Screen name="connectivity" options={{ headerShown: false, title: 'Connectivity' }} />
        <Drawer.Screen name="quality" options={{ headerShown: false, title: 'Quality' }} />
        <Drawer.Screen name="pakowanie" options={{ headerShown: false, title: 'Pakowanie' }} />
        <Drawer.Screen name="orders" options={{ headerShown: false, title: 'Orders' }} />
        <Drawer.Screen
          name="work-orders/[id]"
          options={{ headerShown: false, title: 'Work order' }}
        />

        {/* Single-file routes — drawer header applies */}
        <Drawer.Screen name="schedule" options={{ headerShown: false, title: 'Schedule' }} />
        <Drawer.Screen name="settings/index" options={{ headerShown: false, title: 'Settings' }} />
      </Drawer>
    </GestureHandlerRootView>
  );
}
