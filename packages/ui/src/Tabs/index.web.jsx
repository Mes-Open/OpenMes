/**
 * Tabs — Geist White system (design ref: OpenMES Components.dc.html §05).
 *
 * Hairline-underlined row (line2); active tab is ink with a 2px accent
 * underline overlapping the hairline, inactive tabs are muted. Controlled:
 * `value` + `onChange(next)` over `tabs` ({ value, label }[]). API is
 * identical to the native twin (index.native.tsx).
 *
 * The strip is one tab stop with Left/Right (and Home/End) choosing between the
 * tabs, per the tabs pattern — Tab itself is for leaving the strip and reaching
 * the panel, not for walking along it.
 *
 * Pass `panels` where the caller renders real tab panels: each tab then
 * publishes `id="<value>-tab"`, and the selected one points `aria-controls` at
 * `<value>-panel`. The panel must hold up its end —
 * `id={`${value}-panel`} role="tabpanel" aria-labelledby={`${value}-tab`}`.
 * It is opt-in because `aria-controls` naming a region that does not exist is
 * worse than saying nothing, and a bare specimen strip has no panel at all.
 *
 * `label` names the strip itself.
 */
import { useRovingFocus } from '../lib/rovingFocus.web.js';

export function Tabs({ tabs, value, onChange, label, panels = false, className = '', ...props }) {
    const index = tabs.findIndex((t) => t.value === value);
    const { containerProps, itemProps } = useRovingFocus(
        tabs.length,
        index < 0 ? 0 : index,
        (i) => onChange?.(tabs[i].value),
    );

    return (
        <div
            {...containerProps}
            role="tablist"
            aria-label={label}
            className={`flex gap-[22px] border-b border-om-line2 ${className}`}
            {...props}
        >
            {tabs.map((tab, i) => {
                const active = tab.value === value;
                return (
                    <button
                        key={tab.value}
                        type="button"
                        role="tab"
                        id={panels ? `${tab.value}-tab` : undefined}
                        aria-selected={active}
                        // Only the selected tab claims a panel: callers render the
                        // active one and unmount the rest, and `aria-controls`
                        // pointing at an id that is not in the document is a
                        // broken reference rather than a helpful one.
                        aria-controls={panels && active ? `${tab.value}-panel` : undefined}
                        {...itemProps(i)}
                        onClick={() => onChange?.(tab.value)}
                        className={`-mb-px border-b-2 px-0.5 py-[9px] text-[13.5px] font-medium transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-om-accent ${active ? 'border-om-accent text-om-ink' : 'border-transparent text-om-muted hover:text-om-ink'}`}
                    >
                        {tab.label}
                    </button>
                );
            })}
        </div>
    );
}
