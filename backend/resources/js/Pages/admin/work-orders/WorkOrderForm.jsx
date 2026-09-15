import { useEffect, useRef } from 'react';
import ComponentPlanPreview from '../../../components/ComponentPlanPreview';
import ResourceForm from '../../../components/ResourceForm';
import { woFields } from './fields';

export const WO_FORM_INITIAL = {
    order_no: '', customer_order_no: '', customer_id: '', line_id: '', product_type_id: '',
    product_revision_id: '',
    bom_template_ids: [], generate_components: false, use_component_stock: false, component_warehouse_ids: [],
    excluded_component_paths: [], component_preview_token: null, planned_start_at: '', planned_end_at: '',
    planned_qty: '', unit_price: '', counting_source: 'operator', priority: 0, due_date: '', description: '', custom_fields: {},
};

const isOn = (value) => ['1', true, 1].includes(value);

// "Generate component work orders" needs a product — its BOM is what gets
// generated — so with none selected a ticked box only fails validation. It
// follows the product selection (off without one, on once one is picked) until
// the user sets it themselves; from then on their choice sticks.
function GenerateComponentsDefault({ data, setData }) {
    const hasProduct = Boolean(data.product_type_id);
    const on = isOn(data.generate_components);
    const expected = useRef(on);
    const touched = useRef(false);

    // A value this component didn't set came from the user.
    useEffect(() => {
        if (on !== expected.current) touched.current = true;
    }, [on]);

    useEffect(() => {
        if (touched.current || on === hasProduct) return undefined;
        // Inertia syncs its data ref in a parent effect; a child effect writing
        // straight away would overwrite the product selection with stale data.
        let cancelled = false;
        queueMicrotask(() => {
            if (cancelled) return;
            expected.current = hasProduct;
            setData('generate_components', hasProduct);
        });
        return () => { cancelled = true; };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [hasProduct]);

    return null;
}

// THE work-order create form — the full Create page and the planner's
// "+ New order" modal both render this, so a field added here (or in
// woFields) shows up in both places.
//
// `stay` posts a `stay` flag the controller answers with back() instead of
// redirecting to the work-orders index — the caller keeps its page (planner).
//
// `action` is what lets the supervisor tree render the same form: same fields,
// posted to /supervisor/work-orders so the request stays in its own section.
export default function WorkOrderForm({ action = '/admin/work-orders', lines = [], productTypes = [], customers = [], bomTemplates = [], productRevisions = [], customFields = [], cancelHref, onCancel, onSuccess, stay = false, initial = {} }) {
    return (
        <ResourceForm
            action={action}
            method="post"
            fields={woFields(lines, productTypes, { customers, bomTemplates, productRevisions })}
            customFields={customFields}
            initial={{
                ...WO_FORM_INITIAL,
                generate_components: Boolean(initial.product_type_id),
                ...(stay ? { stay: 1 } : {}),
                ...initial,
            }}
            renderAfterField={(name, { data, setData }) => name === 'generate_components'
                ? (
                    <>
                        <GenerateComponentsDefault data={data} setData={setData} />
                        <ComponentPlanPreview data={data} setData={setData} action={action} />
                    </>
                ) : null}
            canSubmit={(data) => !data.generate_components || !data.use_component_stock || Boolean(data.component_preview_token)}
            submitLabel="Create"
            cancelHref={cancelHref}
            onCancel={onCancel}
            onSuccess={onSuccess}
        />
    );
}
