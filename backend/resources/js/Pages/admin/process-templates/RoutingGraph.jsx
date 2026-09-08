import { useState, useEffect, useMemo, useCallback, useRef } from 'react';
import { ReactFlow, Background, Controls, MarkerType, Handle, Position, Panel, useNodesState, useEdgesState } from '@xyflow/react';
import '@xyflow/react/dist/style.css';
import dagre from '@dagrejs/dagre';
import { Dropdown, useToast } from '@openmes/ui';
import { __ } from '../../../lib/i18n';
import { csrf } from '../../../lib/machineState';
import Tooltip from '../../../components/Tooltip';
import LinkEdge from '../../../components/flow/LinkEdge';

/**
 * Routing graph editor for a process template. Steps are nodes; links are the
 * explicit routing (from → to = "to may start once from is done"). With no
 * links the implicit step-number chain is shown dotted. Drag from a step's
 * right handle to another's left handle to add a link, select a link and press
 * Delete to remove it. Cycles are refused server-side.
 */

const NODE_W = 220;
const NODE_H = 64;

function layout(steps, pairs) {
    const g = new dagre.graphlib.Graph();
    g.setDefaultEdgeLabel(() => ({}));
    g.setGraph({ rankdir: 'LR', nodesep: 30, ranksep: 70, marginx: 16, marginy: 16 });
    steps.forEach((s) => g.setNode(String(s.id), { width: NODE_W, height: NODE_H }));
    pairs.forEach(([a, b]) => g.setEdge(String(a), String(b)));
    dagre.layout(g);
    return Object.fromEntries(steps.map((s) => {
        const p = g.node(String(s.id));
        return [s.id, { x: p.x - NODE_W / 2, y: p.y - NODE_H / 2 }];
    }));
}

function StepNode({ data, selected, isConnectable }) {
    const { step } = data;
    return (
        <div
            style={{ width: NODE_W }}
            className={`bg-om-card rounded-om border border-om-line2 shadow-sm px-3 py-2 ${selected ? 'ring-2 ring-om-accent' : ''}`}
        >
            <Handle type="target" position={Position.Left} isConnectable={isConnectable} className="!bg-om-faint !w-2.5 !h-2.5 !border-2 !border-om-card hover:!bg-om-accent" />
            <Handle type="source" position={Position.Right} isConnectable={isConnectable} className="!bg-om-faint !w-2.5 !h-2.5 !border-2 !border-om-card hover:!bg-om-accent" />
            <div className="flex items-center gap-2">
                <span className={`shrink-0 w-6 h-6 rounded-full text-xs font-bold flex items-center justify-center ${selected ? 'bg-om-accent text-white' : 'bg-om-chip text-om-accent'}`}>{step.step_number}</span>
                <div className="min-w-0">
                    <p className="text-sm font-semibold text-om-ink truncate">{step.name}</p>
                    <p className="text-[11px] text-om-muted truncate">
                        {step.workstation?.name ?? step.workstation_type?.name ?? __('Any workstation')}
                        {step.variant_group ? ` · ${__('Variant')}: ${step.variant_group}` : ''}
                        {step.is_optional ? ` · ${__('Optional')}` : ''}
                    </p>
                </div>
            </div>
        </div>
    );
}

const NODE_TYPES = { step: StepNode };
const EDGE_TYPES = { link: LinkEdge };

async function api(url, method, body) {
    const res = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        body: body === undefined ? undefined : JSON.stringify(body),
    });
    let json = null;
    try { json = await res.json(); } catch (_) { /* no body */ }
    return { ok: res.ok, json };
}

function firstError(json) {
    if (!json) return null;
    if (json.errors) { const f = Object.values(json.errors)[0]; return Array.isArray(f) ? f[0] : String(f); }
    return json.message ?? null;
}

export default function RoutingGraph({ steps, links: initialLinks, baseUrl, compact = false, height = 340, selectedId = null, onSelectStep }) {
    // The backend advertises the link editor by sending a links array; while it
    // doesn't (the DAG-routing backend hasn't shipped), the graph is read-only:
    // the implicit chain renders, but nothing can be drawn or removed.
    const editable = Array.isArray(initialLinks);
    const toast = useToast();
    const [links, setLinks] = useState(initialLinks ?? []);
    useEffect(() => { setLinks(initialLinks ?? []); }, [initialLinks]);

    const explicit = links.length > 0;
    const [linkKind, setLinkKind] = useState('sequence');

    // Implicit chain when there are no links, so the editor never starts blank.
    const pairs = useMemo(() => (explicit
        ? links.filter((l) => l.kind !== 'rework').map((l) => [l.from, l.to])
        : steps.slice(1).map((s, i) => [steps[i].id, s.id])), [explicit, links, steps]);

    const structureKey = `${steps.map((s) => s.id).join(',')}|${pairs.map((p) => p.join('-')).join(',')}`;
    const lastKeyRef = useRef(structureKey);
    const positions = useMemo(() => layout(steps, pairs), [structureKey]); // eslint-disable-line react-hooks/exhaustive-deps

    const [nodes, setNodes, onNodesChange] = useNodesState([]);
    const [edges, setEdges, onEdgesChange] = useEdgesState([]);

    useEffect(() => {
        // A structural change (reorder, link added/removed) re-flows the whole
        // graph; a data refresh keeps whatever the user dragged nodes to.
        const structureChanged = lastKeyRef.current !== structureKey;
        lastKeyRef.current = structureKey;
        setNodes((prev) => {
            const prevById = Object.fromEntries(prev.map((n) => [n.id, n]));
            return steps.map((s) => ({
                ...(prevById[String(s.id)] ?? {}),
                id: String(s.id),
                type: 'step',
                // Steps are created and removed in the rail, never by keyboard
                // on the canvas — without this, Delete removed the node
                // client-side only (and in edit mode cascaded real link DELETEs).
                deletable: false,
                position: (structureChanged ? null : prevById[String(s.id)]?.position) ?? positions[s.id] ?? { x: 0, y: 0 },
                selected: selectedId != null && s.id === selectedId,
                data: { step: s },
            }));
        });
        setEdges((prev) => {
            const selected = new Set(prev.filter((e) => e.selected).map((e) => e.id));
            return explicit
                ? links.map((l) => ({
                    id: `l${l.id}`,
                    source: String(l.from),
                    target: String(l.to),
                    type: 'link',
                    animated: true,
                    deletable: true,
                    selected: selected.has(`l${l.id}`),
                    markerEnd: { type: MarkerType.ArrowClosed, color: l.kind === 'rework' ? 'var(--om-blocked)' : 'var(--om-accent)' },
                    data: l.kind === 'rework'
                        ? { linkId: l.id, tone: 'danger', dashed: true, label: __('rework'), onRemove: () => removeLink(l.id) }
                        : { linkId: l.id, tone: 'accent', label: __('then'), onRemove: () => removeLink(l.id) },
                }))
                : pairs.map(([a, b]) => ({
                    id: `i${a}-${b}`,
                    source: String(a),
                    target: String(b),
                    type: 'link',
                    animated: true,
                    deletable: false,
                    selectable: false,
                    markerEnd: { type: MarkerType.ArrowClosed, color: 'var(--om-faint)' },
                    data: { tone: 'muted', dashed: true },
                }));
        });
    }, [steps, links, pairs, explicit, positions, structureKey, selectedId, setNodes, setEdges]); // eslint-disable-line react-hooks/exhaustive-deps

    const onConnect = useCallback(async ({ source, target }) => {
        if (!source || !target) return;
        const { ok, json } = await api(`${baseUrl}/step-links`, 'POST', { from_template_step_id: Number(source), to_template_step_id: Number(target), kind: linkKind });
        if (ok && Array.isArray(json?.data)) setLinks(json.data);
        else toast({ severity: 'error', title: __('Could not add the link.'), body: firstError(json) });
    }, [baseUrl, linkKind]); // eslint-disable-line react-hooks/exhaustive-deps

    const removeLink = async (linkId) => {
        const { ok, json } = await api(`${baseUrl}/step-links/${linkId}`, 'DELETE');
        if (ok && Array.isArray(json?.data)) setLinks(json.data);
        else toast({ severity: 'error', title: __('Could not remove the link.'), body: firstError(json) });
    };

    const onEdgesDelete = useCallback(async (deleted) => {
        for (const e of deleted) {
            if (e.data?.linkId) await removeLink(e.data.linkId);
        }
    }, [baseUrl]); // eslint-disable-line react-hooks/exhaustive-deps

    if (steps.length === 0) return null;

    const kindPicker = (
        <Tooltip
            label={__('The kind of the NEXT link you draw between two steps: sequence = the target waits for the source; rework (send back) = the source may be sent back to that earlier step for another pass (dashed red).')}
            placement="bottom"
        >
        <Dropdown
            size="sm"
            className="min-w-[200px]"
            value={linkKind}
            onChange={(v) => v && setLinkKind(v)}
            options={[
                { value: 'sequence', label: __('New link: sequence') },
                { value: 'rework', label: __('New link: rework (send back)') },
            ]}
            aria-label={__('New link kind')}
        />
        </Tooltip>
    );

    const canvas = (
        <ReactFlow
            nodes={nodes}
            edges={edges}
            nodeTypes={NODE_TYPES}
            edgeTypes={EDGE_TYPES}
            onNodesChange={onNodesChange}
            onEdgesChange={onEdgesChange}
            onConnect={editable ? onConnect : undefined}
            onEdgesDelete={editable ? onEdgesDelete : undefined}
            onNodeClick={onSelectStep ? (_, node) => onSelectStep(Number(node.id)) : undefined}
            nodesDraggable
            nodesConnectable={editable}
            edgesReconnectable={false}
            deleteKeyCode={editable ? ['Backspace', 'Delete'] : null}
            elementsSelectable
            fitView
            fitViewOptions={{ padding: 0.2, maxZoom: 1.1 }}
            proOptions={{ hideAttribution: true }}
            minZoom={0.2}
        >
            <Background gap={24} color="var(--om-line2)" />
            <Controls showInteractive={false} />
            {compact && editable && <Panel position="top-right">{kindPicker}</Panel>}
        </ReactFlow>
    );

    if (compact) {
        // Strip mode for the master–detail page: no heading, the kind picker
        // floats over the canvas, the frame comes from the parent.
        return <div style={{ height }} className="bg-om-panel">{canvas}</div>;
    }

    return (
        <div className="mb-8">
            <div className="flex items-center gap-3 mb-2 flex-wrap">
                <h2 className="text-xl font-bold text-om-ink">{__('Routing graph')}</h2>
                <span className="text-sm text-om-muted flex-1 min-w-[240px]">
                    {explicit
                        ? __('Explicit routing — a step waits for every incoming link; several outgoing links run in parallel. Steps without any link start immediately.')
                        : editable
                            ? __('Implicit sequence — draw a link between two steps to switch to explicit routing.')
                            : __('Implicit sequence — steps run in order.')}
                </span>
                {editable && kindPicker}
            </div>
            <div style={{ height }} className="rounded-om border border-om-line2 bg-om-panel overflow-hidden">
                {canvas}
            </div>
        </div>
    );
}
