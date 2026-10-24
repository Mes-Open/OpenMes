import { LINE_STATES, lineStateLabel, lineStateTone } from './lineStates';

/** Swatch row naming every line state, "No data" included. */
export default function LineStateLegend() {
    return (
        <ul className="m-0 flex list-none flex-wrap gap-x-3.5 gap-y-1 p-0 text-[13px] text-om-muted" data-testid="line-state-legend">
            {LINE_STATES.map((state) => (
                <li key={state} className="inline-flex items-center gap-1.5">
                    <span
                        aria-hidden="true"
                        className="h-2.5 w-2.5 rounded-[2px]"
                        style={{ background: lineStateTone(state).swatch }}
                    />
                    {lineStateLabel(state)}
                </li>
            ))}
        </ul>
    );
}
