import { __ } from '../../../lib/i18n';

// Loyalty tiers — values must match App\Enums\Tier on the backend.
export const TIER_VALUES = ['bronze', 'silver', 'gold', 'vip'];

// Tailwind badge classes, mirrored from Tier::badgeColor().
export const TIER_BADGE_STYLES = {
    bronze: 'bg-amber-100 text-amber-800',
    silver: 'bg-gray-200 text-gray-700',
    gold: 'bg-yellow-100 text-yellow-800',
    vip: 'bg-purple-100 text-purple-800',
};

// Built as functions (not module constants) so labels are translated at render
// time, after the active locale chunk has loaded at bootstrap.
export function tierOptions() {
    return [
        { value: 'bronze', label: __('Bronze') },
        { value: 'silver', label: __('Silver') },
        { value: 'gold', label: __('Gold') },
        { value: 'vip', label: __('VIP') },
    ];
}

export function tierLabel(tier) {
    return Object.fromEntries(tierOptions().map((t) => [t.value, t.label]))[tier] ?? tier;
}

export function customerFields() {
    return [
        { name: 'name', label: __('Name'), required: true },
        { name: 'code', label: __('Code'), help: __('Optional short business code.') },
        { name: 'tier', label: __('Tier'), type: 'select', required: true, options: tierOptions() },
        { name: 'payment_score', label: __('Payment score (0–100)'), type: 'number', help: __('Higher means the customer pays more reliably. Feeds priority scoring.') },
        { name: 'notes', label: __('Notes'), type: 'textarea' },
        { name: 'is_active', label: __('Active'), type: 'checkbox' },
    ];
}
