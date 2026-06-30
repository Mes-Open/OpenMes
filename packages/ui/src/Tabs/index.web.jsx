/**
 * Tabs — Geist White system (design ref: OpenMES Components.dc.html §05).
 *
 * Hairline-underlined row (line2); active tab is ink with a 2px accent
 * underline overlapping the hairline, inactive tabs are muted. Controlled:
 * `value` + `onChange(next)` over `tabs` ({ value, label }[]). API is
 * identical to the native twin (index.native.tsx).
 */
export function Tabs({ tabs, value, onChange, className = '', ...props }) {
    return (
        <div role="tablist" className={`flex gap-[22px] border-b border-om-line2 ${className}`} {...props}>
            {tabs.map((tab) => {
                const active = tab.value === value;
                return (
                    <button
                        key={tab.value}
                        type="button"
                        role="tab"
                        aria-selected={active}
                        onClick={() => onChange?.(tab.value)}
                        className={`-mb-px border-b-2 px-0.5 py-[9px] text-[13.5px] font-medium transition-colors cursor-pointer ${active ? 'border-om-accent text-om-ink' : 'border-transparent text-om-muted hover:text-om-ink'}`}
                    >
                        {tab.label}
                    </button>
                );
            })}
        </div>
    );
}
