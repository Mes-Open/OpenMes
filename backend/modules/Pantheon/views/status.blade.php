{{--
    Pantheon connector status. Deliberately plain Blade: a module page must not
    depend on the core React build, so it stays a server-rendered page that works
    whatever the app's frontend is doing.

    Answers the three questions an admin actually has: is it configured, did the
    last run work, and what could it not apply.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pantheon — OpenMES</title>
    <style>
        body { font: 15px/1.6 -apple-system, "Segoe UI", Roboto, sans-serif; margin: 2rem auto; max-width: 60rem; padding: 0 1.5rem; color: #172b4d; }
        h1 { font-size: 1.6rem; } h2 { font-size: 1.15rem; margin-top: 2rem; }
        table { border-collapse: collapse; width: 100%; margin-top: .75rem; }
        th, td { border: 1px solid #dfe1e6; padding: .45rem .6rem; text-align: left; vertical-align: top; font-size: 13px; }
        th { background: #f4f5f7; }
        code { background: #f4f5f7; padding: .1rem .3rem; border-radius: 3px; font-size: 12.5px; }
        .ok { color: #216e4e; font-weight: 600; } .bad { color: #ae2e24; font-weight: 600; }
        .muted { color: #5e6c84; }
        a { color: #0c66e4; }
    </style>
</head>
<body>
    <p class="muted"><a href="/admin/modules">&lsaquo; {{ __('Modules') }}</a></p>
    <h1>Datalab Pantheon</h1>

    <h2>{{ __('Connection') }}</h2>
    <table>
        <tr>
            <th>{{ __('Status') }}</th>
            <td>
                @if (! $settings->isActive())
                    <span class="bad">{{ __('Inactive') }}</span>
                    <span class="muted">— {{ __('switch it on in Admin → Integrations') }}</span>
                @elseif (! $settings->isConfigured())
                    <span class="bad">{{ __('Not configured') }}</span>
                    <span class="muted">— {{ __('PAWS URL, username and company database are required') }}</span>
                @elseif ($problem = $settings->transportProblem())
                    {{-- Refusing to sync over cleartext http:// is deliberate; say why. --}}
                    <span class="bad">{{ __('Blocked') }}</span>
                    <span class="muted">— {{ $problem }}</span>
                @else
                    <span class="ok">{{ __('Active') }}</span>
                @endif
            </td>
        </tr>
        <tr><th>PAWS</th><td><code>{{ $settings->baseUrl() ?: '—' }}</code></td></tr>
        <tr><th>{{ __('Company database') }}</th><td><code>{{ $settings->companyDb() ?: '—' }}</code></td></tr>
        <tr>
            <th>{{ __('Product classifications') }}</th>
            <td>
                {{-- Empty means "accept every classification", which is almost never
                     what a customer wants — flag it rather than looking configured. --}}
                @forelse ($settings->productClassifications() as $code)<code>{{ $code }}</code> @empty
                    <span class="bad">{{ __('none set — every item would import as a product') }}</span>
                @endforelse
            </td>
        </tr>
        <tr>
            <th>{{ __('Material classifications') }}</th>
            <td>
                @forelse ($settings->materialClassifications() as $code)<code>{{ $code }}</code> @empty
                    <span class="muted">—</span>
                @endforelse
            </td>
        </tr>
    </table>

    <h2>{{ __('Last runs') }}</h2>
    @if ($runs->isEmpty())
        <p class="muted">
            {{ __('Nothing has run yet.') }}
            {{ __('Trigger it manually with:') }} <code>php artisan pantheon:sync</code>
        </p>
    @else
        <table>
            <thead>
                <tr>
                    <th>{{ __('Sync') }}</th>
                    <th>{{ __('Tenant') }}</th>
                    <th>{{ __('Started') }}</th>
                    <th>{{ __('Imported') }}</th>
                    <th>{{ __('Updated') }}</th>
                    <th>{{ __('Skipped') }}</th>
                    <th>{{ __('Errors') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($runs as $run)
                    <tr>
                        <td><code>{{ $run->sync }}</code></td>
                        <td class="muted">{{ $run->tenant_id ?? '—' }}</td>
                        <td>{{ $run->started_at }}</td>
                        <td>{{ $run->imported }}</td>
                        <td>{{ $run->updated }}</td>
                        <td>{{ $run->skipped }}</td>
                        <td>
                            @if ($run->failure)
                                <span class="bad">{{ __('failed') }}</span>: {{ $run->failure }}
                            @elseif ($run->error_count > 0)
                                <span class="bad">{{ $run->error_count }}</span>
                            @else
                                <span class="ok">0</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
