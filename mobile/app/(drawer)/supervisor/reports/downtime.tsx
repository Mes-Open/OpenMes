import { MissingScreen } from '@/components/ui/MissingScreen';

export default function DowntimeReportPage() {
  return (
    <MissingScreen
      title="Downtime report"
      subtitle="SUPERVISOR · REPORTS"
      endpoint="GET /api/v1/reports/downtime"
    />
  );
}
