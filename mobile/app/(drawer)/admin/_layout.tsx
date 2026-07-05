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
    return <Redirect href={role === "Supervisor" ? "/supervisor" : "/orders/work-orders"} />;
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
        name="scrap-reasons/index"
        options={{ title: "Scrap reasons" }}
      />
      <Stack.Screen name="scrap-reasons/new" options={{ headerShown: false, title: "New scrap reason" }} />
      <Stack.Screen name="scrap-reasons/[id]" options={{ headerShown: false, title: "Edit scrap reason" }} />
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
      <Stack.Screen name="materials/index" options={{ title: "Materials" }} />
      <Stack.Screen name="materials/new" options={{ headerShown: false, title: "New material" }} />
      <Stack.Screen name="materials/[id]/index" options={{ headerShown: false, title: "Material" }} />
      <Stack.Screen name="materials/[id]/edit" options={{ headerShown: false, title: "Edit material" }} />
      <Stack.Screen name="line-statuses/index" options={{ title: "Line statuses" }} />
      <Stack.Screen name="schedule-capacity/index" options={{ title: "Capacity" }} />
      <Stack.Screen name="pallets/index" options={{ title: "Pallets" }} />
      <Stack.Screen name="integrations/index" options={{ title: "Integrations" }} />
      <Stack.Screen name="custom-fields/index" options={{ title: "Custom fields" }} />
      <Stack.Screen name="qc-triggers/index" options={{ title: "QC triggers" }} />
      <Stack.Screen name="webhooks/new" options={{ headerShown: false, title: "New webhook" }} />
      <Stack.Screen name="webhooks/[id]/edit" options={{ headerShown: false, title: "Edit webhook" }} />
      <Stack.Screen name="integrations/new" options={{ headerShown: false, title: "New integration" }} />
      <Stack.Screen name="integrations/[id]/edit" options={{ headerShown: false, title: "Edit integration" }} />
      <Stack.Screen name="custom-fields/new" options={{ headerShown: false, title: "New custom field" }} />
      <Stack.Screen name="custom-fields/[id]/edit" options={{ headerShown: false, title: "Edit custom field" }} />
      <Stack.Screen name="qc-triggers/new" options={{ headerShown: false, title: "New QC trigger" }} />
      <Stack.Screen name="qc-triggers/[id]/edit" options={{ headerShown: false, title: "Edit QC trigger" }} />
      <Stack.Screen name="pallets/new" options={{ headerShown: false, title: "New pallet" }} />
      <Stack.Screen name="pallets/[id]/edit" options={{ headerShown: false, title: "Edit pallet" }} />
      <Stack.Screen name="view-templates/new" options={{ headerShown: false, title: "New view template" }} />
      <Stack.Screen name="view-templates/[id]/edit" options={{ headerShown: false, title: "Edit view template" }} />
      <Stack.Screen name="dashboard/index" options={{ title: "Dashboard" }} />
      <Stack.Screen name="orders/index" options={{ title: "Orders" }} />
      <Stack.Screen name="work-orders/[id]" options={{ title: "Work order" }} />
      <Stack.Screen name="schedule/index" options={{ title: "Schedule" }} />
      <Stack.Screen
        name="lot-genealogy/index"
        options={{ title: "Lot genealogy" }}
      />
      <Stack.Screen
        name="issue-types/index"
        options={{ title: "Issue types" }}
      />
      <Stack.Screen name="issue-types/new" options={{ headerShown: false, title: "New issue type" }} />
      <Stack.Screen name="issue-types/[id]" options={{ headerShown: false, title: "Edit issue type" }} />
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
