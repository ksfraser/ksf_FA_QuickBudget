<?php
/**
 * FactorTypes
 *
 * Constants for inflation factor types.
 * Used to pass to repositories for filtering.
 */
declare(strict_types=1);

final class FactorTypes
{
    public const GLOBAL = 'global';
    public const CATEGORY = 'category';
    public const GL = 'gl';
    public const TYPE = 'type';

    public static function all(): array
    {
        return [self::GLOBAL, self::CATEGORY, self::GL, self::TYPE];
    }
}
