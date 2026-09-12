// Renders what an installed module contributed to a named point on a page.
//
// The server side is App\Extension\HookRegistry: a controller resolves the hook
// points its page offers and hands them over as the `hooks` prop. On an
// installation with no modules that prop is `{}` and every <Hook> renders null,
// which is why a core page can offer extension points at no cost.
//
// A contribution either names a component the module ships, or carries plain
// fields that render as a standard card — so a module can contribute without
// shipping any JSX at all.

// Same shape as the page glob in app.jsx: matches nothing, and yields {}, when
// no module is installed.
const moduleComponents = import.meta.glob('../../../modules/*/resources/js/Components/**/*.jsx', { eager: true });

/**
 * Resolve 'ext:Example/LinePicker' to the component a module ships at
 * `modules/<any>/resources/js/Components/Example/LinePicker.jsx`.
 */
export function resolveHookComponent(name) {
    if (typeof name !== 'string' || ! name.startsWith('ext:')) {
        return null;
    }

    const suffix = `/resources/js/Components/${name.slice(4)}.jsx`;
    const key = Object.keys(moduleComponents).find((path) => path.endsWith(suffix));

    return key ? (moduleComponents[key].default ?? null) : null;
}

/** A contribution that names no component: the same fields a dashboard widget carries. */
function GenericCard({ title, metric, body, href, external }) {
    const content = (
        <>
            {title && <div className="text-[13px] font-medium text-om-ink">{title}</div>}
            {metric && <div className="text-[20px] font-semibold text-om-ink mt-0.5">{metric}</div>}
            {body && <div className="text-om-muted text-[12.5px] mt-1">{body}</div>}
        </>
    );

    if (! href) {
        return <div className="rounded-om border border-om-line p-3">{content}</div>;
    }

    return (
        <a
            href={href}
            {...(external ? { rel: 'noreferrer' } : {})}
            className="block rounded-om border border-om-line p-3 hover:bg-om-chip"
        >
            {content}
        </a>
    );
}

/**
 * Everything contributed to one hook point.
 *
 * Any prop other than `name`/`hooks` is passed to each contributed component as
 * page context, so a module's component can read the record it is rendered for.
 */
export function Hook({ name, hooks, ...context }) {
    const contributions = hooks?.[name];

    if (! contributions?.length) {
        return null;
    }

    return contributions.map((contribution, i) => {
        const { component, props = {}, ...fields } = contribution;
        const Component = component ? resolveHookComponent(component) : GenericCard;

        // A module that named a component this build does not contain. Say so
        // quietly rather than crashing the page it was contributing to.
        if (! Component) {
            console.warn(`Hook component not found: ${component} (${name})`);

            return null;
        }

        return <Component key={i} {...fields} {...props} {...context} />;
    });
}

/** True when anything was contributed — for deciding whether to draw a wrapper. */
export function hasHook(hooks, name) {
    return Boolean(hooks?.[name]?.length);
}
