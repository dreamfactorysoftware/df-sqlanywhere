<?php
namespace DreamFactory\Core\SqlAnywhere\Database\Schema;

use DreamFactory\Core\Database\DataReader;
use DreamFactory\Core\Database\Schema\ColumnSchema;
use DreamFactory\Core\Database\Schema\Schema;
use DreamFactory\Core\Database\Schema\TableSchema;

/**
 * Schema is the class for retrieving metadata information from a MS SQL Server database.
 */
class SqlAnywhereSchema extends Schema
{
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

    protected function translateSimpleColumnTypes(array &$info)
    {
        // override this in each schema class
        $type = (isset($info['type'])) ? $info['type'] : null;
        switch ($type) {
            // some types need massaging, some need other required properties
            case 'pk':
            case ColumnSchema::TYPE_ID:
                $info['type'] = 'int';
                $info['allow_null'] = false;
                $info['auto_increment'] = true;
                $info['is_primary_key'] = true;
                break;

            case 'fk':
            case ColumnSchema::TYPE_REF:
                $info['type'] = 'int';
                $info['is_foreign_key'] = true;
                // check foreign tables
                break;

            case ColumnSchema::TYPE_TIMESTAMP_ON_CREATE:
                $info['type'] = 'timestamp';
                $default = (isset($info['default'])) ? $info['default'] : null;
                if (!isset($default)) {
                    $info['default'] = ['expression' => 'CURRENT TIMESTAMP'];
                }
                break;
            case ColumnSchema::TYPE_TIMESTAMP_ON_UPDATE:
                $info['type'] = 'timestamp';
                $default = (isset($info['default'])) ? $info['default'] : null;
                if (!isset($default)) {
                    $info['default'] = ['expression' => 'TIMESTAMP'];
                }
                break;
            case ColumnSchema::TYPE_USER_ID:
            case ColumnSchema::TYPE_USER_ID_ON_CREATE:
            case ColumnSchema::TYPE_USER_ID_ON_UPDATE:
                $info['type'] = 'int';
                break;

            case ColumnSchema::TYPE_BOOLEAN:
                $info['type'] = 'bit';
                $default = (isset($info['default'])) ? $info['default'] : null;
                if (isset($default)) {
                    // convert to bit 0 or 1, where necessary
                    $info['default'] = (int)filter_var($default, FILTER_VALIDATE_BOOLEAN);
                }
                break;

            case ColumnSchema::TYPE_INTEGER:
                $info['type'] = 'int';
                break;

            case ColumnSchema::TYPE_DOUBLE:
                $info['type'] = 'float';
                $info['type_extras'] = '(53)';
                break;

            case ColumnSchema::TYPE_TEXT:
                $info['type'] = 'long varchar';
                break;
            case 'ntext':
                $info['type'] = 'long nvarchar';
                break;
            case 'image':
                $info['type'] = 'varbinary';
                $info['type_extras'] = '(max)';
                break;

            case ColumnSchema::TYPE_STRING:
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

            case ColumnSchema::TYPE_BINARY:
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
            $value = (int)$this->selectValue("SELECT MAX([{$table->primaryKey}]) FROM {$table->rawName}");
        }
        $name = strtr($table->rawName, ['[' => '', ']' => '']);
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
            $this->normalTables[$schema] = $this->findTableNames($schema, false);
        }
        $db = $this->connection;
        foreach ($this->normalTables[$schema] as $table) {
            $tableName = $this->quoteTableName($table->name);
            $db->statement("ALTER TABLE $tableName $enable CONSTRAINT ALL");
        }
    }

    /**
     * @inheritdoc
     */
    protected function loadTable(TableSchema $table)
    {
        if (!$this->findColumns($table)) {
            return null;
        }

        $this->findConstraints($table);

        return $table;
    }

    /**
     * Collects the foreign key column details for the given table.
     * Also, collects the foreign tables and columns that reference the given table.
     *
     * @param TableSchema $table the table metadata
     */
    protected function findConstraints($table)
    {
        $schema = (!empty($table->schemaName)) ? $table->schemaName : $this->getDefaultSchema();

        $sql = <<<EOD
SELECT indextype,colnames FROM SYS.SYSINDEXES WHERE creator = :schema AND tname = :table
EOD;
        $params = [':schema' => $schema, ':table' => $table->tableName];
        $constraints = $this->connection->select($sql, $params);

        foreach ($constraints as $key => $constraint) {
            $constraint = (array)$constraint;
            $type = $constraint['indextype'];
            $colnames = $constraint['colnames'];
            switch ($type) {
                case 'Primary Key':
                    $colnames = explode(',', $colnames);
                    switch (count($colnames)) {
                        case 0: // No primary key on table
                            $table->primaryKey = null;
                            break;
                        case 1: // Only 1 primary key
                            $primary = strstr($colnames[0], ' ', true);
                            $column = $table->getColumn($primary);
                            if (isset($column)) {
                                $column->isPrimaryKey = true;
                                if ((ColumnSchema::TYPE_INTEGER === $column->type) && $column->autoIncrement) {
                                    $column->type = ColumnSchema::TYPE_ID;
                                }
                                $table->addColumn($column);
                            }
                            $table->primaryKey = $primary;
                            break;
                        default:
                            if (is_array($colnames)) {
                                $primary = '';
                                foreach ($colnames as $key) {
                                    $key = strstr($key, ' ', true);
                                    $primary = (empty($key)) ? $key : ',' . $key;
                                }
                                $table->primaryKey = $primary;
                            }
                            break;
                    }
                    break;
                case 'Unique Constraint':
                    $column = $table->getColumn(strstr($colnames, ' ', true));
                    if (isset($column)) {
                        $column->IsUnique = true;
                        $table->addColumn($column);
                    }
                    break;
                case 'Non-unique':
                    $colnames = explode(',', $colnames);
                    switch (count($colnames)) {
                        case 1: // Only 1 key
                            $column = $table->getColumn(strstr($colnames[0], ' ', true));
                            if (isset($column)) {
                                $column->isIndex = true;
                                $table->addColumn($column);
                            }
                            break;
                        default:
                            if (is_array($colnames)) {
                                foreach ($colnames as $key) {
                                    $column = $table->getColumn(strstr($key, ' ', true));
                                    if (isset($column)) {
                                        $column->isIndex = true;
                                        $table->addColumn($column);
                                    }
                                }
                            }
                            break;
                    }
                    break;
            }
        }

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

        $this->buildTableRelations($table, $constraints);
    }

    /**
     * Collects the table column metadata.
     *
     * @param TableSchema $table the table metadata
     *
     * @return boolean whether the table exists in the database
     */
    protected function findColumns($table)
    {
        $sql = <<<SQL
SELECT * FROM sys.syscolumns WHERE creator = '{$table->schemaName}' AND tname = '{$table->tableName}'
SQL;

        try {
            $columns = $this->connection->select($sql);
            if (empty($columns)) {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }

        foreach ($columns as $column) {
            $column = (array)$column;
            $c = $this->createColumn($column);
            $table->addColumn($c);
            if ($c->autoIncrement && $table->sequenceName === null) {
                $table->sequenceName = $table->name;
            }
        }

        return true;
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
        $c->rawName = $this->quoteColumnName($c->name);
        $c->allowNull = $column['nulls'] == 'Y';
        $c->isPrimaryKey = $column['in_primary_key'] == 'Y';
        $c->dbType = $column['coltype'];
        $c->scale = intval($column['syslength']);
        $c->precision = $c->size = intval($column['length']);
        $c->comment = $column['remarks'];

        $c->fixedLength = $this->extractFixedLength($column['coltype']);
        $c->supportsMultibyte = $this->extractMultiByteSupport($column['coltype']);
        $this->extractType($c, $column['coltype']);
        if (isset($column['default_value'])) {
            $this->extractDefault($c, $column['default_value']);
        }

        return $c;
    }

    protected function findSchemaNames()
    {
        $sql = <<<SQL
SELECT user_name FROM sysuser WHERE user_name NOT IN ('SYS','dbo','EXTENV_MAIN','EXTENV_WORKER') and user_type IN (12,13,14)
SQL;

        return $this->selectColumn($sql);
    }

    /**
     * Returns all table names in the database.
     *
     * @param string $schema the schema of the tables. Defaults to empty string, meaning the current or default schema.
     *                       If not empty, the returned table names will be prefixed with the schema name.
     * @param bool   $include_views
     *
     * @return array all table names in the database.
     */
    protected function findTableNames($schema = '', $include_views = true)
    {
        if ($include_views) {
            $condition = "tabletype IN ('TABLE','VIEW','MAT VIEW')";
        } else {
            $condition = "tabletype = 'TABLE'";
        }
        $params = [];
        if (!empty($schema)) {
            $condition .= " AND creator = :schema";
            $params[':schema'] = $schema;
        }

        $sql = <<<SQL
SELECT creator, tname, tabletype, remarks FROM sys.syscatalog WHERE {$condition} ORDER BY tname
SQL;

        $rows = $this->connection->select($sql, $params);

        $defaultSchema = $this->getDefaultSchema();
        $addSchema = (!empty($schema) && ($defaultSchema !== $schema));

        $names = [];
        foreach ($rows as $row) {
            $row = (array)$row;
            $schemaName = isset($row['creator']) ? $row['creator'] : '';
            $tableName = isset($row['tname']) ? $row['tname'] : '';
            $isView = (false !== stripos($row['tabletype'], 'VIEW'));
            if ($addSchema) {
                $name = $schemaName . '.' . $tableName;
                $rawName = $this->quoteTableName($schemaName) . '.' . $this->quoteTableName($tableName);;
            } else {
                $name = $tableName;
                $rawName = $this->quoteTableName($tableName);
            }
            $settings = compact('schemaName', 'tableName', 'name', 'rawName', 'isView');
            $settings['description'] = $row['remarks'];
            $names[strtolower($name)] = new TableSchema($settings);
        }

        return $names;
    }

    /**
     * Returns all stored procedure names in the database.
     *
     * @param string $schema the schema of the stored procedures. Defaults to empty string, meaning the current or
     *                       default schema. If not empty, the returned stored procedure names will be prefixed with
     *                       the schema name.
     *
     * @return array all stored procedure names in the database.
     */
    protected function findProcedureNames($schema = '')
    {
        $defaultSchema = $this->getDefaultSchema();
        $params = [];
        $where = null;
        if (!empty($schema)) {
            $where = 'WHERE creator = :schema';
            $params[':schema'] = $schema;
        }

        $sql = <<<SQL
SELECT procname FROM SYS.SYSPROCS {$where} ORDER BY procname
SQL;

        $results = $this->connection->select($sql, $params);
        foreach ($results as $key => $name) {
            if (!empty($schema) && ($defaultSchema != $schema)) {
                $results[$key] = $schema . '.' . $name->procname;
            } else {
                $results[$key] = $name->procname;
            }
        }

        return $results;
    }

    /**
     * Returns all stored function names in the database.
     *
     * @param string $schema the schema of the stored function. Defaults to empty string, meaning the current or
     *                       default schema. If not empty, the returned stored function names will be prefixed with the
     *                       schema name.
     *
     * @return array all stored function names in the database.
     */
    protected function findFunctionNames($schema = '')
    {
//        $defaultSchema = $this->getDefaultSchema();
//        $params = [];
//        $where = null;
//        if (!empty($schema))
//        {
//            $where = 'WHERE creator = :schema';
//            $params[':schema'] = $schema;
//        }
//
//        $sql = <<<SQL
//SELECT procname FROM SYS.SYSPROCS {$where} ORDER BY procname
//SQL;
//
//        $results = $this->selectColumn($sql);
//        if (!empty($results) && !empty($schema) && ($defaultSchema != $schema)) {
//            foreach ($results as $key => $name) {
//                $results[$key] = $schema . '.' . $name;
//            }
//        }
//
//        return $results;
        return [];
    }

    /**
     * Builds a SQL statement for renaming a DB table.
     *
     * @param string $table   the table to be renamed. The name will be properly quoted by the method.
     * @param string $newName the new table name. The name will be properly quoted by the method.
     *
     * @return string the SQL statement for renaming a DB table.
     * @since 1.1.6
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
     * @since 1.1.6
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
        $definition = $this->getColumnType($definition);
        $sql =
            'ALTER TABLE ' .
            $this->quoteTableName($table) .
            ' ALTER COLUMN ' .
            $this->quoteColumnName($column) .
            ' ' .
            $this->getColumnType($definition);

        return $sql;
    }

    public function parseValueForSet($value, $field_info)
    {
        switch ($field_info->type) {
            case ColumnSchema::TYPE_BOOLEAN:
                $value = (filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0);
                break;
        }

        return $value;
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
     * @param string $dbType DB type
     */
    public function extractType(ColumnSchema &$column, $dbType)
    {
        parent::extractType($column, $dbType);

        $simpleType = strstr($dbType, '(', true);
        $simpleType = strtolower($simpleType ?: $dbType);

        switch ($simpleType) {
            case 'long varchar':
                $column->type = ColumnSchema::TYPE_TEXT;
                break;
            case 'long nvarchar':
                $column->type = ColumnSchema::TYPE_TEXT;
                $column->supportsMultibyte = true;
                break;
        }
    }

    /**
     * Extracts the default value for the column.
     * The value is typecasted to correct PHP type.
     *
     * @param mixed $defaultValue the default value obtained from metadata
     */
    public function extractDefault(ColumnSchema &$field, $defaultValue)
    {
        if ('autoincrement' === $defaultValue) {
            $field->defaultValue = null;
            $field->autoIncrement = true;
        } elseif (('(NULL)' === $defaultValue) || ('' === $defaultValue)) {
            $field->defaultValue = null;
        } elseif ($field->type === ColumnSchema::TYPE_BOOLEAN) {
            if ('1' === $defaultValue) {
                $field->defaultValue = true;
            } elseif ('0' === $defaultValue) {
                $field->defaultValue = false;
            } else {
                $field->defaultValue = null;
            }
        } elseif ($field->type === ColumnSchema::TYPE_TIMESTAMP) {
            $field->defaultValue = null;
            if ('current timestamp' === $defaultValue) {
                $field->defaultValue = ['expression' => 'CURRENT TIMESTAMP'];
                $field->type = ColumnSchema::TYPE_TIMESTAMP_ON_CREATE;
            } elseif ('timestamp' === $defaultValue) {
                $field->defaultValue = ['expression' => 'TIMESTAMP'];
                $field->type = ColumnSchema::TYPE_TIMESTAMP_ON_UPDATE;
            }
        } else {
            parent::extractDefault($field, str_replace(['(', ')', "'"], '', $defaultValue));
        }
    }

    /**
     * Extracts size, precision and scale information from column's DB type.
     * We do nothing here, since sizes and precisions have been computed before.
     *
     * @param string $dbType the column's DB type
     */
    public function extractLimit(ColumnSchema &$field, $dbType)
    {
    }

    /**
     * Converts the input value to the type that this column is of.
     *
     * @param mixed $value input value
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

    public function parseFieldForSelect(ColumnSchema $field, $as_quoted_string = false)
    {
        $name = ($as_quoted_string) ? $field->rawName : $field->name;
        $alias = $field->getName(true);
        if ($as_quoted_string && !ctype_alnum($alias)){
            $alias = '['.$alias.']';
        }
        switch ($field->dbType) {
//            case 'datetime':
//            case 'datetimeoffset':
//                return "(CONVERT(nvarchar(30), $name, 127)) AS $alias";
            case 'geometry':
            case 'geography':
            case 'hierarchyid':
                return "($name.ToString()) AS $alias";
            default :
                return parent::parseFieldForSelect($field, $as_quoted_string);
        }
    }

    /**
     * @param string $name
     * @param array  $params
     *
     * @return mixed
     * @throws \Exception
     */
    public function callProcedure($name, &$params)
    {
        $name = $this->quoteTableName($name);
        // Note that using the dblib driver doesn't allow binding of output parameters,
        // and also requires declaration prior to and selecting after to retrieve them.
        $paramStr = '';
        $pre = '';
        $post = '';
        $skip = 0;
        $bindings = [];
        foreach ($params as $key => $param) {
            $pName = (isset($param['name']) && !empty($param['name'])) ? $param['name'] : "p$key";
            $pValue = (isset($param['value'])) ? $param['value'] : null;

            if (!empty($paramStr)) {
                $paramStr .= ', ';
            }

            switch (strtoupper(strval(isset($param['param_type']) ? $param['param_type'] : 'IN'))) {
                case 'INOUT':
                    // with dblib driver you can't bind output parameters
                    $rType = $param['type'];
                    $pre .= "DECLARE @$pName $rType; SET @$pName = $pValue;";
                    $skip++;
                    $post .= "SELECT @$pName AS [$pName];";
                    $paramStr .= "@$pName OUTPUT";
                    break;

                case 'OUT':
                    // with dblib driver you can't bind output parameters
                    $rType = $param['type'];
                    $pre .= "DECLARE @$pName $rType;";
                    $post .= "SELECT @$pName AS [$pName];";
                    $paramStr .= "@$pName OUTPUT";
                    break;

                default:
                    $bindings[":$pName"] = $pValue;
                    $paramStr .= ":$pName";
                    break;
            }
        }

        $sql = "$pre EXEC $name $paramStr; $post";
        /** @type \PDOStatement $statement */
        $statement = $this->connection->getPdo()->prepare($sql);

        // do binding
        $this->bindValues($statement, $bindings);

        // support multiple result sets
        try {
            $statement->execute();
            $reader = new DataReader($statement);
        } catch (\Exception $e) {
            $errorInfo = $e instanceof \PDOException ? $e : null;
            $message = $e->getMessage();
            throw new \Exception($message, (int)$e->getCode(), $errorInfo);
        }
        $result = $reader->readAll();
        for ($i = 0; $i < $skip; $i++) {
            if ($reader->nextResult()) {
                $result = $reader->readAll();
            }
        }
        if ($reader->nextResult()) {
            // more data coming, make room
            $result = [$result];
            do {
                $temp = $reader->readAll();
                $keep = true;
                if (1 == count($temp)) {
                    $check = current($temp);
                    foreach ($params as &$param) {
                        $pName = (isset($param['name'])) ? $param['name'] : '';
                        if (isset($check[$pName])) {
                            $param['value'] = $check[$pName];
                            $keep = false;
                        }
                    }
                }
                if ($keep) {
                    $result[] = $temp;
                }
            } while ($reader->nextResult());

            // if there is only one data set, just return it
            if (1 == count($result)) {
                $result = $result[0];
            }
        }

        return $result;
    }

    /**
     * @param string $name
     * @param array  $params
     *
     * @throws \Exception
     * @return mixed
     */
    public function callFunction($name, &$params)
    {
        if (false === strpos($name, '.')) {
            // requires full name with schema here.
            $name = $this->getDefaultSchema() . '.' . $name;
        }
        $name = $this->quoteTableName($name);

        $bindings = [];
        foreach ($params as $key => $param) {
            $pName = (isset($param['name']) && !empty($param['name'])) ? ':' . $param['name'] : ":p$key";
            $pValue = isset($param['value']) ? $param['value'] : null;

            $bindings[$pName] = $pValue;
        }

        $paramStr = implode(',', array_keys($bindings));
        $sql = "SELECT $name($paramStr);";
        /** @type \PDOStatement $statement */
        $statement = $this->connection->getPdo()->prepare($sql);

        // do binding
        $this->bindValues($statement, $bindings);

        // Move to the next result and get results
        // support multiple result sets
        try {
            $statement->execute();
            $reader = new DataReader($statement);
        } catch (\Exception $e) {
            $errorInfo = $e instanceof \PDOException ? $e : null;
            $message = $e->getMessage();
            throw new \Exception($message, (int)$e->getCode(), $errorInfo);
        }
        $result = $reader->readAll();
        if ($reader->nextResult()) {
            // more data coming, make room
            $result = [$result];
            do {
                $result[] = $reader->readAll();
            } while ($reader->nextResult());
        }

        return $result;
    }
}