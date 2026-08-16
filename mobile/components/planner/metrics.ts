/**
 * Shared grid metrics for the weekly planner.
 *
 * These live in their own module (rather than on WeeklyBoard) so PlannerBlock
 * can read them without a circular import back into its own parent.
 *
 * The drag hit-test depends on these being the *actual* rendered sizes — if you
 * change a cell width here, the drop targets follow automatically; if you
 * hard-code a size in a component instead, drags will land in the wrong cell.
 */

/** Frozen line column on the left. */
export const LINE_COL_W = 158;
/** One day×shift cell. */
export const CELL_W = 92;
/** One stacking lane within a line row. */
export const LANE_H = 34;
/** Day header / shift sub-header height. */
export const HEADER_H = 44;

/** Where a dragged block landed. */
export interface DropTarget {
  lineId: number;
  date: string;
  shift: number;
}

/**
 * The dashed ghost shown mid-drag. `extend` is a continuation being appended on
 * another line (diagonal edge-stretch); `span` is an ordinary same-line stretch.
 */
export interface PlannerPreview {
  kind: 'extend' | 'span';
  lineId: number;
  startCol: number;
  endCol: number;
}

/** Rows are sized to their busiest lane count so stacked spans never collide. */
export function rowHeight(lanes: number): number {
  return Math.max(1, lanes) * LANE_H + 8;
}
