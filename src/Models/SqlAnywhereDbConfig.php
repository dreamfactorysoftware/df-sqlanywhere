<?php
namespace DreamFactory\Core\SqlAnywhere\Models;

use DreamFactory\Core\SqlDb\Models\SqlDbConfig;

/**
 * SqlAnywhereDbConfig
 *
 */
class SqlAnywhereDbConfig extends SqlDbConfig
{
    public static function getDriverName()
    {
        return 'dblib';
    }

    public static function getDefaultPort()
    {
        return 2638;
    }
}