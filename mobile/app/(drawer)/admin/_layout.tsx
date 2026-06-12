import { Redirect, Stack } from "expo-router";

import Colors from "@/constants/Colors";
import { useColorScheme } from "@/components/useColorScheme";
import { getRole, useAuthStore } from "@/stores/authStore";

export default function AdminLayout() {
  const scheme = useColorScheme();
  const palette = Colors[scheme];

  // Hard gate: only Admins land here. Supervisors get bounced to their hub,
  // operators to theirs. The API itself rejects unauthorized callers, so this
  // is purely a navigation guard.
  //
  // If `user` is null we are mid-auth (rehydrating, or right after a 401
  // cleared the session). DON'T fall through to redirecting to /operator —
  // that would silently drop an admin into the operator hub. Let AuthGate
  // bounce to /login. Returning null keeps the screen blank until then.
  const user = useAuthStore((s) => s.user);
  if (!user) return null;
  const role = getRole(user);
  if (role !== "Admin") {
    return <Redirect href={role === "Supervisor" ? "/supervisor" : "/operator"} />;
  }

  return (
    <Stack
      screenOptions={{
        // Every screen in this stack renders its own chrome (ScreenHeader or
        // ListScreen). Hide the default Stack header so we don't get a double
        // bar / unstyled back button.
        headerShown: false,
        headerStyle: { backgroundColor: palette.surface },
        headerTintColor: palette.text,
        headerTitleStyle: { fontWeight: "700" },
      }}
    >
      <Stack.Screen
        name="index"
        options={{ headerShown: false, title: "Admin" }}
      />
      <Stack.Screen name="users/index" options={{ title: "Users" }} />
      <Stack.Screen
        name="system-settings/index"
        options={{ title: "System settings" }}
      />
      <Stack.Screen name="modules/index" options={{ title: "Modules" }} />
      <Stack.Screen name="companies/index" options={{ title: "Companies" }} />
      <Stack.Screen
        name="cost-sources/index"
        options={{ title: "Cost sources" }}
      />
      <Stack.Screen
        name="anomaly-reasons/index"
        options={{ title: "Anomaly reasons" }}
      />
      <Stack.Screen
        name="subassemblies/index"
        options={{ title: "Subassemblies" }}
      />
      <Stack.Screen
        name="lot-sequences/index"
        options={{ title: "LOT sequences" }}
      />
      <Stack.Screen name="audit-logs/index" options={{ title: "Audit logs" }} />
      <Stack.Screen name="event-logs/index" options={{ title: "Event logs" }} />
      <Stack.Screen name="reports/index" options={{ title: "Reports" }} />
      <Stack.Screen name="api-tokens/index" options={{ title: "API tokens" }} />
      <Stack.Screen
        name="alerts-dashboard/index"
        options={{ title: "Alerts dashboard" }}
      />
      <Stack.Screen name="oee/index" />
      <Stack.Screen name="subiekt/index" />
      <Stack.Screen name="materials/index" options={{ title: "Materials" }} />
      <Stack.Screen name="wall/index" options={{ title: "Plant Wall" }} />
      <Stack.Screen name="dashboard/index" options={{ title: "Dashboard" }} />
      <Stack.Screen name="orders/index" options={{ title: "Orders" }} />
      <Stack.Screen name="work-orders/[id]" options={{ title: "Work order" }} />
      <Stack.Screen name="schedule/index" options={{ title: "Schedule" }} />
      <Stack.Screen
        name="catalog-admin/index"
        options={{ title: "Catalog admin" }}
      />
      <Stack.Screen
        name="connectivity-admin/index"
        options={{ title: "Connectivity admin" }}
      />
      <Stack.Screen
        name="hr-command/index"
        options={{ title: "HR command" }}
      />
      <Stack.Screen
        name="quality-command/index"
        options={{ title: "Quality command" }}
      />
      <Stack.Screen
        name="maintenance-command/index"
        options={{ title: "Maintenance command" }}
      />
      <Stack.Screen
        name="lot-genealogy/index"
        options={{ title: "Lot genealogy" }}
      />
      <Stack.Screen
        name="issue-types/index"
        options={{ title: "Issue types" }}
      />
      <Stack.Screen
        name="connectivity-mappings/index"
        options={{ title: "Topic mappings" }}
      />
      <Stack.Screen
        name="update-check/index"
        options={{ title: "Update check" }}
      />
      <Stack.Screen
        name="system-logs/index"
        options={{ title: "System logs" }}
      />
      <Stack.Screen
        name="inspection-plans/index"
        options={{ title: "Inspection plans" }}
      />
      <Stack.Screen
        name="employee-schedule"
        options={{ title: "Employee schedule" }}
      />
    </Stack>
  );
}
