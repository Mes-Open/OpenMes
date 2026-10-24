import { __, formatTime } from '../../../lib/i18n';
import { OverviewCard } from './OverviewGrid';
import LineStateLegend from './LineStateLegend';
import { lineStateLabel, lineStateTone } from './lineStates';

/**
 * "Lines" section: one card per active line with its state, since when and
 * why. A line whose collector went silent shows "No data" (hatched), not its
 * last known state.
 */
export default function LinesSection({ overview }) {
    const lines = overview?.lines ?? [];

    return (
        <OverviewCard title={__('Lines')} action={<LineStateLegend />}>
            {lines.length === 0 ? (
                <p className="m-0 text-[14px] text-om-muted">{__('No active lines')}</p>
            ) : (
                <ul className="m-0 grid list-none grid-cols-[repeat(auto-fill,minmax(210px,1fr))] gap-4 p-0">
                    {lines.map((line) => <LineCard key={line.id} line={line} />)}
                </ul>
            )}
        </OverviewCard>
    );
}

function LineCard({ line }) {
    const tone = lineStateTone(line.state);
    const reason = line.needs_reason && !line.reason ? __('Reason needed') : line.reason;

    return (
        <li
            className="flex min-w-0 flex-col gap-2.5 rounded-[14px] border border-om-line bg-om-card p-5"
            data-testid="overview-line"
            data-line-id={line.id}
            data-state={line.state}
        >
            <div className="flex items-center justify-between gap-2">
                <span className="truncate text-[16px] font-semibold text-om-ink">{line.name}</span>
                <span
                    className="shrink-0 rounded-full px-2.5 py-[3px] text-[12px] font-semibold"
                    style={tone.pill}
                >
                    {lineStateLabel(line.state)}
                </span>
            </div>
            {line.since && (
                <span className="text-[13px] text-om-muted">{__('Since :time', { time: formatTime(line.since) })}</span>
            )}
            {reason && (
                <span className="text-[14px] font-medium text-om-ink">
                    {reason}
                    {line.needs_reason && line.reason && (
                        <span className="text-om-muted"> · {__('Reason needed')}</span>
                    )}
                </span>
            )}
        </li>
    );
}
