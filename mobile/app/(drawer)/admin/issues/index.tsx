import { AdminIssuesScreen } from '@/screens/(drawer)/admin/issues/index';

/**
 * Admin Issues — mirrors the web admin issues table. Data via /api/v1/issues
 * (useIssues); rows open the issue detail.
 */
export default function AdminIssuesPage() {
  return <AdminIssuesScreen />;
}
