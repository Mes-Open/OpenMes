import { useMemo } from 'react';
import AppDataTable from '../AppDataTable';
import { __ } from '../../lib/i18n';

/**
 * The PrestaShop-style "available fields" panel for the chosen entity: what the
 * importer accepts, and the column name each one is auto-detected from.
 */
export default function AvailableFields({ entity }) {
    const fields = entity?.fields ?? [];
    const groups = entity?.identifierGroups ?? [];
    const labelOf = (key) => fields.find((f) => f.key === key)?.label ?? key;

    // The custom-field catch-all is one more row, not a footnote beside the list.
    const rows = useMemo(() => {
        const out = fields.map((f) => ({ ...f, id: f.key }));

        if (entity?.allowsCustomFields) {
            out.push({
                id: '__custom__',
                key: 'custom:field_name',
                label: null,
                custom: true,
                description: __('Any other column, kept on the record as a custom field.'),
            });
        }

        return out;
    }, [fields, entity?.allowsCustomFields]);

    const columns = useMemo(() => [
        {
            id: 'field',
            header: __('Field'),
            accessorFn: (f) => f.label ?? '',
            enableSorting: false,
            meta: { flex: true },
            cell: ({ row }) => {
                const f = row.original;

                return (
                    <div className="min-w-0">
                        {f.label && (
                            <p className="text-sm text-om-ink">
                                {f.label}
                                {f.required && <span className="text-om-blocked font-bold ml-0.5" aria-label={__('required')}>*</span>}
                            </p>
                        )}
                        {f.description && <p className="text-xs text-om-muted">{f.description}</p>}
                    </div>
                );
            },
        },
        {
            id: 'key',
            header: __('File column'),
            accessorFn: (f) => f.key,
            enableSorting: false,
            cell: ({ row }) => (
                // A column name is one token and has to read as one, never broken
                // mid-word. The label column beside it carries the descriptions
                // and gives up the width instead.
                <code className={`block text-[11px] font-mono whitespace-nowrap ${row.original.custom ? 'text-purple-700' : 'text-om-faint'}`}>
                    {row.original.key}
                </code>
            ),
        },
    ], []);

    return (
        <div>
            <h2 className="px-5 py-3 text-sm font-bold text-om-ink">{__('Available fields')}</h2>
            <AppDataTable
                data={rows}
                columns={columns}
                getRowId={(f) => f.id}
                searchable={false}
                columnToggle={false}
                // Paged so the panel stays a fixed height instead of growing with
                // the entity's field count / the run history. Ten rows rather than
                // DataTable's default six: these are scanned, not read one by one.
                pageSize={10}
                bodyMaxHeight={null}
                emptyLabel={__('No rows to preview.')}
            />
            <div className="px-5 py-3">
                <p className="text-xs text-om-faint">* {__('Required field')}</p>
                {groups.length > 0 && (
                    <p className="text-xs text-om-faint">
                        {__('One of :fields is required', { fields: groups.map((g) => g.map(labelOf).join(' + ')).join(', ') })}
                    </p>
                )}
            </div>
        </div>
    );
}
