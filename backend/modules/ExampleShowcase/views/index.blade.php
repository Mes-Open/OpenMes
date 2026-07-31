<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Example Showcase — OpenMES module</title>
    <style>
        :root { color-scheme: light dark; }
        body { font: 15px/1.6 system-ui, sans-serif; margin: 0; padding: 2rem; max-width: 820px; }
        h1 { font-size: 1.4rem; margin: 0 0 .25rem; }
        .muted { opacity: .7; }
        code { background: rgba(127,127,127,.15); padding: .1em .35em; border-radius: 4px; }
        ul { padding-left: 1.2rem; }
        a { color: #d24a1f; }
        .card { border: 1px solid rgba(127,127,127,.3); border-radius: 10px; padding: 1rem 1.25rem; margin-top: 1.25rem; }
    </style>
</head>
<body>
    <h1>Example Showcase</h1>
    <p class="muted">A reference module served entirely from <code>backend/modules/ExampleShowcase</code> — this page is its own Blade view, no core file was touched.</p>

    <div class="card">
        <strong>This page proves the menu hook works.</strong>
        <p class="muted">You reached it by clicking a link the module injected into the sidebar via <code>MenuRegistry</code>. Because the module renders through Blade (not Inertia), the sidebar link performed a full page load.</p>
    </div>

    <div class="card" id="log">
        <strong>Event hooks</strong>
        <p class="muted">While this module is enabled, every OpenMES domain event is logged. Watch them fire:</p>
        <code>tail -f storage/logs/laravel-*.log | grep ExampleShowcase</code>
        <ul class="muted">
            <li>Create / edit / complete a work order</li>
            <li>Start or complete a batch step</li>
            <li>Assign a user to a line, or let a machine change a workstation's state</li>
        </ul>
    </div>

    <p class="muted" style="margin-top:2rem">Disable it again under <strong>Admin → Modules</strong>; every hook detaches and the module goes back to zero runtime cost.</p>
</body>
</html>
