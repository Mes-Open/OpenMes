<?php

namespace App\Services\Quality;

use App\Models\CustomerFlavorStandard;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * 按“客户 + 产品 + 日期”解析当前生效的客户风味标准。
 *
 * 规则：
 *  - 只取 ACTIVE；
 *  - 具体产品标准优先于产品族通用标准；
 *  - 生效日期在有效区间内；
 *  - 同一优先级取最新生效、最新创建的一条。
 */
class CustomerFlavorStandardResolver
{
    public function resolve(?int $customerId, ?int $productTypeId, ?CarbonInterface $on = null): ?CustomerFlavorStandard
    {
        if (! $customerId) {
            return null;
        }

        $on = $on ? Carbon::instance($on) : now();

        return CustomerFlavorStandard::query()
            ->active()
            ->where('customer_id', $customerId)
            ->where(function ($query) use ($productTypeId) {
                $query->whereNull('product_type_id');
                if ($productTypeId) {
                    $query->orWhere('product_type_id', $productTypeId);
                }
            })
            ->where(function ($query) use ($on) {
                $query->whereNull('effective_from')->orWhereDate('effective_from', '<=', $on->toDateString());
            })
            ->where(function ($query) use ($on) {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $on->toDateString());
            })
            ->orderByRaw('CASE WHEN product_type_id IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }
}
