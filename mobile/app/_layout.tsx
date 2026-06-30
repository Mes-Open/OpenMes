import FontAwesome from '@expo/vector-icons/FontAwesome';
import { DarkTheme, DefaultTheme, ThemeProvider } from '@react-navigation/native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { useFonts } from 'expo-font';
import { Stack, useRouter, useSegments } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { useEffect, useRef } from 'react';
import 'react-native-reanimated';

import { useColorScheme } from '@/components/useColorScheme';
import i18n from '@/lib/i18n';
import { getRole, useAuthStore } from '@/stores/authStore';
import { useSettingsStore } from '@/stores/settingsStore';

export {
  ErrorBoundary,
} from 'expo-router';

export const unstable_settings = {
  initialRouteName: '(drawer)',
};

SplashScreen.preventAutoHideAsync();

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      staleTime: 30_000,
    },
  },
});

export default function RootLayout() {
  const [loaded, error] = useFonts({
    // Geist sans + Geist Mono — single source of truth for typography.
    // The design's @import spec is Geist 300–700 + Geist Mono 400–600; we
    // only load the weights actually referenced in the app so we don't ship
    // a wall of unused .ttf assets. If a screen needs Thin (300) or another
    // weight, add it here AND reference it from code.
    Geist_400Regular: require('@expo-google-fonts/geist/400Regular/Geist_400Regular.ttf'),
    Geist_500Medium: require('@expo-google-fonts/geist/500Medium/Geist_500Medium.ttf'),
    Geist_600SemiBold: require('@expo-google-fonts/geist/600SemiBold/Geist_600SemiBold.ttf'),
    Geist_700Bold: require('@expo-google-fonts/geist/700Bold/Geist_700Bold.ttf'),
    GeistMono_400Regular: require('@expo-google-fonts/geist-mono/400Regular/GeistMono_400Regular.ttf'),
    GeistMono_500Medium: require('@expo-google-fonts/geist-mono/500Medium/GeistMono_500Medium.ttf'),
    GeistMono_600SemiBold: require('@expo-google-fonts/geist-mono/600SemiBold/GeistMono_600SemiBold.ttf'),
    ...FontAwesome.font,
  });

  const authHydrated = useAuthStore((s) => s.hydrated);
  const settingsHydrated = useSettingsStore((s) => s.hydrated);
  const persistedLanguage = useSettingsStore((s) => s.language);
  const ready = loaded && authHydrated && settingsHydrated;

  // Apply persisted language once settings have hydrated (zustand restores async).
  useEffect(() => {
    if (settingsHydrated && persistedLanguage && i18n.language !== persistedLanguage) {
      i18n.changeLanguage(persistedLanguage);
    }
  }, [settingsHydrated, persistedLanguage]);

  useEffect(() => {
    if (error) throw error;
  }, [error]);

  useEffect(() => {
    if (ready) SplashScreen.hideAsync();
  }, [ready]);

  if (!ready) return null;

  return (
    <QueryClientProvider client={queryClient}>
      <RootLayoutNav />
    </QueryClientProvider>
  );
}

function RootLayoutNav() {
  const colorScheme = useColorScheme();
  return (
    <ThemeProvider value={colorScheme === 'dark' ? DarkTheme : DefaultTheme}>
      <AuthGate />
      {/* Every screen renders its own chrome — hide the default Stack header
          globally so we never see an unstyled back button on top of the app. */}
      <Stack screenOptions={{ headerShown: false }}>
        <Stack.Screen name="(drawer)" />
        <Stack.Screen name="login" />
        <Stack.Screen name="select-line" />
        {/* WO detail lives inside the drawer so the sidebar stays visible —
            see app/(drawer)/work-orders/[id].tsx. */}
        <Stack.Screen name="work-orders/[id]/run/[batchId]" />
        <Stack.Screen name="issues/[id]" />
        <Stack.Screen name="issues/new" options={{ presentation: 'modal' }} />
        <Stack.Screen name="work-orders/[id]/anomalies/index" />
        <Stack.Screen name="work-orders/[id]/anomalies/new" options={{ presentation: 'modal' }} />
        <Stack.Screen name="work-orders/[id]/costs/index" />
        <Stack.Screen name="work-orders/[id]/costs/new" options={{ presentation: 'modal' }} />
        <Stack.Screen name="work-orders/[id]/attachments/index" />
        <Stack.Screen
          name="work-orders/new/index"
          options={{ title: 'New work order', presentation: 'modal' }}
        />
        {/* Quality / inspections — operator + supervisor */}
        <Stack.Screen name="quality/inspections/index" options={{ title: 'Inspections' }} />
        <Stack.Screen name="quality/inspections/[id]" options={{ title: 'Inspection' }} />
        <Stack.Screen
          name="quality/inspections/[id]/run/index"
          options={{ title: 'Run inspection', presentation: 'modal' }}
        />
        {/* Admin */}
        <Stack.Screen name="admin/users/[id]" options={{ title: 'Edit user' }} />
        <Stack.Screen name="admin/users/new" options={{ title: 'New user', presentation: 'modal' }} />
        <Stack.Screen name="admin/companies/[id]" options={{ title: 'Edit company' }} />
        <Stack.Screen name="admin/companies/new" options={{ title: 'New company', presentation: 'modal' }} />
        <Stack.Screen name="admin/cost-sources/[id]" options={{ title: 'Edit cost source' }} />
        <Stack.Screen name="admin/cost-sources/new" options={{ title: 'New cost source', presentation: 'modal' }} />
        <Stack.Screen name="admin/anomaly-reasons/[id]" options={{ title: 'Edit reason' }} />
        <Stack.Screen name="admin/anomaly-reasons/new" options={{ title: 'New reason', presentation: 'modal' }} />
        <Stack.Screen name="admin/subassemblies/[id]" options={{ title: 'Edit subassembly' }} />
        <Stack.Screen name="admin/subassemblies/new" options={{ title: 'New subassembly', presentation: 'modal' }} />
        <Stack.Screen name="admin/lot-sequences/[id]" options={{ title: 'Edit LOT sequence' }} />
        <Stack.Screen name="admin/lot-sequences/new" options={{ title: 'New LOT sequence', presentation: 'modal' }} />
        <Stack.Screen name="admin/materials/[id]" />
        <Stack.Screen name="admin/oee/[lineId]" />
        {/* HR */}
        <Stack.Screen name="hr/skills/[id]" options={{ title: 'Edit skill' }} />
        <Stack.Screen name="hr/skills/new" options={{ title: 'New skill', presentation: 'modal' }} />
        <Stack.Screen name="hr/wage-groups/[id]" options={{ title: 'Edit wage group' }} />
        <Stack.Screen name="hr/wage-groups/new" options={{ title: 'New wage group', presentation: 'modal' }} />
        <Stack.Screen name="hr/crews/[id]" options={{ title: 'Edit crew' }} />
        <Stack.Screen name="hr/crews/new" options={{ title: 'New crew', presentation: 'modal' }} />
        <Stack.Screen name="hr/workers/[id]" options={{ title: 'Edit worker' }} />
        <Stack.Screen name="hr/workers/new" options={{ title: 'New worker', presentation: 'modal' }} />
        {/* Maintenance */}
        <Stack.Screen name="maintenance/tools/[id]" options={{ title: 'Tool' }} />
        <Stack.Screen name="maintenance/tools/new" options={{ title: 'New tool', presentation: 'modal' }} />
        <Stack.Screen name="maintenance/events/[id]" options={{ title: 'Event' }} />
        <Stack.Screen name="maintenance/events/new" options={{ title: 'New event', presentation: 'modal' }} />
        {/* Connectivity */}
        {/* Pakowanie */}
        <Stack.Screen name="pakowanie/eans/new" options={{ title: 'Add EAN', presentation: 'modal' }} />
        {/* Production */}
        <Stack.Screen name="production/product-types/[id]" options={{ title: 'Edit product type' }} />
        <Stack.Screen name="production/product-types/new" options={{ title: 'New product type', presentation: 'modal' }} />
        <Stack.Screen name="production/shifts/[id]" options={{ title: 'Edit shift' }} />
        <Stack.Screen name="production/shifts/new" options={{ title: 'New shift', presentation: 'modal' }} />
        <Stack.Screen name="production/templates/[id]" options={{ title: 'Process template' }} />
        <Stack.Screen name="production/templates/[id]/qc-templates/[qcId]" options={{ title: 'Edit QC template' }} />
        <Stack.Screen name="production/templates/[id]/qc-templates/new" options={{ title: 'New QC template', presentation: 'modal' }} />
        {/* Structure */}
        <Stack.Screen name="structure/divisions/[id]" options={{ title: 'Edit division' }} />
        <Stack.Screen name="structure/factories/[id]" options={{ title: 'Edit factory' }} />
        <Stack.Screen name="structure/factories/new" options={{ title: 'New factory', presentation: 'modal' }} />
        <Stack.Screen name="structure/sites/new" options={{ title: 'New site', presentation: 'modal' }} />
        <Stack.Screen name="structure/sites/[id]/edit" options={{ title: 'Edit site', presentation: 'modal' }} />
        <Stack.Screen name="structure/areas/new" options={{ title: 'New area', presentation: 'modal' }} />
        <Stack.Screen name="structure/areas/[id]/edit" options={{ title: 'Edit area', presentation: 'modal' }} />
        <Stack.Screen name="production/process-segments/new" options={{ title: 'New process segment', presentation: 'modal' }} />
        <Stack.Screen name="production/process-segments/[id]/edit" options={{ title: 'Edit process segment', presentation: 'modal' }} />
        <Stack.Screen name="hr/personnel-classes/new" options={{ title: 'New personnel class', presentation: 'modal' }} />
        <Stack.Screen name="hr/personnel-classes/[id]/edit" options={{ title: 'Edit personnel class', presentation: 'modal' }} />
        <Stack.Screen name="admin/inspection-plans/new" options={{ title: 'New inspection plan', presentation: 'modal' }} />
        <Stack.Screen name="admin/inspection-plans/[id]/edit" options={{ title: 'Edit inspection plan', presentation: 'modal' }} />
        <Stack.Screen name="structure/factories/[id]/divisions/new" options={{ title: 'New division', presentation: 'modal' }} />
        <Stack.Screen name="structure/lines/[id]" options={{ title: 'Edit line' }} />
        <Stack.Screen name="structure/lines/new" options={{ title: 'New line', presentation: 'modal' }} />
        <Stack.Screen name="structure/lines/[id]/statuses/new" options={{ title: 'New status', presentation: 'modal' }} />
        <Stack.Screen name="structure/lines/[id]/workstations/[workstationId]" options={{ title: 'Edit workstation' }} />
        <Stack.Screen name="structure/lines/[id]/workstations/new" options={{ title: 'New workstation', presentation: 'modal' }} />
        <Stack.Screen name="structure/workstation-types/[id]" options={{ title: 'Edit workstation type' }} />
        <Stack.Screen name="structure/workstation-types/new" options={{ title: 'New workstation type', presentation: 'modal' }} />
      </Stack>
    </ThemeProvider>
  );
}

function AuthGate() {
  const router = useRouter();
  const segments = useSegments();
  const token = useAuthStore((s) => s.token);
  const user = useAuthStore((s) => s.user);
  const activeLineId = useAuthStore((s) => s.activeLineId);
  const lastRouteRef = useRef<string | null>(null);

  useEffect(() => {
    const inAuthScreen = segments[0] === 'login';
    const inSelectLine = segments[0] === 'select-line';
    const lines = user?.lines ?? [];
    const needsLineSelection = !!user && lines.length > 1 && activeLineId == null;

    // Half-cleared auth (token present, user null) — happens when a 401
    // interceptor fires against a refetch after the browser has suspended
    // the tab for hours. Force a full clear so the redirect to /login below
    // fires deterministically instead of letting a role layout fall through
    // to /operator.
    if (token && !user) {
      useAuthStore.getState().clear();
      return;
    }

    // After login, drop the user on the hub that matches their role. Each
    // role hub is a separate Stack inside (drawer)/; the layouts enforce a
    // hard redirect if someone deep-links into a hub they aren't allowed to
    // see, so this is just the entry point. Role landings are deep paths so
    // the URL is never bare `/` — every authenticated screen carries a
    // role prefix.
    const role = getRole(user);
    const roleHome =
      role === 'Admin'
        ? '/admin/dashboard'
        : role === 'Supervisor'
          ? '/supervisor'
          : '/operator/today';

    // The bare `/` resolves to app/(drawer)/(tabs)/index.tsx — that's a
    // legacy hub we don't want users to land on. Detect by inspecting
    // segments: when the deepest segment is `(drawer)` or `(tabs)` (i.e.
    // there's no concrete path after the route groups), bounce the user
    // into their role landing so the URL is never just `/`.
    // expo-router types `segments` as a tuple that always has at least one
    // entry, so we just check that every segment is a route group.
    const onBareRoot = segments.every((s) => s === '(drawer)' || s === '(tabs)');

    let target: string | null = null;
    if (!token && !inAuthScreen) target = '/login';
    else if (token && inAuthScreen) target = needsLineSelection ? '/select-line' : roleHome;
    else if (token && needsLineSelection && !inSelectLine) target = '/select-line';
    else if (token && onBareRoot) target = roleHome;

    if (target && lastRouteRef.current !== target) {
      lastRouteRef.current = target;
      router.replace(target as never);
    }
    if (!target) lastRouteRef.current = null;
  }, [token, user, activeLineId, segments, router]);

  return null;
}
