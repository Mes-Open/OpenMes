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
    // Locally patched Geist Mono: the upstream font's default zero is slashed
    // (and there is no per-call way to disable it across iOS+Android), so
    // assets/fonts/ ships copies whose default '0' glyph is the plain (ss09)
    // round zero. Family name is kept — Geist's OFL has no Reserved Font Name.
    // See assets/fonts/README.md for the patch recipe.
    GeistMono_400Regular: require('../assets/fonts/GeistMono_400Regular.ttf'),
    GeistMono_500Medium: require('../assets/fonts/GeistMono_500Medium.ttf'),
    GeistMono_600SemiBold: require('../assets/fonts/GeistMono_600SemiBold.ttf'),
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
  // Remount the whole navigator whenever the signed-in identity changes —
  // otherwise the previous user's navigation history survives logout/login
  // and the back button pops into the other role's screens.
  const userId = useAuthStore((s) => s.user?.id ?? 'anon');
  return (
    <ThemeProvider value={colorScheme === 'dark' ? DarkTheme : DefaultTheme}>
      <AuthGate />
      {/* Every screen renders its own chrome — hide the default Stack header
          globally so we never see an unstyled back button on top of the app. */}
      <Stack key={String(userId)} screenOptions={{ headerShown: false }}>
        <Stack.Screen name="(drawer)" />
        <Stack.Screen name="login" />
        <Stack.Screen name="select-line" />
        {/* Operator experience — root-level (no drawer/sidebar), mirroring the
            web operator chrome. See screens/operator/* + components/operator. */}
        <Stack.Screen name="operator" />
        {/* WO detail lives inside the drawer so the sidebar stays visible —
            see app/(drawer)/work-orders/[id].tsx. */}
        <Stack.Screen name="work-orders/[id]/run/[batchId]" />
        {/* Quality / inspections — operator + supervisor (list lives under (drawer)) */}
        <Stack.Screen
          name="quality/inspections/[id]/run/index"
          options={{ title: 'Run inspection', presentation: 'modal' }}
        />
        {/* Admin */}
        {/* HR */}
        {/* Maintenance */}
        {/* Connectivity */}
        {/* Pakowanie */}
        {/* Production */}
        {/* Structure */}
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
    // Web parity: only Operators go through line selection (Admins land on the
    // admin dashboard, Supervisors on the supervisor dashboard — routes/web.php).
    const needsLineSelection =
      !!user && getRole(user) === 'Operator' && lines.length > 1 && activeLineId == null;

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
          : '/operator/queue';

    // The bare `/` has no concrete screen — detect it by inspecting segments:
    // when every segment is just the `(drawer)` route group (i.e. there's no
    // concrete path after it), bounce the user into their role landing so the
    // URL is never just `/`. expo-router types `segments` as a tuple that
    // always has at least one entry.
    const onBareRoot = segments.every((s) => s === '(drawer)');

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
