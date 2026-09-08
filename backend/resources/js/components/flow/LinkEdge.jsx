import { memo } from 'react';
import { BaseEdge, EdgeLabelRenderer, getSmoothStepPath, useReactFlow } from '@xyflow/react';
import { Dropdown, Icon } from '@openmes/ui';
import { __ } from '../../lib/i18n';

/**
 * Editable edge shared by the Line Flow canvas and the routing-graph editor —
 * React Flow's "edge with button" pattern: the path is a plain BaseEdge (with
 * the default 20px hit area, so it is clickable), the label chip lives in
 * EdgeLabelRenderer as real DOM. Clicking the edge or its chip selects it; a
 * selected edge grows a toolbar with an optional kind picker and a remove
 * button, so nobody has to know about the Delete key.
 *
 * data: { label, tone: 'accent' | 'muted' | 'danger', dashed, kindOptions?, kind?,
 *         onChangeKind?(value), onRemove?() }
 */
function LinkEdge({ id, sourceX, sourceY, targetX, targetY, sourcePosition, targetPosition, selected, animated, markerEnd, data = {} }) {
    const { setEdges } = useReactFlow();
    const [path, labelX, labelY] = getSmoothStepPath({ sourceX, sourceY, targetX, targetY, sourcePosition, targetPosition });
    const accent = data.tone !== 'muted';
    const stroke = data.tone === 'danger' ? 'var(--om-blocked)' : accent ? 'var(--om-accent)' : 'var(--om-faint)';
    const editable = Boolean(data.onRemove);

    const select = (e) => {
        e.stopPropagation();
        setEdges((edges) => edges.map((edge) => ({ ...edge, selected: edge.id === id })));
    };

    return (
        <>
            <BaseEdge
                id={id}
                path={path}
                markerEnd={markerEnd}
                // React Flow's .animated class animates stroke-dashoffset; a solid
                // stroke shows no motion, so animated edges always get a dash.
                style={{ stroke, strokeWidth: selected ? 3 : accent ? 2.5 : 1.5, strokeDasharray: data.dashed ? '6 4' : animated ? '8 4' : undefined }}
            />
            <EdgeLabelRenderer>
                <div
                    style={{ position: 'absolute', transform: `translate(-50%, -50%) translate(${labelX}px, ${labelY}px)`, pointerEvents: 'all' }}
                    className={`nodrag nopan ${editable ? 'cursor-pointer' : ''}`}
                    onClick={editable ? select : undefined}
                >
                    {selected && editable ? (
                        <div className="flex items-center gap-1 bg-om-card border border-om-accent rounded-om shadow-md px-1.5 py-1">
                            {data.kindOptions ? (
                                <Dropdown
                                    size="sm"
                                    value={data.kind}
                                    onChange={(v) => v && data.onChangeKind?.(v)}
                                    options={data.kindOptions}
                                    aria-label={__('Link kind')}
                                />
                            ) : (
                                <span className="text-[11px] font-semibold text-om-ink px-1">{data.label}</span>
                            )}
                            <button
                                type="button"
                                onClick={(e) => { e.stopPropagation(); data.onRemove?.(); }}
                                className="flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] font-semibold text-om-blocked hover:bg-om-blocked-bg"
                                title={__('Remove link')}
                                aria-label={__('Remove link')}
                            >
                                <Icon name="trash-2" size={13} />
                                {__('Remove')}
                            </button>
                        </div>
                    ) : (
                        data.label ? (
                            <span
                                className={`inline-block rounded-full border px-2 py-0.5 text-[10px] font-semibold ${
                                    data.tone === 'danger' ? 'bg-om-card border-om-blocked/40 text-om-blocked' : accent ? 'bg-om-card border-om-accent/40 text-om-accent' : 'bg-om-card border-om-line2 text-om-muted'
                                }`}
                            >
                                {data.label}
                            </span>
                        ) : null
                    )}
                </div>
            </EdgeLabelRenderer>
        </>
    );
}

export default memo(LinkEdge);
