<?php
namespace DreamFactory\Core\SqlAnywhere\Database\Schema;

use DreamFactory\Core\Database\Enums\DbFunctionUses;
use DreamFactory\Core\Database\Enums\FunctionTypes;
use DreamFactory\Core\Database\Schema\ColumnSchema;
use DreamFactory\Core\Database\Schema\FunctionSchema;
use DreamFactory\Core\Database\Schema\ParameterSchema;
use DreamFactory\Core\Database\Schema\ProcedureSchema;
use DreamFactory\Core\Database\Schema\RoutineSchema;
use DreamFactory\Core\Database\Components\Schema;
use DreamFactory\Core\Database\Schema\TableSchema;
use DreamFactory\Core\Enums\DbResourceTypes;
use DreamFactory\Core\Enums\DbSimpleTypes;

/**
 * Schema is the class for retrieving metadata information from a MS SQL Server database.
 */
class SqlAnywhereSchema extends Schema
{
    /**
     * Underlying database provides field-level schema, i.e. SQL (true) vs NoSQL (false)
     */
    const PROVIDES_FIELD_SCHEMA = true;

    /**
     * @const string Quoting characters
     */
    const LEFT_QUOTE_CHARACTER = '[';

    const RIGHT_QUOTE_CHARACTER = ']';

    /**
     * @param boolean $refresh if we need to refresh schema cache.
     *
     * @return string default schema.
     */
    public function getDefaultSchema($refresh = false)
    {
        return $this->getUserName();
    }

    /**
     * @inheritdoc
     */
    public function getSupportedResourceTypes()
    {
        return [
            DbResourceTypes::TYPE_TABLE,
            DbResourceTypes::TYPE_VIEW,
            DbResourceTypes::TYPE_PROCEDURE,
            DbResourceTypes::TYPE_FUNCTION
        ];
    }

    protected function translateSimpleColumnTypes(array &$info)
    {
        // override this in each schema class
        $type = (isset($info['type'])) ? $info['type'] : null;
        switch ($type) {
            // some types need massaging, some need other required properties
            case 'pk':
            case DbSimpleTypes::TYPE_ID:
                $info['type'] = 'int';
                $info['allow_null'] = false;
                $info['auto_increment'] = true;
                $info['is_primary_key'] = true;
                break;

            case 'fk':
            case DbSimpleTypes::TYPE_REF:
                $info['type'] = 'int';
                $info['is_foreign_key'] = true;
                // check foreign tables
                break;

            case DbSimpleTypes::TYPE_TIMESTAMP_ON_CREATE:
                $info['type'] = 'timestamp';
                $default = (isset($info['default'])) ? $info['default'] : null;
                if (!isset($default)) {
                    $info['default'] = ['expression' => 'CURRENT TIMESTAMP'];
                }
                break;
            case DbSimpleTypes::TYPE_TIMESTAMP_ON_UPDATE:
                $info['type'] = 'timestamp';
                $default = (isset($info['default'])) ? $info['default'] : null;
                if (!isset($default)) {
                    $info['default'] = ['expression' => 'TIMESTAMP'];
                }
                break;
            case DbSimpleTypes::TYPE_USER_ID:
            case DbSimpleTypes::TYPE_USER_ID_ON_CREATE:
            case DbSimpleTypes::TYPE_USER_ID_ON_UPDATE:
                $info['type'] = 'int';
                break;

            case DbSimpleTypes::TYPE_BOOLEAN:
                $info['type'] = 'bit';
                $default = (isset($info['default'])) ? $info['default'] : null;
                if (isset($default)) {
                    // convert to bit 0 or 1, where necessary
                    $info['default'] = (int)filter_var($default, FILTER_VALIDATE_BOOLEAN);
                }
                break;

            case DbSimpleTypes::TYPE_INTEGER:
                $info['type'] = 'int';
                break;

            case DbSimpleTypes::TYPE_DOUBLE:
                $info['type'] = 'float';
                $info['type_extras'] = '(53)';
                break;

            case DbSimpleTypes::TYPE_TEXT:
                $info['type'] = 'long varchar';
                break;
            case 'ntext':
                $info['type'] = 'long nvarchar';
                break;
            case 'image':
                $info['type'] = 'varbinary';
                $info['type_extras'] = '(max)';
                break;

            case DbSimpleTypes::TYPE_STRING:
                $fixed =
                    (isset($info['fixed_length'])) ? filter_var($info['fixed_length'], FILTER_VALIDATE_BOOLEAN) : false;
                $national =
                    (isset($info['supports_multibyte'])) ? filter_var($info['supports_multibyte'],
                        FILTER_VALIDATE_BOOLEAN) : false;
                if ($fixed) {
                    $info['type'] = ($national) ? 'nchar' : 'char';
                } elseif ($national) {
                    $info['type'] = 'nvarchar';
                } else {
                    $info['type'] = 'varchar';
                }
                break;

            case DbSimpleTypes::TYPE_BINARY:
                $fixed =
                    (isset($info['fixed_length'])) ? filter_var($info['fixed_length'], FILTER_VALIDATE_BOOLEAN) : false;
                $info['type'] = ($fixed) ? 'binary' : 'varbinary';
                break;
        }
    }

    protected function validateColumnSettings(array &$info)
    {
        // override this in each schema class
        $type = (isset($info['type'])) ? $info['type'] : null;
        switch ($type) {
            // some types need massaging, some need other required properties
            case 'bit':
            case 'tinyint':
            case 'smallint':
            case 'int':
            case 'bigint':
                if (!isset($info['type_extras'])) {
                    $length =
                        (isset($info['length']))
                            ? $info['length']
                            : ((isset($info['precision'])) ? $info['precision']
                            : null);
                    if (!empty($length)) {
                        $info['type_extras'] = "($length)"; // sets the viewable length
                    }
                }

                $default = (isset($info['default'])) ? $info['default'] : null;
                if (isset($default) && is_numeric($default)) {
                    $info['default'] = intval($default);
                }
                break;

            case 'decimal':
            case 'numeric':
            case 'money':
            case 'smallmoney':
                if (!isset($info['type_extras'])) {
                    $length =
                        (isset($info['length']))
                            ? $info['length']
                            : ((isset($info['precision'])) ? $info['precision']
                            : null);
                    if (!empty($length)) {
                        $scale =
                            (isset($info['decimals']))
                                ? $info['decimals']
                                : ((isset($info['scale'])) ? $info['scale']
                                : null);
                        $info['type_extras'] = (!empty($scale)) ? "($length,$scale)" : "($length)";
                    }
                }

                $default = (isset($info['default'])) ? $info['default'] : null;
                if (isset($default) && is_numeric($default)) {
                    $info['default'] = floatval($default);
                }
                break;
            case 'real':
            case 'float':
                if (!isset($info['type_extras'])) {
                    $length =
                        (isset($info['length']))
                            ? $info['length']
                            : ((isset($info['precision'])) ? $info['precision']
                            : null);
                    if (!empty($length)) {
                        $info['type_extras'] = "($length)";
                    }
                }

                $default = (isset($info['default'])) ? $info['default'] : null;
                if (isset($default) && is_numeric($default)) {
                    $info['default'] = floatval($default);
                }
                break;

            case 'char':
            case 'nchar':
            case 'binary':
                $length = (isset($info['length'])) ? $info['length'] : ((isset($info['size'])) ? $info['size'] : null);
                if (isset($length)) {
                    $info['type_extras'] = "($length)";
                }
                break;

            case 'varchar':
            case 'nvarchar':
            case 'varbinary':
                $length = (isset($info['length'])) ? $info['length'] : ((isset($info['size'])) ? $info['size'] : null);
                if (isset($length)) {
                    $info['type_extras'] = "($length)";
                } else // requires a max length
                {
                    $info['type_extras'] = '(' . static::DEFAULT_STRING_MAX_SIZE . ')';
                }
                break;

            case 'time':
            case 'datetime':
            case 'timestamp':
                $length = (isset($info['length'])) ? $info['length'] : ((isset($info['size'])) ? $info['size'] : null);
                if (isset($length)) {
                    $info['type_extras'] = "($length)";
                }
                break;
        }
    }

    /**
     * @param array $info
     *
     * @return string
     * @throws \Exception
     */
    protected function buildColumnDefinition(array $info)
    {
        $type = (isset($info['type'])) ? $info['type'] : null;
        $typeExtras = (isset($info['type_extras'])) ? $info['type_extras'] : null;

        $definition = $type . $typeExtras;

        $allowNull = (isset($info['allow_null'])) ? filter_var($info['allow_null'], FILTER_VALIDATE_BOOLEAN) : false;
        $definition .= ($allowNull) ? ' NULL' : ' NOT NULL';

        $default = (isset($info['default'])) ? $info['default'] : null;
        if (isset($default)) {
            if (is_array($default)) {
                $expression = (isset($default['expression'])) ? $default['expression'] : null;
                if (null !== $expression) {
                    $definition .= ' DEFAULT ' . $expression;
                }
            } else {
                $default = $this->quoteValue($default);
                $definition .= ' DEFAULT ' . $default;
            }
        }

        $auto = (isset($info['auto_increment'])) ? filter_var($info['auto_increment'], FILTER_VALIDATE_BOOLEAN) : false;
        if ($auto) {
            $definition .= ' IDENTITY';
        }

        $isUniqueKey = (isset($info['is_unique'])) ? filter_var($info['is_unique'], FILTER_VALIDATE_BOOLEAN) : false;
        $isPrimaryKey =
            (isset($info['is_primary_key'])) ? filter_var($info['is_primary_key'], FILTER_VALIDATE_BOOLEAN) : false;
        if ($isPrimaryKey && $isUniqueKey) {
            throw new \Exception('Unique and Primary designations not allowed simultaneously.');
        }

        if ($isUniqueKey) {
            $definition .= ' UNIQUE';
        } elseif ($isPrimaryKey) {
            $definition .= ' PRIMARY KEY';
        }

        return $definition;
    }

    /**
     * Compares two table names.
     * The table names can be either quoted or unquoted. This method
     * will consider both cases.
     *
     * @param string $name1 table name 1
     * @param string $name2 table name 2
     *
     * @return boolean whether the two table names refer to the same table.
     */
    public function compareTableNames($name1, $name2)
    {
        $name1 = str_replace(['[', ']'], '', $name1);
        $name2 = str_replace(['[', ']'], '', $name2);

        return parent::compareTableNames(strtolower($name1), strtolower($name2));
    }

    /**
     * Resets the sequence value of a table's primary key.
     * The sequence will be reset such that the primary key of the next new row inserted
     * will have the specified value or max value of a primary key plus one (i.e. sequence trimming).
     *
     * @param TableSchema  $table   the table schema whose primary key sequence will be reset
     * @param integer|null $value   the value for the primary key of the next new row inserted.
     *                              If this is not set, the next new row's primary key will have the max value of a
     *                              primary key plus one (i.e. sequence trimming).
     *
     */
    public function resetSequence($table, $value = null)
    {
        if ($table->sequenceName === null) {
            return;
        }
        if ($value !== null) {
            $value = (int)($value) - 1;
        } else {
            /** @noinspection SqlNoDataSourceInspection */
            /** @noinspection SqlDialectInspection */
            $value = (int)$this->selectValue("SELECT MAX([{$table->primaryKey}]) FROM {$table->quotedName}");
        }
        $name = strtr($table->quotedName, ['[' => '', ']' => '']);
        $this->connection->statement("DBCC CHECKIDENT ('$name',RESEED,$value)");
    }

    private $normalTables = [];  // non-view tables

    /**
     * Enables or disables integrity check.
     *
     * @param boolean $check  whether to turn on or off the integrity check.
     * @param string  $schema the schema of the tables. Defaults to empty string, meaning the current or default schema.
     *
     */
    public function checkIntegrity($check = true, $schema = '')
    {
        $enable = $check ? 'CHECK' : 'NOCHECK';
        if (!isset($this->normalTables[$schema])) {
            $this->normalTables[$schema] = $this->findTableNames($schema);
        }
        $db = $this->connection;
        foreach ($this->normalTables[$schema] as $table) {
            $tableName = $this->quoteTableName($table->name);
            /** @noinspection SqlNoDataSourceInspection */
            $db->statement("ALTER TABLE $tableName $enable CONSTRAINT ALL");
        }
    }

    /**
     * @inheritdoc
     */
    protected function findTableReferences()
    {
        $sql = <<<EOD
SELECT columns, foreign_creator AS 'table_schema', foreign_tname AS 'table_name',
    primary_creator AS 'referenced_table_schema', primary_tname AS 'referenced_table_name'
FROM SYS.SYSFOREIGNKEYS WHERE foreign_creator NOT IN ('SYS','dbo')
EOD;
        $constraints = $this->connection->select($sql);
        foreach ($constraints as &$constraint) {
            $constraint = (array)$constraint;
            list($constraint['column_name'], $constraint['referenced_column_name']) =
                explode(' IS ', $constraint['columns']);
        }

        return $constraints;
    }

    /**
     * @inheritdoc
     */
    protected function findColumns(TableSchema $table)
    {
        $schema = (!empty($table->schemaName)) ? $table->schemaName : $this->getDefaultSchema();
        $params = [':schema' => $schema, ':table' => $table->resourceName];
        $sql = <<<MYSQL
SELECT * FROM sys.syscolumns WHERE creator = :schema AND tname = :table
MYSQL;

        if (!empty($columns = $this->connection->select($sql, $params))) {
            $sql = <<<EOD
SELECT indextype,colnames FROM sys.sysindexes WHERE creator = :schema AND tname = :table
EOD;
            $constraints = $this->connection->select($sql, $params);

            foreach ($constraints as $key => $constraint) {
                $constraint = (array)$constraint;
                $type = $constraint['indextype'];
                $colnames = $constraint['colnames'];
                $colnames = explode(',', $colnames);
                foreach ($colnames as $key) {
                    $key = strstr($key, ' ', true);
                    foreach ($columns as &$column) {
                        if ($key === array_get($column, 'cname')) {
                            switch ($type) {
                                case 'Primary Key':
                                    $column['isPrimaryKey'] = true;
                                    break;
                                case 'Unique Constraint':
                                    $column['isUnique'] = true;
                                    break;
                                case 'Non-unique':
                                    $column['isIndex'] = true;
                                    break;
                            }
                        }
                    }
                }
            }
        }

        return $columns;
    }

    /**
     * Creates a table column.
     *
     * @param array $column column metadata
     *
     * @return ColumnSchema normalized column metadata
     */
    protected function createColumn($column)
    {
        $c = new ColumnSchema(['name' => $column['cname']]);
        $c->quotedName = $this->quoteColumnName($c->name);
        $c->allowNull = $column['nulls'] == 'Y';
        $c->isPrimaryKey = $column['in_primary_key'] == 'Y';
        $c->dbType = $column['coltype'];
        $c->scale = intval($column['syslength']);
        $c->precision = $c->size = intval($column['length']);
        $c->comment = $column['remarks'];

        $c->fixedLength = $this->extractFixedLength($c->dbType);
        $c->supportsMultibyte = $this->extractMultiByteSupport($c->dbType);
        $this->extractType($c, $c->dbType);
        if (isset($column['default_value'])) {
            $this->extractDefault($c, $column['default_value']);
        }

        switch ($c->dbType) {
            case 'geometry':
            case 'geography':
            case 'hierarchyid':
                $c->dbFunction = [
                    [
                        'use'           => [DbFunctionUses::SELECT],
                        'function'      => "({$c->quotedName}.ToString())",
                        'function_type' => FunctionTypes::DATABASE
                    ]
                ];
                break;
        }

        return $c;
    }

    protected function findSchemaNames()
    {
        $sql = <<<MYSQL
SELECT user_name FROM sysuser WHERE user_name NOT IN ('SYS','dbo','EXTENV_MAIN','EXTENV_WORKER') and user_type IN (12,13,14)
MYSQL;

        return $this->selectColumn($sql);
    }

    /**
     * @inheritdoc
     */
    protected function findTableNames($schema = '')
    {
        $condition = "tabletype = 'TABLE'";
        $params = [];
        if (!empty($schema)) {
            $condition .= " AND creator = :schema";
            $params[':schema'] = $schema;
        }

        $sql = <<<MYSQL
SELECT creator, tname, remarks FROM sys.syscatalog WHERE {$condition} ORDER BY tname
MYSQL;

        $rows = $this->connection->select($sql, $params);

        $defaultSchema = $this->getNamingSchema();
        $addSchema = (!empty($schema) && ($defaultSchema !== $schema));

        $names = [];
        foreach ($rows as $row) {
            $row = array_change_key_case((array)$row, CASE_LOWER);
            $schemaName = isset($row['creator']) ? $row['creator'] : '';
            $resourceName = isset($row['tname']) ? $row['tname'] : '';
            $internalName = $schemaName . '.' . $resourceName;
            $name = ($addSchema) ? $internalName : $resourceName;
            $quotedName = $this->quoteTableName($schemaName) . '.' . $this->quoteTableName($resourceName);
            $settings = compact('schemaName', 'resourceName', 'name', 'internalName', 'quotedName');
            $settings['description'] = $row['remarks'];
            $names[strtolower($name)] = new TableSchema($settings);
        }

        return $names;
    }

    /**
     * @inheritdoc
     */
    protected function findViewNames($schema = '')
    {
        $condition = "tabletype IN ('VIEW','MAT VIEW')";
        $params = [];
        if (!empty($schema)) {
            $condition .= " AND creator = :schema";
            $params[':schema'] = $schema;
        }

        $sql = <<<MYSQL
SELECT creator, tname, remarks FROM sys.syscatalog WHERE {$condition} ORDER BY tname
MYSQL;

        $rows = $this->connection->select($sql, $params);

        $defaultSchema = $this->getNamingSchema();
        $addSchema = (!empty($schema) && ($defaultSchema !== $schema));

        $names = [];
        foreach ($rows as $row) {
            $row = array_change_key_case((array)$row, CASE_LOWER);
            $schemaName = isset($row['creator']) ? $row['creator'] : '';
            $resourceName = isset($row['tname']) ? $row['tname'] : '';
            $internalName = $schemaName . '.' . $resourceName;
            $name = ($addSchema) ? $internalName : $resourceName;
            $quotedName = $this->quoteTableName($schemaName) . '.' . $this->quoteTableName($resourceName);
            $settings = compact('schemaName', 'resourceName', 'name', 'internalName', 'quotedName');
            $settings['isView'] = true;
            $settings['description'] = $row['remarks'];
            $names[strtolower($name)] = new TableSchema($settings);
        }

        return $names;
    }

    /**
     * @inheritdoc
     */
    protected function findRoutineNames($type, $schema = '')
    {
        $bindings = [];
        $where = '';
        if (!empty($schema)) {
            $where = 'WHERE creator = :schema';
            $bindings[':schema'] = $schema;
        }

        $sql = <<<MYSQL
SELECT procname FROM SYS.SYSPROCS {$where} ORDER BY procname
MYSQL;

        $rows = $this->selectColumn($sql, $bindings);

        $sql = <<<MYSQL
SELECT procname FROM SYS.SYSPROCPARMS {$where} AND parmtype = 4
MYSQL;

        $functions = $this->selectColumn($sql, $bindings);

        $defaultSchema = $this->getNamingSchema();
        $addSchema = (!empty($schema) && ($defaultSchema !== $schema));

        $names = [];
        foreach ($rows as $resourceName) {
            if ((false === array_search($resourceName, $functions)) && ('FUNCTION' === $type)) {
                // only way to determine proc from func is by params??
                continue;
            }

            $schemaName = $schema;
            $internalName = $schemaName . '.' . $resourceName;
            $name = ($addSchema) ? $internalName : $resourceName;
            $quotedName = $this->quoteTableName($schemaName) . '.' . $this->quoteTableName($resourceName);
            $settings = compact('schemaName', 'resourceName', 'name', 'quotedName', 'internalName');
            $names[strtolower($name)] =
                ('PROCEDURE' === $type) ? new ProcedureSchema($settings) : new FunctionSchema($settings);
        }

        return $names;
    }

    /**
     * Loads the parameter metadata for the specified stored procedure or function.
     *
     * @param RoutineSchema $holder
     */
    protected function loadParameters(RoutineSchema &$holder)
    {
        $sql = <<<MYSQL
SELECT 
    parm_id, parmmode, parmname, parmtype, parmdomain, user_type, length, scale, "default"
FROM 
    SYS.SYSPROCPARMS
WHERE 
    procname = '{$holder->name}' AND creator = '{$holder->schemaName}'
MYSQL;

        foreach ($this->connection->select($sql) as $row) {
            $row = array_change_key_case((array)$row, CASE_LOWER);
            $simpleType = static::extractSimpleType(array_get($row, 'parmdomain'));
            /*
            parmtype	SMALLINT	The type of parameter will be one of the following:
            0 - Normal parameter (variable)
            1 - Result variable - used with a procedure that returns result sets
            2 - SQLSTATE error value
            3 - SQLCODE error value
            4 - Return value from function
             */
            switch (intval(array_get($row, 'parmtype'))) {
                case 0:
                    $holder->addParameter(new ParameterSchema(
                        [
                            'name'          => array_get($row, 'parmname'),
                            'position'      => intval(array_get($row, 'parm_id')),
                            'param_type'    => array_get($row, 'parmmode'),
                            'type'          => $simpleType,
                            'db_type'       => array_get($row, 'parmdomain'),
                            'length'        => (isset($row['length']) ? intval(array_get($row, 'length')) : null),
                            'precision'     => (isset($row['length']) ? intval(array_get($row, 'length')) : null),
                            'scale'         => (isset($row['scale']) ? intval(array_get($row, 'scale')) : null),
                            'default_value' => array_get($row, 'default'),
                        ]
                    ));
                    break;
                case 1:
                case 4:
                    $holder->returnType = $simpleType;
                    break;
            }
        }
    }

    /**
     * Builds a SQL statement for renaming a DB table.
     *
     * @param string $table   the table to be renamed. The name will be properly quoted by the method.
     * @param string $newName the new table name. The name will be properly quoted by the method.
     *
     * @return string the SQL statement for renaming a DB table.
     */
    public function renameTable($table, $newName)
    {
        return "sp_rename '$table', '$newName'";
    }

    /**
     * Builds a SQL statement for renaming a column.
     *
     * @param string $table   the table whose column is to be renamed. The name will be properly quoted by the method.
     * @param string $name    the old name of the column. The name will be properly quoted by the method.
     * @param string $newName the new name of the column. The name will be properly quoted by the method.
     *
     * @return string the SQL statement for renaming a DB column.
     */
    public function renameColumn($table, $name, $newName)
    {
        return "sp_rename '$table.$name', '$newName', 'COLUMN'";
    }

    /**
     * Builds a SQL statement for changing the definition of a column.
     *
     * @param string $table      the table whose column is to be changed. The table name will be properly quoted by the
     *                           method.
     * @param string $column     the name of the column to be changed. The name will be properly quoted by the method.
     * @param string $definition the new column type. The {@link getColumnType} method will be invoked to convert
     *                           abstract column type (if any) into the physical one. Anything that is not recognized
     *                           as abstract type will be kept in the generated SQL. For example, 'string' will be
     *                           turned into 'varchar(255)', while 'string not null' will become 'varchar(255) not
     *                           null'.
     *
     * @return string the SQL statement for changing the definition of a column.
     * @since 1.1.6
     */
    public function alterColumn($table, $column, $definition)
    {
        $sql = <<<MYSQL
ALTER TABLE {$this->quoteTableName($table)}
ALTER COLUMN {$this->quoteColumnName($column)} {$this->getColumnType($definition)}
MYSQL;

        return $sql;
    }

    public function parseValueForSet($value, $field_info)
    {
        switch ($field_info->type) {
            case DbSimpleTypes::TYPE_BOOLEAN:
                $value = ($value ? 1 : 0);
                break;
        }

        return parent::parseValueForSet($value, $field_info);
    }

    public function formatValue($value, $type)
    {
        $value = parent::formatValue($value, $type);

        if (' ' === $value) {
            // SQL Anywhere strangely returns empty string as a single space string
            return '';
        }

        return $value;
    }

    /**
     * Extracts the PHP type from DB type.
     *
     * @param ColumnSchema $column
     * @param string       $dbType DB type
     */
    public function extractType(ColumnSchema $column, $dbType)
    {
        parent::extractType($column, $dbType);

        $simpleType = strstr($dbType, '(', true);
        $simpleType = strtolower($simpleType ?: $dbType);

        switch ($simpleType) {
            case 'long varchar':
                $column->type = DbSimpleTypes::TYPE_TEXT;
                break;
            case 'long nvarchar':
                $column->type = DbSimpleTypes::TYPE_TEXT;
                $column->supportsMultibyte = true;
                break;
        }
    }

    /**
     * Extracts the default value for the column.
     * The value is typecasted to correct PHP type.
     *
     * @param ColumnSchema $field
     * @param mixed        $defaultValue the default value obtained from metadata
     */
    public function extractDefault(ColumnSchema $field, $defaultValue)
    {
        if ('autoincrement' === $defaultValue) {
            $field->defaultValue = null;
            $field->autoIncrement = true;
        } elseif (('(NULL)' === $defaultValue) || ('' === $defaultValue)) {
            $field->defaultValue = null;
        } elseif ($field->type === DbSimpleTypes::TYPE_BOOLEAN) {
            if ('1' === $defaultValue) {
                $field->defaultValue = true;
            } elseif ('0' === $defaultValue) {
                $field->defaultValue = false;
            } else {
                $field->defaultValue = null;
            }
        } elseif ($field->type === DbSimpleTypes::TYPE_TIMESTAMP) {
            $field->defaultValue = null;
            if ('current timestamp' === $defaultValue) {
                $field->defaultValue = ['expression' => 'CURRENT TIMESTAMP'];
                $field->type = DbSimpleTypes::TYPE_TIMESTAMP_ON_CREATE;
            } elseif ('timestamp' === $defaultValue) {
                $field->defaultValue = ['expression' => 'TIMESTAMP'];
                $field->type = DbSimpleTypes::TYPE_TIMESTAMP_ON_UPDATE;
            }
        } else {
            parent::extractDefault($field, str_replace(['(', ')', "'"], '', $defaultValue));
        }
    }

    /**
     * Extracts size, precision and scale information from column's DB type.
     * We do nothing here, since sizes and precisions have been computed before.
     *
     * @param ColumnSchema $field
     * @param string       $dbType the column's DB type
     */
    public function extractLimit(ColumnSchema $field, $dbType)
    {
    }

    /**
     * Converts the input value to the type that this column is of.
     *
     * @param ColumnSchema $field
     * @param mixed        $value input value
     *
     * @return mixed converted value
     */
    public function typecast(ColumnSchema $field, $value)
    {
        if ($field->phpType === 'boolean') {
            return $value ? 1 : 0;
        } else {
            return parent::typecast($field, $value);
        }
    }

    /**
     * @inheritdoc
     */
    protected function getProcedureStatement(RoutineSchema $routine, array $param_schemas, array &$values)
    {
        // Note that using the dblib driver doesn't allow binding of output parameters,
        // and also requires declaration prior to and selecting after to retrieve them.

        $paramStr = '';
        $prefix = '';
        $postfix = '';
        $bindings = [];
        foreach ($param_schemas as $key => $paramSchema) {
            switch ($paramSchema->paramType) {
                case 'IN':
                    $pName = ':' . $paramSchema->name;
                    $paramStr .= (empty($paramStr)) ? $pName : ", $pName";
                    $bindings[$pName] = array_get($values, $key);
                    break;
                case 'INOUT':
//                    $pName = $paramSchema->name;
//                    $paramStr .= (empty($paramStr) ? $pName : ", $pName");
                    // with dblib driver you can't bind output parameters
//                    $prefix .= "CREATE VARIABLE $pName {$paramSchema->dbType};";
//                    $prefix .= "SET $pName = " . array_get($values, $paramSchema->name) . ';';
//                    $postfix .= "SELECT $pName as " . $this->quoteColumnName($paramSchema->name) . ';';
                    break;
                case 'OUT':
//                    $pName = $paramSchema->name;
//                    $paramStr .= (empty($paramStr) ? $pName : ", $pName");
                    // with dblib driver you can't bind output parameters
//                    $prefix .= "CREATE VARIABLE $pName {$paramSchema->dbType};";
//                    $postfix .= "SELECT $pName as " . $this->quoteColumnName($paramSchema->name) . ';';
                    break;
            }
        }

        return "$prefix CALL {$routine->quotedName}($paramStr); $postfix";
    }

    protected function doRoutineBinding($statement, array $paramSchemas, array &$values)
    {
        // Note that using the dblib driver doesn't allow binding of output parameters,
        // and also requires declaration prior to and selecting after to retrieve them.
        $dblib = in_array('dblib', \PDO::getAvailableDrivers());
        // do binding
        foreach ($paramSchemas as $key => $paramSchema) {
            switch ($paramSchema->paramType) {
                case 'IN':
                    $this->bindValue($statement, ':' . $paramSchema->name, array_get($values, $key));
                    break;
                case 'INOUT':
                case 'OUT':
                    if (!$dblib) {
                        $pdoType = $this->getPdoType($paramSchema->type);
                        $this->bindParam($statement, ':' . $paramSchema->name, $values[$key],
                            $pdoType | \PDO::PARAM_INPUT_OUTPUT, $paramSchema->length);
                    }
                    break;
            }
        }
    }
}
