import ResourceForm from '../../../components/ResourceForm';
import { woFields } from './fields';

export const WO_FORM_INITIAL = {
    order_no: '', customer_order_no: '', customer_id: '', line_id: '', product_type_id: '',
    planned_qty: '', unit_price: '', priority: 0, due_date: '', description: '', custom_fields: {},
};

// THE work-order create form — the full Create page and the planner's
// "+ New order" modal both render this, so a field added here (or in
// woFields) shows up in both places.
//
// `stay` posts a `stay` flag the controller answers with back() instead of
// redirecting to the work-orders index — the caller keeps its page (planner).
export default function WorkOrderForm({ lines = [], productTypes = [], customers = [], customFields = [], cancelHref, onCancel, onSuccess, stay = false, initial = {} }) {
    return (
        <ResourceForm
            action="/admin/work-orders"
            method="post"
            fields={woFields(lines, productTypes, { customers })}
            customFields={customFields}
            initial={{ ...WO_FORM_INITIAL, ...(stay ? { stay: 1 } : {}), ...initial }}
            submitLabel="Create"
            cancelHref={cancelHref}
            onCancel={onCancel}
            onSuccess={onSuccess}
        />
    );
}
