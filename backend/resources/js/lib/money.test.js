import { afterEach, describe, expect, it } from 'vitest';
import { currency, formatMoney, setCurrency } from './money';

describe('formatMoney', () => {
    afterEach(() => setCurrency('PLN'));

    it('formats with two decimals and an explicit currency', () => {
        expect(formatMoney(1234.5, 'EUR')).toBe('1,234.50 EUR');
    });

    it('defaults to the installation currency', () => {
        setCurrency('USD');
        expect(currency()).toBe('USD');
        expect(formatMoney(5)).toBe('5.00 USD');
    });

    it('accepts numeric strings from decimal casts', () => {
        expect(formatMoney('40.0000', 'PLN')).toBe('40.00 PLN');
    });

    it('allows more precision when asked', () => {
        expect(formatMoney(0.0125, 'EUR', { maximumFractionDigits: 4 })).toBe('0.0125 EUR');
    });

    it('renders a dash for missing amounts', () => {
        expect(formatMoney(null)).toBe('—');
        expect(formatMoney('')).toBe('—');
        expect(formatMoney('abc')).toBe('—');
    });

    it('ignores an empty currency code', () => {
        setCurrency('');
        expect(currency()).toBe('PLN');
    });
});
