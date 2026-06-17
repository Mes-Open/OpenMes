import { ScheduleScreen } from '@/screens/(drawer)/schedule';

/**
 * Admin Schedule — same screen as /schedule, mounted under /admin/schedule
 * so the URL stays in admin context. Single source of truth for the screen
 * itself; only the URL differs.
 */
export default function AdminSchedulePage() {
  return <ScheduleScreen />;
}
