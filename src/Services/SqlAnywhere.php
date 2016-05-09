<?php

namespace DreamFactory\Core\SqlAnywhere\Services;

use DreamFactory\Core\SqlDb\Services\SqlDb;

/**
 * Class SqlAnywhere
 *
 * @package DreamFactory\Core\SqlDb\Services
 */
class SqlAnywhere extends SqlDb
{
    public static function adaptConfig(array &$config)
    {
        $config['driver'] = 'dblib';
        if (in_array('dblib', \PDO::getAvailableDrivers())) {
            if (null !== $dumpLocation = config('df.db.freetds.dump')) {
                if (!putenv("TDSDUMP=$dumpLocation")) {
                    \Log::alert('Could not write environment variable for TDSDUMP location.');
                }
            }
            if (null !== $dumpConfLocation = config('df.db.freetds.dumpconfig')) {
                if (!putenv("TDSDUMPCONFIG=$dumpConfLocation")) {
                    \Log::alert('Could not write environment variable for TDSDUMPCONFIG location.');
                }
            }
            if (null !== $confLocation = config('df.db.freetds.sqlanywhere')) {
                if (!putenv("FREETDSCONF=$confLocation")) {
                    \Log::alert('Could not write environment variable for FREETDSCONF location.');
                }
            }
        }
        parent::adaptConfig($config);
    }
}