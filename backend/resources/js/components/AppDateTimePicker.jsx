import { usePage } from '@inertiajs/react';
import AppDatePicker from './AppDatePicker';
import { __ } from '../lib/i18n';

/** Wall-clock input in the plant timezone; never convert via the browser timezone. */
export default function AppDateTimePicker({ value, onChange, id, className, timeLabel, ...props }) {
    const { timezone } = usePage().props;
    const date = value?.slice(0, 10) || '';
    const time = value?.slice(11, 16) || '00:00';
    return (
        <div className={className}>
            <div className="flex items-center gap-2">
                <AppDatePicker {...props} id={id} className="flex-1 min-w-0" value={date || null}
                    onChange={(day) => onChange(day ? `${day}T${time}` : '')} />
                <input type="time" aria-label={timeLabel ?? (props['aria-label'] ? `${props['aria-label']} — ${__('Time')}` : __('Time'))} value={time} disabled={!date}
                    className="rounded-om-sm border border-om-line bg-om-bg text-om-ink px-3 py-2"
                    onChange={(e) => onChange(`${date}T${e.target.value || '00:00'}`)} />
                {date && <button type="button" className="text-om-muted text-xs" onClick={() => onChange('')}>{__('Clear')}</button>}
            </div>
            <span className="text-xs text-om-muted">{timezone}</span>
        </div>
    );
}
