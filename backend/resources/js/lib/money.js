/**
 * The one place a money amount is turned into text in the browser.
 *
 * Every pay rate and cost amount is in the installation currency (Settings →
 * System, shared as the Inertia `currency` prop and set at boot in app.jsx).
 * Modules call formatMoney() too, so amounts read the same everywhere. The
 * server twin is App\Support\Money.
 */
import { formatNumber } from './i18n';

let activeCurrency = 'PLN';

export function setCurrency(code) {
    if (typeof code === 'string' && code !== '') activeCurrency = code;
}

export function currency() {
    return activeCurrency;
}

/**
 * `formatMoney(1234.5)` → "1,234.50 PLN" (separators follow the UI locale).
 * Null/empty/non-numeric → "—". Pass a code for a record in another currency.
 */
export function formatMoney(value, code, opts = {}) {
    if (value == null || value === '' || isNaN(value)) return '—';
    const number = formatNumber(value, { minimumFractionDigits: 2, maximumFractionDigits: 2, ...opts });
    return `${number} ${code || activeCurrency}`;
}
