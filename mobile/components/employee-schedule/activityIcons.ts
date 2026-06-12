// Activity-type → FontAwesome icon mapping.
// FA names because the rest of the app uses `FontAwesome` from
// @expo/vector-icons; switching to FA5/Material here would mix icon weights.

import type { FontAwesome } from '@expo/vector-icons';

import type { ActivityType } from '@/api/employeeActivities';

type FAName = React.ComponentProps<typeof FontAwesome>['name'];

export const ACTIVITY_ICONS: Record<ActivityType, FAName> = {
  work: 'cog',
  break: 'coffee',
  rest: 'clock-o',
  travel: 'arrow-right',
  setup: 'cogs',
  meeting: 'users',
  training: 'graduation-cap',
  maint: 'wrench',
  qc: 'shield',
  off: 'moon-o',
  custom: 'star',
};

export function iconForActivity(type: ActivityType): FAName {
  return ACTIVITY_ICONS[type] ?? 'circle';
}
