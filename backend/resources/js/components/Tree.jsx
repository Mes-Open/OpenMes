import { useState } from 'react';
import { Checkbox } from '@openmes/ui';
import { __ } from '../lib/i18n';

const selectablePaths = (nodes) => nodes.flatMap((node) => [...(node.disabled ? [] : [node.id]), ...selectablePaths(node.children ?? [])]);

export const treePaths = (nodes) => nodes.flatMap((node) => [node.id, ...treePaths(node.children ?? [])]);

/** Reusable disclosure tree. Nodes contain id, label, description and children. */
export default function Tree({ nodes, selectedPaths, onSelectionChange }) {
    const [collapsed, setCollapsed] = useState(new Set());
    const selected = new Set(selectedPaths);
    const toggleExpanded = (path) => setCollapsed((previous) => {
        const next = new Set(previous);
        if (next.has(path)) next.delete(path); else next.add(path);
        return next;
    });
    const toggleSelection = (node, checked) => {
        const next = new Set(selected);
        for (const path of selectablePaths([node])) {
            if (checked) next.add(path); else next.delete(path);
        }
        onSelectionChange?.([...next]);
    };
    const branch = (items, nested = false) => <ul className={nested ? 'ml-3 border-l border-om-line pl-3' : 'space-y-1'}>
        {items.map((node) => {
            const children = node.children ?? [];
            const paths = node.disabled ? [node.id] : selectablePaths([node]);
            const count = paths.filter((path) => selected.has(path)).length;
            const expanded = !collapsed.has(node.id);
            const label = node.label;
            return <li key={node.id}>
                <div className="flex items-start gap-2 rounded-om-sm px-1 py-2 hover:bg-om-panel">
                    {children.length ? <button type="button" aria-expanded={expanded}
                        aria-label={`${expanded ? __('Collapse') : __('Expand')}: ${label}`}
                        onClick={() => toggleExpanded(node.id)}
                        className="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded border border-om-line text-om-muted focus-visible:outline-2 focus-visible:outline-om-accent">
                        <svg viewBox="0 0 16 16" className="size-3" fill="none" stroke="currentColor" strokeWidth="1.5" aria-hidden="true">
                            <path d="M3 8h10" />{!expanded && <path d="M8 3v10" />}
                        </svg>
                    </button> : <span className="w-5 shrink-0" />}
                    <div className="min-w-0 flex-1">
                        <Checkbox checked={count === paths.length} indeterminate={count > 0 && count < paths.length}
                            disabled={!onSelectionChange || node.disabled} onChange={(checked) => toggleSelection(node, checked)} label={label} />
                        {node.description && <p className="ml-[27px] mt-0.5 text-xs text-om-muted">{node.description}</p>}
                    </div>
                </div>
                {children.length > 0 && expanded && branch(children, true)}
            </li>;
        })}
    </ul>;
    return branch(nodes);
}
