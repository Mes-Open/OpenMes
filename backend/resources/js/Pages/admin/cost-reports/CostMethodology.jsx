import { __ } from '../../../lib/i18n';

/**
 * Collapsible "how is this calculated" panel shown on the cost report list and
 * detail views, so it is clear how each number is derived and where the data
 * comes from. Native <details> disclosure (no JS state needed).
 */
export default function CostMethodology() {
    return (
        <details className="bg-white dark:bg-slate-800 rounded-lg shadow-sm">
            <summary className="cursor-pointer select-none px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-200">
                {__('How is this calculated?')}
            </summary>
            <div className="px-4 pb-4 pt-0 text-sm text-gray-600 dark:text-gray-300 space-y-2 border-t border-gray-100 dark:border-slate-700">
                <p className="pt-3">{__('Total cost = materials + labor + additional costs, per finished work order. Cost per unit = total / produced quantity.')}</p>
                <ul className="list-disc pl-5 space-y-2">
                    <li>
                        <span className="font-medium text-gray-800 dark:text-gray-100">{__('Materials')}: </span>
                        {__('actual consumed quantity x unit price captured at consumption time. If no consumption was recorded, the BOM recipe x produced quantity is used instead (shown as BOM estimate). Source: material allocations and material prices.')}
                    </li>
                    <li>
                        <span className="font-medium text-gray-800 dark:text-gray-100">{__('Labor')}: </span>
                        {__('from work-type time logged against the order, priced per worker by pay type: hourly (rate x hours), weekly (weekly salary / standard weekly hours x hours) or piece rate (rate x pieces, with the order output split across piece-rate workers by their logged hours). Source: employee activities and per-worker pay; defaults from Settings apply when a worker has none.')}
                    </li>
                    <li>
                        <span className="font-medium text-gray-800 dark:text-gray-100">{__('Additional costs')}: </span>
                        {__('amounts booked manually against the work order.')}
                    </li>
                    <li>
                        <span className="font-medium text-gray-800 dark:text-gray-100">{__('Currency')}: </span>
                        {__('the system currency from Settings. Amounts in other currencies are summed without conversion and flagged.')}
                    </li>
                </ul>
            </div>
        </details>
    );
}
