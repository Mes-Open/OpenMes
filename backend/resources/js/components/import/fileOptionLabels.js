import { __ } from '../../lib/i18n';

/**
 * How the two parse settings are named on screen. Shared by the upload step and
 * the mapping step's preview, which offers the same choices so a wrong guess
 * can be corrected without re-uploading.
 */
export const DELIMITER_LABELS = {
    auto: () => __('Auto-detect'),
    comma: () => __('Comma (,)'),
    semicolon: () => __('Semicolon (;)'),
    tab: () => __('Tab'),
};

export const ENCODING_LABELS = {
    'utf-8': 'UTF-8',
    'iso-8859-1': 'ISO-8859-1',
    'windows-1250': 'Windows-1250',
};

export const delimiterOptions = (values) =>
    (values ?? Object.keys(DELIMITER_LABELS)).map((d) => ({ value: d, label: DELIMITER_LABELS[d]?.() ?? d }));

export const encodingOptions = (values) =>
    (values ?? Object.keys(ENCODING_LABELS)).map((e) => ({ value: e, label: ENCODING_LABELS[e] ?? e }));
