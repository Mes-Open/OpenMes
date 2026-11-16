<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Reflector;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answer 404 for a route-model parameter that cannot be an integer key.
 *
 * Implicit binding hands `/admin/lines/abc` straight to the database. SQLite
 * just finds no row, but PostgreSQL rejects the comparison of a bigint column
 * with 'abc' (SQLSTATE 22P02), so a typo or a stale link in production became a
 * 500 instead of "not found". This runs right before SubstituteBindings (see the
 * priority list in bootstrap/app.php), after authentication, so a guest is still
 * sent to the login page first.
 *
 * Only parameters bound by the model's own integer primary key are checked: a
 * custom field (`{line:code}`) or an explicit Route::bind() is left alone.
 */
class RejectMalformedModelKeys
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();

        if ($route instanceof Route) {
            $parameters = $route->parameters();

            foreach ($route->signatureParameters(['subClass' => Model::class]) as $parameter) {
                $name = $this->parameterName($parameter->getName(), $parameters);
                if ($name === null) {
                    continue;
                }

                $value = $parameters[$name];
                if (! is_string($value) || ctype_digit($value)) {
                    continue;
                }

                if ($route->bindingFieldFor($name) !== null || app('router')->getBindingCallback($name) !== null) {
                    continue;
                }

                $model = app(Reflector::getParameterClassName($parameter));
                if ($model->getRouteKeyName() === $model->getKeyName()
                    && in_array($model->getKeyType(), ['int', 'integer'], true)) {
                    abort(404);
                }
            }
        }

        return $next($request);
    }

    /** Same lookup as Laravel's ImplicitRouteBinding: the name, then its snake_case. */
    private function parameterName(string $name, array $parameters): ?string
    {
        if (array_key_exists($name, $parameters)) {
            return $name;
        }

        $snake = Str::snake($name);

        return array_key_exists($snake, $parameters) ? $snake : null;
    }
}
