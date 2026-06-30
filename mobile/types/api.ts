// API response types based on /home/mateusz/dev/work/openmes/OpenMes/backend/routes/api.php
// and OpenMes/docs/API_DOCUMENTATION.md

export type Role = 'Admin' | 'Supervisor' | 'Operator';

/** Stored custom-field values on an entity: { key: value }. */
export type CustomFieldValues = Record<string, unknown>;

export type CustomFieldType =
  | 'text'
  | 'textarea'
  | 'number'
  | 'integer'
  | 'boolean'
  | 'date'
  | 'datetime'
  | 'select'
  | 'multiselect';

/** Admin-defined custom-field schema (read-only on mobile in v1). */
export interface CustomFieldDefinition {
  id: number;
  entity_type: string;
  key: string;
  label: string;
  type: CustomFieldType;
  config: { options?: { value: string; label: string }[]; min?: number; max?: number } | null;
  required: boolean;
  position: number;
  is_active: boolean;
}

export type WorkOrderStatus =
  | 'PENDING'
  | 'ACCEPTED'
  | 'IN_PROGRESS'
  | 'BLOCKED'
  | 'PAUSED'
  | 'DONE'
  | 'REJECTED'
  | 'CANCELLED';

export type BatchStatus = 'PENDING' | 'IN_PROGRESS' | 'DONE' | 'CANCELLED';

export type BatchStepStatus = 'PENDING' | 'IN_PROGRESS' | 'DONE' | 'SKIPPED';

export type IssueStatus = 'OPEN' | 'ACKNOWLEDGED' | 'RESOLVED' | 'CLOSED';

export interface Line {
  id: number;
  name: string;
  code?: string | null;
  description?: string | null;
  division_id?: number | null;
  is_active?: boolean;
  workstations_count?: number;
  work_orders_count?: number;
  users_count?: number;
}

export interface Permission {
  id: number;
  name: string;
}

export interface UserRole {
  id: number;
  name: Role | string;
  permissions?: Permission[];
}

export interface User {
  id: number;
  username: string;
  email?: string | null;
  name?: string | null;
  account_type?: 'user' | 'workstation';
  workstation_id?: number | null;
  worker_id?: number | null;
  force_password_change?: boolean;
  last_login_at?: string | null;
  role?: Role | string;
  roles?: UserRole[];
  lines?: Line[];
}

export interface LoginResponse {
  token: string;
  user: User;
}

export interface ProductType {
  id: number;
  name: string;
  code?: string | null;
  custom_fields?: CustomFieldValues | null;
}

export interface WorkOrder {
  id: number;
  order_no: string;
  status: WorkOrderStatus;
  planned_qty: number;
  produced_qty?: number;
  priority?: number | string | null;
  due_date?: string | null;
  line_id?: number | null;
  line?: Line | null;
  product_type_id?: number | null;
  product_type?: ProductType | null;
  notes?: string | null;
  process_snapshot?: { template_id?: number; [k: string]: unknown } | null;
  custom_fields?: CustomFieldValues | null;
  created_at?: string;
  updated_at?: string;
  batches?: Batch[];
}

export interface BatchStep {
  id: number;
  batch_id: number;
  name: string;
  instruction?: string | null;
  status: BatchStepStatus;
  sequence?: number;
  workstation_id?: number | null;
  workstation?: { id: number; name: string; code?: string | null } | null;
  expected_duration_seconds?: number | null;
  started_at?: string | null;
  completed_at?: string | null;
  started_by_id?: number | null;
  completed_by_id?: number | null;
  produced_qty?: number | null;
}

export interface Batch {
  id: number;
  work_order_id: number;
  status: BatchStatus;
  target_qty: number;
  produced_qty?: number;
  workstation_id?: number | null;
  workstation?: { id: number; name: string } | null;
  lot_number?: string | null;
  started_at?: string | null;
  completed_at?: string | null;
  steps?: BatchStep[];
  work_order?: WorkOrder;
}

export interface IssueType {
  id: number;
  name: string;
  is_blocking?: boolean;
  description?: string | null;
}

export interface Issue {
  id: number;
  status: IssueStatus;
  issue_type_id?: number;
  issue_type?: IssueType | null;
  description?: string | null;
  resolution_notes?: string | null;
  work_order_id?: number | null;
  work_order?: WorkOrder | null;
  batch_id?: number | null;
  batch_step_id?: number | null;
  line_id?: number | null;
  line?: Line | null;
  reported_by_id?: number | null;
  reported_by?: User | null;
  acknowledged_by_id?: number | null;
  acknowledged_at?: string | null;
  resolved_at?: string | null;
  closed_at?: string | null;
  created_at?: string;
  updated_at?: string;
}

export interface AnalyticsOverview {
  // Flat shape returned by /api/v1/analytics/overview.
  total_work_orders?: number;
  pending_work_orders?: number;
  in_progress_work_orders?: number;
  active_work_orders?: number;
  completed_work_orders?: number;
  done_today_work_orders?: number;
  blocked_work_orders?: number;

  total_batches?: number;
  active_batches?: number;

  open_issues?: number;
  blocking_issues?: number;
  critical_issues?: number;

  active_lines?: number;
}

export interface ApiPaginated<T> {
  data: T[];
  meta?: {
    current_page?: number;
    last_page?: number;
    per_page?: number;
    total?: number;
  };
}

export interface ApiEnvelope<T> {
  data: T;
  message?: string;
  meta?: Record<string, unknown>;
}
