<?php

namespace App\Concerns;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = Tenant::currentId();

        if ($tenantId !== null) {
            $builder->where($model->getTable().'.tenant_id', $tenantId);
        } elseif (! $model instanceof \App\Models\User) {
            $builder->whereRaw('1 = 0');
        }
    }
}
