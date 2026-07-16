// react-dnd glue for the schedule board. One drag type ('ORDER'); the dragged
// item carries the work order. Empty grid cells are the drop targets.
import { useDrag, useDrop } from 'react-dnd';

export const ORDER_TYPE = 'ORDER';

// A work-order card that can be dragged onto a slot. The native HTML5 drag image
// (a snapshot of this node) floats with the cursor, so the grid layout never
// shifts while dragging. `placement` says which schedule segment this card
// represents ('primary' or an extra placement id) — the drop rewrites only it.
export function DraggableOrder({ wo, placement = 'primary', className = '', style, children }) {
    const [{ isDragging }, drag] = useDrag(() => ({
        type: ORDER_TYPE,
        item: { wo, placement },
        collect: (monitor) => ({ isDragging: monitor.isDragging() }),
    }), [wo.id, placement]);

    return (
        <div ref={drag} className={className} style={{ ...style, opacity: isDragging ? 0.35 : 1, cursor: 'grab' }}>
            {children}
        </div>
    );
}

// Hook for a droppable slot. `target` = { lineId, date, shift }. Returns
// [isOver, dropRef]; attach dropRef to the cell element.
export function useOrderDrop(target, onDropOrder) {
    const [{ isOver }, drop] = useDrop(() => ({
        accept: ORDER_TYPE,
        drop: (item) => onDropOrder(item.wo, target, item.placement),
        collect: (monitor) => ({ isOver: monitor.isOver() }),
    }), [target.lineId, target.date, target.shift, onDropOrder]);
    return [isOver, drop];
}
