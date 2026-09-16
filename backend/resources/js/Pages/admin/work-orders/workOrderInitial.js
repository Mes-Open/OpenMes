export function workOrderInitial(workOrder) {
    return {
        order_no: workOrder.order_no ?? '',
        customer_order_no: workOrder.customer_order_no ?? '',
        customer_id: workOrder.customer_id != null ? String(workOrder.customer_id) : '',
        line_id: workOrder.line_id != null ? String(workOrder.line_id) : '',
        product_type_id: workOrder.product_type_id != null ? String(workOrder.product_type_id) : '',
        product_revision_id: workOrder.product_revision_id != null ? String(workOrder.product_revision_id) : '',
        bom_template_ids: workOrder.bom_template_ids ?? [],
        planned_qty: workOrder.planned_qty ?? '',
        unit_price: workOrder.unit_price ?? '',
        counting_source: workOrder.counting_source ?? 'operator',
        priority: workOrder.priority ?? 0,
        due_date: workOrder.due_date ?? '',
        planned_start_at: workOrder.planned_start_at ?? '',
        description: workOrder.description ?? '',
        status: workOrder.status ?? 'PENDING',
        custom_fields: workOrder.custom_fields ?? {},
    };
}

