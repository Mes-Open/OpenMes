// User and Authentication Types
export interface User {
  id: number;
  username: string;
  email: string;
  roles: Role[];
  lines: Line[];
  force_password_change: boolean;
  last_login_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface Role {
  id: number;
  name: string;
  guard_name: string;
}

export interface Line {
  id: number;
  code: string;
  name: string;
  description: string | null;
  is_active: boolean;
}

export interface LoginCredentials {
  username: string;
  password: string;
}

export interface LoginResponse {
  message: string;
  data: {
    user: User;
    token: string;
    force_password_change: boolean;
  };
}

// Work Order Types
export interface WorkOrder {
  id: number;
  order_no: string;
  line_id: number;
  line: Line;
  product_type_id: number;
  product_type: ProductType;
  process_snapshot: ProcessSnapshot;
  planned_qty: number;
  produced_qty: number;
  status: 'PENDING' | 'IN_PROGRESS' | 'BLOCKED' | 'DONE' | 'CANCELLED';
  priority: number;
  due_date: string | null;
  description: string | null;
  created_at: string;
  updated_at: string;
  completed_at: string | null;
}

export interface ProductType {
  id: number;
  code: string;
  name: string;
  description: string | null;
  unit_of_measure: string;
  is_active: boolean;
}

export interface ProcessSnapshot {
  template_id: number;
  template_name: string;
  template_version: number;
  steps: SnapshotStep[];
}

export interface SnapshotStep {
  step_number: number;
  name: string;
  instruction: string | null;
  estimated_duration_minutes: number | null;
  workstation_id: number | null;
  workstation_name: string | null;
}

// Batch Types
export interface Batch {
  id: number;
  work_order_id: number;
  work_order?: WorkOrder;
  batch_number: number;
  target_qty: number;
  produced_qty: number;
  status: 'PENDING' | 'IN_PROGRESS' | 'DONE' | 'CANCELLED';
  started_at: string | null;
  completed_at: string | null;
  created_at: string;
  updated_at: string;
  steps?: BatchStep[];
}

export interface BatchStep {
  id: number;
  batch_id: number;
  batch?: Batch;
  step_number: number;
  name: string;
  instruction: string | null;
  status: 'PENDING' | 'IN_PROGRESS' | 'DONE' | 'SKIPPED';
  started_at: string | null;
  completed_at: string | null;
  started_by_id: number | null;
  started_by?: User;
  completed_by_id: number | null;
  completed_by?: User;
  duration_minutes: number | null;
  created_at: string;
  updated_at: string;
}

// Issue Types
export interface Issue {
  id: number;
  work_order_id: number;
  batch_step_id: number | null;
  issue_type_id: number;
  issue_type: IssueType;
  title: string;
  description: string | null;
  status: 'OPEN' | 'ACKNOWLEDGED' | 'RESOLVED' | 'CLOSED';
  reported_by_id: number;
  reported_by?: User;
  assigned_to_id: number | null;
  assigned_to?: User;
  reported_at: string;
  acknowledged_at: string | null;
  resolved_at: string | null;
  closed_at: string | null;
  resolution_notes: string | null;
  created_at: string;
  updated_at: string;
}

export interface IssueType {
  id: number;
  code: string;
  name: string;
  severity: 'LOW' | 'MEDIUM' | 'HIGH' | 'CRITICAL';
  is_blocking: boolean;
  is_active: boolean;
}

// API Response Types
export interface ApiResponse<T> {
  message?: string;
  data: T;
}

export interface PaginatedResponse<T> {
  data: T[];
  links: {
    first: string;
    last: string;
    prev: string | null;
    next: string | null;
  };
  meta: {
    current_page: number;
    from: number;
    last_page: number;
    path: string;
    per_page: number;
    to: number;
    total: number;
  };
}

export interface ApiError {
  message: string;
  errors?: Record<string, string[]>;
}
