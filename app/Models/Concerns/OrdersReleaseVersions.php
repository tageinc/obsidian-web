<?php

namespace App\Models\Concerns;

trait OrdersReleaseVersions
{
    public function scopeOrderByReleaseVersion($query, string $direction = 'desc')
    {
        $direction = $direction === 'asc' ? 'asc' : 'desc';
        $integer = $query->getConnection()->getDriverName() === 'sqlite'
            ? "version GLOB '[1-9]*' AND version NOT GLOB '*[^0-9]*'"
            : "version REGEXP '^[1-9][0-9]*$'";

        // Integer releases take precedence; legacy text retains its previous lexical order.
        return $query->orderByRaw("CASE WHEN {$integer} THEN 1 ELSE 0 END {$direction}")
            ->orderByRaw("CASE WHEN {$integer} THEN LENGTH(version) ELSE 0 END {$direction}")
            ->orderBy('version', $direction);
    }
}
