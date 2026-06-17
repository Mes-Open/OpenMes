// Light-only v1: the dark statusPaletteDark branch is dropped — `dark` is accepted but ignored.
import { useTranslation } from 'react-i18next';

import { StatusPill as OmStatusPill, type StatusKey } from '@openmes/ui';

import { statusKindFor, type StatusKind } from '@/constants/Colors';
import { statusLabel } from '@/lib/statusLabels';

interface Props {
  status: string | undefined | null;
  label?: string;
  dark?: boolean;
}

/** Map the app's status kinds onto the design system's pill states. */
const PILL_STATUS: Record<StatusKind, StatusKey> = {
  pending: 'pending',
  inProgress: 'running',
  blocked: 'blocked',
  paused: 'downtime',
  done: 'done',
  cancelled: 'done',
  rejected: 'blocked',
};

/** Delegates to the design system's StatusPill (Geist White §06). */
export function StatusPill({ status, label }: Props) {
  // Subscribe to language changes — statusLabel() reads from i18n.t() which is
  // otherwise unreactive.
  useTranslation();
  const display = (label ?? statusLabel(status)).toString().toUpperCase();
  return <OmStatusPill status={PILL_STATUS[statusKindFor(status)]} label={display} />;
}
