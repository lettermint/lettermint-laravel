<?php

namespace Lettermint\Laravel\Webhooks\Data;

use DateTimeImmutable;
use Exception;

/**
 * Reads fields from decoded webhook data without trusting its shape.
 *
 * A missing field, or a field of an unexpected type, reads as null (or the
 * given default) instead of throwing, so a payload that gains, loses or
 * changes a field still decodes.
 *
 * @internal
 */
final class Field
{
    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function string(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return match (true) {
            is_string($value) => $value,
            is_int($value), is_float($value) => (string) $value,
            default => null,
        };
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function int(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return match (true) {
            is_int($value) => $value,
            is_float($value) && floor($value) === $value => (int) $value,
            is_string($value) && preg_match('/\A-?\d+\z/', $value) === 1 => (int) $value,
            default => null,
        };
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function float(array $data, string $key): ?float
    {
        $value = $data[$key] ?? null;

        return is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))
            ? (float) $value
            : null;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function bool(array $data, string $key, bool $default): bool
    {
        $value = $data[$key] ?? null;

        return is_bool($value) ? $value : $default;
    }

    /**
     * A nested object (a JSON object or array), or null.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>|null
     */
    public static function object(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $value : null;
    }

    /**
     * A nested object or list, or an empty array.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function array(array $data, string $key): array
    {
        return self::object($data, $key) ?? [];
    }

    /**
     * A list of strings; other items are skipped.
     *
     * @param  array<array-key, mixed>  $data
     * @return list<string>
     */
    public static function strings(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (is_string($value)) {
            return [$value];
        }

        return array_values(array_filter(self::array($data, $key), 'is_string'));
    }

    /**
     * Maps every object in a list; other items are skipped.
     *
     * @template T
     *
     * @param  array<array-key, mixed>  $data
     * @param  callable(array<array-key, mixed>): T  $map
     * @return list<T>
     */
    public static function list(array $data, string $key, callable $map): array
    {
        $items = [];

        foreach (self::array($data, $key) as $item) {
            if (is_array($item)) {
                $items[] = $map($item);
            }
        }

        return $items;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function date(array $data, string $key): ?DateTimeImmutable
    {
        $value = self::string($data, $key);

        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }
}
