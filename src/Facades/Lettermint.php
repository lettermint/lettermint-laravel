<?php

namespace Lettermint\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Lettermint\Lettermint as LettermintClient;

/**
 * The Lettermint client from the container. Its parts are properties on the
 * client, and the facade exposes each one as a method:
 *
 * ```php
 * Lettermint::emails()->send([...]);
 * Lettermint::domains()->list();
 * ```
 *
 * @method static \Lettermint\Resources\Emails emails()
 * @method static \Lettermint\Resources\Domains domains()
 * @method static \Lettermint\Resources\Messages messages()
 * @method static \Lettermint\Resources\Projects projects()
 * @method static \Lettermint\Resources\Routes routes()
 * @method static \Lettermint\Resources\Stats stats()
 * @method static \Lettermint\Resources\Suppressions suppressions()
 * @method static \Lettermint\Resources\Team team()
 * @method static \Lettermint\Resources\Webhooks webhooks()
 * @method static string ping()
 * @method static \Lettermint\Types\AnalyticsResponse analytics(array $query)
 * @method static \Lettermint\Types\BlockedFileTypes blockedFileTypes()
 *
 * @see LettermintClient
 */
class Lettermint extends Facade
{
    /**
     * The client properties the facade exposes as methods.
     */
    private const RESOURCES = [
        'emails',
        'domains',
        'messages',
        'projects',
        'routes',
        'stats',
        'suppressions',
        'team',
        'webhooks',
    ];

    protected static function getFacadeAccessor(): string
    {
        return LettermintClient::class;
    }

    /**
     * @param  string  $method
     * @param  array<array-key, mixed>  $args
     */
    public static function __callStatic($method, $args): mixed
    {
        $client = static::getFacadeRoot();

        if ($args === [] && $client instanceof LettermintClient && in_array($method, self::RESOURCES, true)) {
            return $client->{$method};
        }

        return parent::__callStatic($method, $args);
    }
}
