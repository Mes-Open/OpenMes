import { DatePicker } from '@openmes/ui';

import { calendarCopy } from '../lib/tableLabels';
import { __ } from '../lib/i18n';

/**
 * DatePicker with this app's copy already applied.
 *
 * `@openmes/ui`'s DatePicker is deliberately locale-free — every visible string,
 * the month names, and the BCP-47 tag behind the spoken day names all arrive as
 * props. Correct for a design-system package, wrong as the thing a page reaches
 * for: left to call sites it was never passed, so the calendar rendered English
 * month names inside a Polish UI and named its days "2026-08-22" to a screen
 * reader in every language.
 *
 * This is the one place that decision lives. Reach for `@openmes/ui`'s
 * `DatePicker` directly only where the copy genuinely shouldn't be the app's.
 *
 * `calendarProps` merges rather than replaces, so a page can still override a
 * single label without dropping the rest.
 */
export default function AppDatePicker({ calendarProps, placeholder, dialogLabel, ...props }) {
    return (
        <DatePicker
            placeholder={placeholder ?? __('Select date')}
            dialogLabel={dialogLabel ?? __('Choose date')}
            calendarProps={{ ...calendarCopy(), ...calendarProps }}
            {...props}
        />
    );
}
