<?php

declare(strict_types=1);

namespace Modules\Business\Helpers;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\Container as ContainerContract;
use Throwable;

/**
 * Resolve the currently active company id for tenant-aware queries.
 *
 * Resolution order:
 *   1. Container binding `business.current_company_id` (set by request
 *      middleware, console runner, queue handler, etc.).
 *   2. Authenticated user's `company_id` attribute, if present.
 *   3. `null` -> no filter applied (bootstrap mode for CLI seeders/migrations
 *      and unauthenticated contexts).
 *
 * The function is resilient to missing Laravel context: when the container
 * or auth facade have not been booted yet (pure unit tests, early boot),
 * each lookup is short-circuited and `null` is returned.
 */
function current_company_id(): ?int
{
    $container = container_or_null();

    if ($container !== null && $container->bound('business.current_company_id')) {
        try {
            $bound = $container->make('business.current_company_id');

            if (is_int($bound)) {
                return $bound;
            }

            if (is_numeric($bound)) {
                return (int) $bound;
            }
        } catch (Throwable) {
            // Fall through to the next resolver.
        }
    }

    try {
        $user = $container?->bound('auth') ? $container->make('auth')->user() : null;
    } catch (Throwable) {
        $user = null;
    }

    if ($user !== null && isset($user->company_id) && is_numeric($user->company_id)) {
        return (int) $user->company_id;
    }

    return null;
}

/**
 * Run the given callback impersonating a specific company tenant.
 *
 * Handy for jobs, seeders, and tests that need to materialise rows under a
 * deterministic tenant context without depending on the current Auth user.
 *
 * @template TReturn
 *
 * @param  callable():TReturn  $callback
 * @return TReturn
 */
function with_company(int $companyId, callable $callback)
{
    $container = container_or_null();

    if ($container === null) {
        return $callback();
    }

    $had_previous = $container->bound('business.current_company_id');
    $previous = $had_previous ? $container->make('business.current_company_id') : null;

    $container->instance('business.current_company_id', $companyId);

    try {
        return $callback();
    } finally {
        if ($had_previous) {
            $container->instance('business.current_company_id', $previous);
        } else {
            $container->forgetInstance('business.current_company_id');
        }
    }
}

/**
 * Return the active container instance or null when Laravel has not booted yet.
 *
 * @internal
 */
function container_or_null(): ?ContainerContract
{
    try {
        return Container::getInstance();
    } catch (Throwable) {
        return null;
    }
}
