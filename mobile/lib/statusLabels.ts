// Centralized display labels for status enums (work orders, batches, batch steps).
// API enum values are uppercase underscore; this maps them to backend i18n keys
// (the keys are the English phrases per Laravel __() convention).

import i18n from '@/lib/i18n';

const KEYS: Record<string, string> = {
  PENDING: 'Not Started',
  ACCEPTED: 'Accepted',
  IN_PROGRESS: 'In Progress',
  PAUSED: 'Paused',
  BLOCKED: 'Blocked',
  DONE: 'Done',
  REJECTED: 'Rejected',
  CANCELLED: 'Cancelled',
};

export function statusLabel(status: string | null | undefined): string {
  if (!status) return '';
  const upper = String(status).toUpperCase();
  const key = KEYS[upper] ?? upper.replace(/_/g, ' ');
  return i18n.t(key);
}

const TERMINAL_WO_STATUSES = new Set(['DONE', 'CANCELLED', 'REJECTED']);

export function isWorkOrderOverdue(wo: {
  due_date?: string | null;
  status?: string | null;
}): boolean {
  if (!wo.due_date) return false;
  if (wo.status && TERMINAL_WO_STATUSES.has(String(wo.status).toUpperCase())) return false;
  const due = new Date(wo.due_date);
  if (Number.isNaN(due.getTime())) return false;
  return due.getTime() < Date.now();
}
