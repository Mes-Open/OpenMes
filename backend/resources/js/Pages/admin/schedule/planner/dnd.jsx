// react-dnd glue for the schedule board. One drag type ('ORDER'); the dragged
// item carries the work order. Empty grid cells are the drop targets.
import { useDrag, useDrop } from 'react-dnd';

export const ORDER_TYPE = 'ORDER';

// A work-order card that can be dragged onto a slot. The native HTML5 drag image
// (a snapshot of this node) floats with the cursor, so the grid layout never
// shifts while dragging.
export function DraggableOrder({ wo, className = '', style, children }) {
    const [{ isDragging }, drag] = useDrag(() => ({
        type: ORDER_TYPE,
        item: { wo },
        collect: (monitor) => ({ isDragging: monitor.isDragging() }),
    }), [wo.id]);

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
        drop: (item) => onDropOrder(item.wo, target),
        collect: (monitor) => ({ isOver: monitor.isOver() }),
    }), [target.lineId, target.date, target.shift, onDropOrder]);
    return [isOver, drop];
}
