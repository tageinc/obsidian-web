<?php

namespace App\Models\Concerns;

use App\Services\RedisWorkloads;

trait InvalidatesSoftwareVersionCache
{
    protected static function bootInvalidatesSoftwareVersionCache(): void
    {
        $invalidate = function ($model) {
            $prefixes = array_unique([$model->getOriginal('prefix'), $model->prefix]);
            $kind = $model->softwareVersionKind;
            $callback = function () use ($kind, $prefixes) {
                $cache = app(RedisWorkloads::class);
                foreach ($prefixes as $prefix) {
                    $cache->invalidateVersion($kind, $prefix);
                }
            };

            if ($model->getConnection()->transactionLevel() > 0) {
                $model->getConnection()->afterCommit($callback);
            } else {
                $callback();
            }
        };

        static::saved($invalidate);
        static::deleted($invalidate);
    }
}
