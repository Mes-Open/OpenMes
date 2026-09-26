<?php

namespace App\Http\Requests\Concerns;

use App\Extension\FilterRegistry;

/**
 * Let an installed module add its own fields to a core form.
 *
 * A module that contributes a field to the user or worker screen has to reach
 * three places: the form has to show it (HookRegistry), the request has to
 * accept it, and something has to store it. This is the middle one, and without
 * it the other two are useless — `validated()` returns only keys the rule set
 * names, so a field nobody declared is dropped between the browser and the
 * controller, silently and with no error to show for it.
 *
 * Adding the rule here is what makes the key survive. It also means the module's
 * field is validated server-side like any other, rather than trusting whatever
 * the page sent.
 *
 * Conventions a contributing module must follow:
 *
 * - **Prefix your keys** `module_<name>_<field>`, e.g. `module_example_code`.
 *   The rule set is one flat array shared with core; an unprefixed key will
 *   eventually collide with a core field, and the collision silently replaces
 *   the core rule.
 * - **Add, do not subtract.** The filter receives the complete rule set and can
 *   rewrite any of it, including weakening core rules — dropping `confirmed`
 *   from a password, say. FilterRegistry has no notion of "append only" and this
 *   is not policed. It is a real hole in a third-party module; keep it in mind
 *   before accepting one.
 */
trait AllowsModuleFields
{
    /**
     * The core rule set, plus whatever the installed modules add to it.
     *
     * With no module listening FilterRegistry returns the array untouched, so
     * the community path costs nothing.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    protected function withModuleFields(array $rules): array
    {
        return app(FilterRegistry::class)->filter(
            $this->moduleFieldFilter(),
            $rules,
            $this->moduleFieldContext(),
        );
    }

    /** The filter name, e.g. `validation.admin.users`. */
    abstract protected function moduleFieldFilter(): string;

    /**
     * What the module needs in order to build its rules — at minimum the record
     * being edited, so it can write its own `unique ... ignore`, and the action,
     * so a create and an edit can differ.
     *
     * @return array<string, mixed>
     */
    protected function moduleFieldContext(): array
    {
        return ['action' => $this->moduleFieldAction()];
    }

    /**
     * Whether this request creates or edits. A trait method cannot be reached
     * through `parent::`, so overrides call this rather than the default above.
     */
    protected function moduleFieldAction(): string
    {
        return $this->isMethod('POST') ? 'store' : 'update';
    }
}
