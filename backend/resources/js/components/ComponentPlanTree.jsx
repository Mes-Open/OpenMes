import Tree from './Tree';
import { __, formatNumber } from '../lib/i18n';

export const componentPaths = (nodes) => nodes.flatMap((node) => [node.path, ...componentPaths(node.children ?? [])]);

export default function ComponentPlanTree({ nodes, selectedPaths, onSelectionChange }) {
    const items = (components) => components.map((node) => ({
        id: node.path,
        disabled: node.snapshot === null,
        label: `${node.specification.material_code} · ${node.specification.material_name}`,
        description: node.snapshot === null ? `${__('Material input')}: ${formatNumber(node.planned_qty)} ${node.specification.unit_of_measure}` : `${__('Required')}: ${formatNumber(node.required_qty)} · ${__('Available')}: ${formatNumber(node.available_qty ?? 0)} · ${__('From stock')}: ${formatNumber(node.stock_qty ?? 0)} · ${__('To produce')}: ${formatNumber(node.planned_qty)} ${node.specification.unit_of_measure}`,
        children: items(node.children ?? []),
    }));
    return <Tree nodes={items(nodes)} selectedPaths={selectedPaths} onSelectionChange={onSelectionChange} />;
}
