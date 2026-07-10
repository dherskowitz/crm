<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Hides soft-merged records (those pointing at a surviving record via
 * merged_into_id) from all normal queries. Reverse a merge by querying with
 * `Model::withoutGlobalScope(NotMergedScope::class)`.
 *
 * @implements Scope<Model>
 */
class NotMergedScope implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereNull($model->getTable().'.merged_into_id');
    }
}
