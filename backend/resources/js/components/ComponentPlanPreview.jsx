import { useEffect, useId, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { Button, Checkbox } from '@openmes/ui';
import { __ } from '../lib/i18n';
import { apiCall } from '../lib/http';
import ComponentPlanTree, { componentPaths } from './ComponentPlanTree';

export default function ComponentPlanPreview({ data, setData, action }) {
    const { componentWarehouses = [], timezone = 'UTC' } = usePage().props;
    const [visible, setVisible] = useState(true);
    const [revision, setRevision] = useState(0);
    const [result, setResult] = useState(null);
    const panelId = useId();
    const enabled = ['1', true, 1].includes(data.generate_components);
    const key = JSON.stringify([data.product_type_id, data.bom_template_ids, data.planned_qty, data.use_component_stock, data.component_warehouse_ids, data.planned_start_at, revision]);
    const ready = Boolean(data.product_type_id) && Number(data.planned_qty) > 0;

    useEffect(() => {
        if (!enabled || !ready) return;
        setData?.('component_preview_token', null);
        let cancelled = false;
        // Wait for typing/product-dependent BOM selection to settle. Ignore stale responses.
        const timer = setTimeout(async () => {
            const [product_type_id, bom_template_ids, planned_qty, use_component_stock, component_warehouse_ids, planned_start_at] = JSON.parse(key);
            try {
                const response = await apiCall(`${action}/component-preview`, 'POST', {
                    product_type_id, bom_template_ids, planned_qty, use_component_stock, component_warehouse_ids, planned_start_at,
                });
                const body = await response.json();
                if (!response.ok) throw new Error(Object.values(body.errors ?? {}).flat().join(' ') || body.message);
                if (!cancelled) { setResult({ key, plan: body.data }); setData?.('component_preview_token', body.data.preview_token); }
            } catch (e) {
                if (!cancelled) setResult({ key, error: e.message || __('Could not preview components.') });
            }
        }, 300);
        return () => { cancelled = true; clearTimeout(timer); };
    }, [key, enabled, ready, action]);

    useEffect(() => { setData?.('excluded_component_paths', []); }, [data.product_type_id, JSON.stringify(data.bom_template_ids)]);

    if (!enabled) return null;
    const current = result?.key === key ? result : null;
    const flatten = (nodes) => nodes.flatMap((node) => [node, ...flatten(node.children)]);
    const rows = current?.plan ? flatten(current.plan.components) : [];
    return <div className="space-y-3">
        <Checkbox checked={Boolean(data.use_component_stock)} onChange={(checked) => setData('use_component_stock', checked)} label={__('Use available component stock')} />
        {data.use_component_stock && <fieldset className="space-y-2 rounded-om border border-om-line p-3">
            <legend className="px-1 text-sm">{__('Component warehouses')}</legend>
            {componentWarehouses.map((warehouse) => <div key={warehouse.id}><Checkbox
                checked={(data.component_warehouse_ids ?? []).includes(warehouse.id)} label={`${warehouse.code} · ${warehouse.name}`}
                onChange={(checked) => setData('component_warehouse_ids', checked ? [...(data.component_warehouse_ids ?? []), warehouse.id] : data.component_warehouse_ids.filter((id) => id !== warehouse.id))} /></div>)}
        </fieldset>}
        <p className="text-xs text-om-muted">{__('Planning timezone')}: {timezone}. {data.planned_start_at ? `${__('Needed at')}: ${data.planned_start_at.replace('T', ' ')}` : __('Production start is not scheduled.')}</p>
        <div className="flex flex-wrap gap-2">
            <Button type="button" variant="secondary" aria-expanded={visible} aria-controls={panelId}
                onClick={() => setVisible((previous) => !previous)}>
                {visible ? __('Hide component preview') : __('Show component preview')}
            </Button>
            {ready && <Button type="button" variant="secondary" onClick={() => setRevision((value) => value + 1)}>{__('Refresh availability')}</Button>}
        </div>
        <div id={panelId} hidden={!visible}>
            {!ready ? <p className="text-sm text-om-muted">{__('Select a product and enter the planned quantity to preview components.')}</p>
                : !current ? <p role="status" className="text-sm text-om-muted">{__('Loading...')}</p>
                : current.error ? <p role="alert" className="text-sm text-om-blocked">{current.error}</p>
                : <div className="rounded-om border border-om-line p-3 text-sm space-y-2">
                    <p className="font-medium">{__('Component jobs: :count', { count: rows.filter((node) => node.snapshot && node.planned_qty > 0).length })}</p>
                    <p className="text-xs text-om-muted">{__('Production can only be unchecked when stock covers the whole requirement. Purchased materials follow the existing material flow.')}</p>
                    <ComponentPlanTree key={key} nodes={current.plan.components} selectedPaths={componentPaths(current.plan.components).filter((path) => !(data.excluded_component_paths ?? []).includes(path))} onSelectionChange={(paths) => setData('excluded_component_paths', componentPaths(current.plan.components).filter((path) => !paths.includes(path)))} />
                </div>}
        </div>
    </div>;
}
