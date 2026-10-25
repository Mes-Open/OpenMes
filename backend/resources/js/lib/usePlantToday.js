import { useEffect, useState } from 'react';
import { todayInTimezone } from './i18n';

/**
 * Today's date (`YYYY-MM-DD`) in the plant timezone, kept current: re-checked
 * every minute, so anything derived from it (the overdue count) flips at the
 * plant's midnight even when no row changes. Re-renders only when the date does.
 */
export function usePlantToday() {
    const [today, setToday] = useState(() => todayInTimezone());

    useEffect(() => {
        const id = setInterval(() => setToday(todayInTimezone()), 60_000);
        return () => clearInterval(id);
    }, []);

    return today;
}
