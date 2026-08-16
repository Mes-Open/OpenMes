import { __, formatNumber } from '../../lib/i18n';
import { KIND_COLOR, TONE_COLOR, scoreColor, toneTextColor } from './tokens';

/**
 * The shift read backwards: what the OEE factors came to, which causes cost
 * the most time, and where the scheduled hours actually went.
 *
 * Everything here is the same numbers the live view shows, regrouped — no
 * second fetch, so switching tabs is instant and can never disagree with the
 * timeline.
 */
export default function AnalysisView({ analysis }) {
    return (
        <div className="px-[22px] py-5">
            <div className="mb-5 grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
                {analysis.cards.map((card) => (
                    <FactorCard key={card.key} card={card} />
                ))}
            </div>

            <div className="flex flex-col gap-[18px] lg:flex-row lg:items-start">
                <section className="min-w-0 flex-[1.35] rounded-[14px] border border-om-line2 bg-om-bg px-5 py-[18px]">
                    <div className="mb-4 flex items-baseline justify-between">
                        <h3 className="text-[15px] font-semibold text-om-ink">{__('Downtime by cause')}</h3>
                        <span className="font-mono text-[9.5px] uppercase text-om-faint">
                            {analysis.pareto.totalMinutes} {__('min lost')} · {analysis.pareto.causeCount} {__('causes')}
                        </span>
                    </div>

                    {analysis.pareto.rows.length === 0 ? (
                        <p className="text-[12.5px] text-om-muted">{__('No stops recorded this shift.')}</p>
                    ) : (
                        analysis.pareto.rows.map((row) => (
                            <div key={row.label} className="mb-[13px]">
                                <div className="mb-[5px] flex items-baseline justify-between gap-3">
                                    <span className="text-[12.5px] text-om-ink">{row.label}</span>
                                    <span className="font-mono text-[11px] text-om-muted">
                                        {row.minutes} {__('min')} · {row.percent}%
                                    </span>
                                </div>
                                <div className="h-2 overflow-hidden rounded-full bg-om-track">
                                    <div
                                        className="h-full rounded-full"
                                        style={{ width: `${row.bar}%`, background: KIND_COLOR[row.kind] ?? 'var(--om-faintest)' }}
                                    />
                                </div>
                            </div>
                        ))
                    )}

                    {analysis.pareto.rows.length > 0 && (
                        <p className="mt-3.5 border-t border-om-line2 pt-[13px] text-xs leading-relaxed text-om-muted">
                            {__('Top cause ":cause" accounts for :percent% of lost time.', {
                                cause: analysis.pareto.rows[0].label,
                                percent: analysis.pareto.rows[0].percent,
                            })}
                        </p>
                    )}
                </section>

                <div className="flex min-w-0 flex-1 flex-col gap-3.5">
                    <section className="rounded-[14px] border border-om-line2 bg-om-bg px-5 py-[18px]">
                        <h3 className="mb-4 text-[15px] font-semibold text-om-ink">{__('Time losses')}</h3>
                        {analysis.waterfall.map((row) => (
                            <div key={row.key} className="mb-3">
                                <div className="mb-[5px] flex items-baseline justify-between">
                                    <span
                                        className="text-[12.5px]"
                                        style={{ color: row.tone === 'running' ? 'var(--om-ink)' : 'var(--om-muted)' }}
                                    >
                                        {row.label}
                                    </span>
                                    <span
                                        className="font-mono text-[11.5px] font-semibold"
                                        style={{ color: toneTextColor(row.tone) }}
                                    >
                                        {row.minutes} {__('min')}
                                    </span>
                                </div>
                                <div className="h-[7px] overflow-hidden rounded-full bg-om-track">
                                    <div
                                        className="h-full rounded-full"
                                        style={{ width: `${Math.abs(row.bar)}%`, background: TONE_COLOR[row.tone] }}
                                    />
                                </div>
                            </div>
                        ))}
                    </section>

                    <section className="rounded-[14px] border border-om-line2 bg-om-bg px-5 py-[18px]">
                        <h3 className="mb-3.5 text-[15px] font-semibold text-om-ink">{__('Quality')}</h3>
                        <div className="flex overflow-hidden rounded-[11px] border border-om-line2">
                            {analysis.quality.map((cell, i) => (
                                <div
                                    key={cell.key}
                                    className={`flex-1 px-3.5 py-[13px] ${i < analysis.quality.length - 1 ? 'border-r border-om-line2' : ''}`}
                                >
                                    <div className="mb-1.5 font-mono text-[8.5px] uppercase tracking-[0.1em] text-om-faint">
                                        {cell.label}
                                    </div>
                                    <div className="font-mono text-xl font-semibold" style={{ color: toneTextColor(cell.tone) }}>
                                        {formatNumber(cell.value)}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>
                </div>
            </div>
        </div>
    );
}

function FactorCard({ card }) {
    const color = scoreColor(card.value);

    return (
        <div className="rounded-[14px] border border-om-line2 bg-om-bg px-5 py-[18px]">
            <div className="mb-3 flex items-baseline justify-between gap-2">
                <span className="font-mono text-[9px] uppercase tracking-[0.12em] text-om-faint">{card.label}</span>
                {card.delta && <span className="text-[11px] text-om-muted">{card.delta}</span>}
            </div>
            <div className="mb-3 font-mono text-[32px] font-semibold leading-none tracking-[-0.02em]" style={{ color }}>
                {card.value === null ? '—' : `${card.value}%`}
            </div>
            <div className="mb-2 h-[5px] overflow-hidden rounded-full bg-om-track">
                <div className="h-full rounded-full" style={{ width: `${card.value ?? 0}%`, background: color }} />
            </div>
            <p className="text-[11.5px] leading-snug text-om-muted">{card.note}</p>
        </div>
    );
}
