<?php
namespace DreamFactory\Core\SqlAnywhere;

use DreamFactory\Core\Components\ServiceDocBuilder;
use DreamFactory\Core\Components\DbSchemaExtensions;
use DreamFactory\Core\Enums\ServiceTypeGroups;
use DreamFactory\Core\Services\ServiceManager;
use DreamFactory\Core\Services\ServiceType;
use DreamFactory\Core\SqlAnywhere\Database\Connectors\SqlAnywhereConnector;
use DreamFactory\Core\SqlAnywhere\Database\Schema\SqlAnywhereSchema;
use DreamFactory\Core\SqlAnywhere\Database\SqlAnywhereConnection;
use DreamFactory\Core\SqlAnywhere\Models\SqlAnywhereDbConfig;
use DreamFactory\Core\SqlAnywhere\Services\SqlAnywhere;
use Illuminate\Database\DatabaseManager;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    use ServiceDocBuilder;

    public function boot()
    {
        // Add our database drivers.
        $this->app->resolving('db', function (DatabaseManager $db) {
            $db->extend('sqlanywhere', function ($config) {
                $connector = new SqlAnywhereConnector();
                $connection = $connector->connect($config);

                return new SqlAnywhereConnection($connection, $config["database"], $config["prefix"], $config);
            });
        });

        // Add our service types.
        $this->app->resolving('df.service', function (ServiceManager $df) {
            $df->addType(
                new ServiceType([
                    'name'            => 'sqlanywhere',
                    'label'           => 'SAP SQL Anywhere',
                    'description'     => 'Database service supporting SAP SQL Anywhere connections.',
                    'group'           => ServiceTypeGroups::DATABASE,
                    'config_handler'  => SqlAnywhereDbConfig::class,
                    'default_api_doc' => function ($service) {
                        return $this->buildServiceDoc($service->id, SqlAnywhere::getApiDocInfo($service));
                    },
                    'factory'         => function ($config) {
                        return new SqlAnywhere($config);
                    },
                ])
            );
        });

        // Add our database extensions.
        $this->app->resolving('db.schema', function (DbSchemaExtensions $db) {
            $db->extend('sqlanywhere', function ($connection) {
                return new SqlAnywhereSchema($connection);
            });
        });
    }
}
