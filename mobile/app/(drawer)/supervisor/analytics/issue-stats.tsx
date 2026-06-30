import { MissingScreen } from '@/components/ui/MissingScreen';

export default function IssueStatsPage() {
  return (
    <MissingScreen
      title="Issue statistics"
      subtitle="SUPERVISOR · ANDON"
      endpoint="GET /api/v1/analytics/issue-stats"
    />
  );
}
