import { TabletPlantWall } from '@/screens/tablet/PlantWall';

/**
 * Admin Dashboard — the canonical admin landing. Renders the existing
 * TabletPlantWall (previously at /admin/wall) so the URL matches the web
 * admin's /admin/dashboard path. The legacy /admin/wall route stays for
 * backward compatibility with any deep links.
 */
export default function AdminDashboardPage() {
  return <TabletPlantWall />;
}
