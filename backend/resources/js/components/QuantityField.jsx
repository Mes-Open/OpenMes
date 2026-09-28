import { usePage } from '@inertiajs/react';
import { resolveHookComponent } from '../lib/hooks';

export const QUANTITY_FIELD_HOOK = 'display.operator.quantity_field';

/**
 * The operator's number input.
 *
 * A module may replace every one of them on a page by contributing a component
 * to `display.operator.quantity_field` (a keypad for gloved hands, say). The
 * controller resolves that point into the page's `hooks` prop, so this reads it
 * from the page rather than having it threaded through every form.
 *
 * Contract, identical for the plain input and a module's component:
 *   value     the current value (string, as an <input> holds it)
 *   onChange  called with the new value — the value, not an event
 *   variant   'big' (a modal's single hero field) or 'compact' (inline forms)
 * Every other prop (aria-label, min, max, step, placeholder, required,
 * className, …) is passed through.
 *
 * With nothing contributed — a community install — this is exactly the
 * <input type="number"> it replaced.
 */
export default function QuantityField({ value, onChange, variant = 'compact', ...rest }) {
    const { hooks } = usePage().props;
    const contribution = hooks?.[QUANTITY_FIELD_HOOK]?.[0];
    const Component = contribution?.component ? resolveHookComponent(contribution.component) : null;

    if (Component) {
        return <Component {...(contribution.props ?? {})} {...rest} value={value} onChange={onChange} variant={variant} />;
    }

    return <input type="number" {...rest} value={value} onChange={(e) => onChange(e.target.value)} />;
}
