import { MissingScreen } from '@/components/ui/MissingScreen';

export default function UpdateCheckPage() {
  return (
    <MissingScreen
      title="Update check"
      subtitle="ADMIN · SYSTEM"
      endpoint="GET /api/v1/system/update-check"
      note="Checks the install for a newer release. Today the server triggers updates via the download-based updater — the mobile app would surface a read-only status panel here."
    />
  );
}
