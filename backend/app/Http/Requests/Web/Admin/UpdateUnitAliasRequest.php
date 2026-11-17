<?php

namespace App\Http\Requests\Web\Admin;

class UpdateUnitAliasRequest extends StoreUnitAliasRequest
{
    protected function ignoredId(): ?int
    {
        return $this->route('unitAlias')?->id;
    }
}
