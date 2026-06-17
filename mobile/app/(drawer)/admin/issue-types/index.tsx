import { MissingScreen } from '@/components/ui/MissingScreen';

export default function IssueTypesPage() {
  return (
    <MissingScreen
      title="Issue types"
      subtitle="ADMIN · CATALOG"
      endpoint="GET /api/v1/issue-types · POST · PATCH · DELETE"
      note="Catalog of Andon issue types — create, rename, deactivate. Admins only. Operator-facing list is read-only and surfaces from the same endpoint."
    />
  );
}
