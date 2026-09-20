import { StatusBadge } from '@openmes/ui';
import { tierLabel } from '../Pages/admin/customers/fields';

const TIER_BADGES = {
    bronze: { icon: 'medal', color: '#b45309' },
    silver: { icon: 'award', color: 'var(--om-muted)' },
    gold: { icon: 'trophy', color: '#a16207' },
    vip: { icon: 'crown', color: 'var(--om-maint)' },
};

export default function CustomerTierBadge({ tier }) {
    const badge = TIER_BADGES[tier] ?? { icon: 'tag', color: 'var(--om-muted)' };
    return <StatusBadge
        label={tierLabel(tier)}
        icon={badge.icon}
        style={{ color: badge.color, background: 'color-mix(in srgb, currentColor 10%, transparent)', borderColor: 'color-mix(in srgb, currentColor 30%, transparent)' }}
    />;
}
