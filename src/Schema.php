<?php

declare(strict_types=1);
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Rabbit\DB\Mysql;

use Exception;
use Rabbit\DB\Constraint;
use Rabbit\DB\Expression;
use Rabbit\DB\TableSchema;
use Rabbit\DB\IndexConstraint;
use Rabbit\Base\Helper\ArrayHelper;
use Rabbit\DB\ConstraintFinderTrait;
use Rabbit\DB\ConstraintFinderInterface;
use Rabbit\Base\Exception\NotSupportedException;
use Rabbit\DB\ForeignKeyConstraint;

class Schema extends \Rabbit\DB\Schema implements ConstraintFinderInterface
{
    use ConstraintFinderTrait;

    public string $columnSchemaClass = ColumnSchema::class;
    protected string $builderClass = QueryBuilder::class;
    public static array $typeMap = [
        'tinyint' => self::TYPE_TINYINT,
        'bit' => self::TYPE_INTEGER,
        'smallint' => self::TYPE_SMALLINT,
        'mediumint' => self::TYPE_INTEGER,
        'int' => self::TYPE_INTEGER,
        'integer' => self::TYPE_INTEGER,
        'bigint' => self::TYPE_BIGINT,
        'float' => self::TYPE_FLOAT,
        'double' => self::TYPE_DOUBLE,
        'real' => self::TYPE_FLOAT,
        'decimal' => self::TYPE_DECIMAL,
        'numeric' => self::TYPE_DECIMAL,
        'tinytext' => self::TYPE_TEXT,
        'mediumtext' => self::TYPE_TEXT,
        'longtext' => self::TYPE_TEXT,
        'longblob' => self::TYPE_BINARY,
        'blob' => self::TYPE_BINARY,
        'text' => self::TYPE_TEXT,
        'varchar' => self::TYPE_STRING,
        'string' => self::TYPE_STRING,
        'char' => self::TYPE_CHAR,
        'datetime' => self::TYPE_DATETIME,
        'year' => self::TYPE_DATE,
        'date' => self::TYPE_DATE,
        'time' => self::TYPE_TIME,
        'timestamp' => self::TYPE_TIMESTAMP,
        'enum' => self::TYPE_STRING,
        'varbinary' => self::TYPE_BINARY,
        'json' => self::TYPE_JSON,
    ];
    protected string $tableQuoteCharacter = '`';
    protected string $columnQuoteCharacter = '`';
    private bool $_oldMysql;

    public function findUniqueIndexes(TableSchema $table): array
    {
        $sql = $this->getCreateTableSql($table);
        $uniqueIndexes = [];

        $regexp = '/UNIQUE KEY\s+\`(.+)\`\s*\((\`.+\`)+\)/mi';
        if (preg_match_all($regexp, $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $indexName = $match[1];
                $indexColumns = array_map('trim', explode('`,`', trim($match[2], '`')));
                $uniqueIndexes[$indexName] = $indexColumns;
            }
        }

        return $uniqueIndexes;
    }

    public function quoteValue(string $str): string
    {
        $slave = $this->db->getSlavePdo();
        if ($slave instanceof \PDO && ($value = $slave->quote($str)) !== false) {
            return $value;
        }

        // the driver doesn't support quote (e.g. oci)
        return "'" . addcslashes(str_replace("'", "''", $str), "\000\n\r\\\032") . "'";
    }

    protected function getCreateTableSql(TableSchema $table): string
    {
        $row = $this->db->createCommand('SHOW CREATE TABLE ' . $this->quoteTableName($table->fullName))->queryOne();
        if (isset($row['Create Table'])) {
            $sql = $row['Create Table'];
        } else {
            $row = array_values($row);
            $sql = $row[1];
        }

        return $sql;
    }

    public function createColumnSchemaBuilder(string $type, int|string|array $length = null): \Rabbit\DB\ColumnSchemaBuilder
    {
        return new ColumnSchemaBuilder($type, $length, $this->db);
    }

    protected function findTableNames(string $schema = ''): ?array
    {
        $sql = 'SHOW TABLES';
        if ($schema !== '') {
            $sql .= ' FROM ' . $this->quoteSimpleTableName($schema);
        }

        return $this->db->createCommand($sql)->queryColumn();
    }

    protected function loadTableSchema(string $name): ?TableSchema
    {
        $table = new TableSchema();
        $this->resolveTableNames($table, $name);

        if ($this->findColumns($table)) {
            $this->findConstraints($table);
            return $table;
        }

        return null;
    }

    protected function resolveTableNames(TableSchema $table, string $name): void
    {
        $parts = explode('.', str_replace('`', '', $name));
        if (isset($parts[1])) {
            $table->schemaName = $parts[0];
            $table->name = $parts[1];
            $table->fullName = $table->schemaName . '.' . $table->name;
        } else {
            $table->fullName = $table->name = $parts[0];
        }
    }

    protected function findColumns(TableSchema $table): bool
    {
        $sql = 'SHOW FULL COLUMNS FROM ' . $this->quoteTableName($table->fullName);
        try {
            $columns = $this->db->createCommand($sql)->queryAll();
        } catch (Exception $e) {
            $previous = $e->getPrevious();
            if ($previous instanceof \PDOException && strpos($previous->getMessage(), 'SQLSTATE[42S02') !== false) {
                // table does not exist
                // https://dev.mysql.com/doc/refman/5.5/en/error-messages-server.html#error_er_bad_table_error
                return false;
            }
            throw $e;
        }
        foreach ($columns as $info) {
            if ($this->db->getSlavePdo()->getAttribute(\PDO::ATTR_CASE) !== \PDO::CASE_LOWER) {
                $info = array_change_key_case($info, CASE_LOWER);
            }
            $column = $this->loadColumnSchema($info);
            $table->columns[$column->name] = $column;
            if ($column->isPrimaryKey) {
                $table->primaryKey[] = $column->name;
                if ($column->autoIncrement) {
                    $table->sequenceName = '';
                }
            }
        }

        return true;
    }

    protected function loadColumnSchema(array $info): \Rabbit\DB\ColumnSchema
    {
        $column = $this->createColumnSchema();

        $column->name = $info['field'];
        $column->allowNull = $info['null'] === 'YES';
        $column->isPrimaryKey = strpos($info['key'], 'PRI') !== false;
        $column->autoIncrement = stripos($info['extra'], 'auto_increment') !== false;
        $column->comment = $info['comment'];

        $column->dbType = $info['type'];
        $column->unsigned = stripos($column->dbType, 'unsigned') !== false;

        $column->type = self::TYPE_STRING;
        if (preg_match('/^(\w+)(?:\(([^\)]+)\))?/', $column->dbType, $matches)) {
            $type = strtolower($matches[1]);
            if (isset(self::$typeMap[$type])) {
                $column->type = self::$typeMap[$type];
            }
            if (!empty($matches[2])) {
                if ($type === 'enum') {
                    preg_match_all("/'[^']*'/", $matches[2], $values);
                    foreach ($values[0] as $i => $value) {
                        $values[$i] = trim($value, "'");
                    }
                    $column->enumValues = $values;
                } else {
                    $values = explode(',', $matches[2]);
                    $column->size = $column->precision = (int)$values[0];
                    if (isset($values[1])) {
                        $column->scale = (int)$values[1];
                    }
                    if ($column->size === 1 && $type === 'bit') {
                        $column->type = 'boolean';
                    } elseif ($type === 'bit') {
                        if ($column->size > 32) {
                            $column->type = 'bigint';
                        } elseif ($column->size === 32) {
                            $column->type = 'integer';
                        }
                    }
                }
            }
        }

        $column->phpType = $this->getColumnPhpType($column);

        if (!$column->isPrimaryKey) {
            if (($column->type === 'timestamp' || $column->type === 'datetime') && $info['default'] === 'CURRENT_TIMESTAMP') {
                $column->defaultValue = new Expression('CURRENT_TIMESTAMP');
            } elseif (isset($type) && $type === 'bit') {
                $column->defaultValue = bindec(trim($info['default'], 'b\''));
            } else {
                $column->defaultValue = $column->phpTypecast($info['default']);
            }
        }

        return $column;
    }

    protected function findConstraints(TableSchema $table): void
    {
        $sql = <<<'SQL'
SELECT
    kcu.constraint_name,
    kcu.column_name,
    kcu.referenced_table_name,
    kcu.referenced_column_name
FROM information_schema.referential_constraints AS rc
JOIN information_schema.key_column_usage AS kcu ON
    (
        kcu.constraint_catalog = rc.constraint_catalog OR
        (kcu.constraint_catalog IS NULL AND rc.constraint_catalog IS NULL)
    ) AND
    kcu.constraint_schema = rc.constraint_schema AND
    kcu.constraint_name = rc.constraint_name
WHERE rc.constraint_schema = database() AND kcu.table_schema = database()
AND rc.table_name = ? AND kcu.table_name = ?
SQL;

        try {
            $rows = $this->db->createCommand(
                $sql,
                [$table->name, $table->name]
            )->queryAll();
            $constraints = [];

            foreach ($rows as $row) {
                $constraints[$row['constraint_name']]['referenced_table_name'] = $row['referenced_table_name'];
                $constraints[$row['constraint_name']]['columns'][$row['column_name']] = $row['referenced_column_name'];
            }

            $table->foreignKeys = [];
            foreach ($constraints as $name => $constraint) {
                $table->foreignKeys[$name] = [
                    $constraint['referenced_table_name'],
                    ...$constraint['columns']
                ];
            }
        } catch (Exception $e) {
            $previous = $e->getPrevious();
            if (!$previous instanceof \PDOException || strpos($previous->getMessage(), 'SQLSTATE[42S02') === false) {
                throw $e;
            }

            // table does not exist, try to determine the foreign keys using the table creation sql
            $sql = $this->getCreateTableSql($table);
            $regexp = '/FOREIGN KEY\s+\(([^\)]+)\)\s+REFERENCES\s+([^\(^\s]+)\s*\(([^\)]+)\)/mi';
            if (preg_match_all($regexp, $sql, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $fks = array_map('trim', explode(',', str_replace('`', '', $match[1])));
                    $pks = array_map('trim', explode(',', str_replace('`', '', $match[3])));
                    $constraint = [str_replace('`', '', $match[2])];
                    foreach ($fks as $k => $name) {
                        $constraint[$name] = $pks[$k];
                    }
                    $table->foreignKeys[md5(serialize($constraint))] = $constraint;
                }
                $table->foreignKeys = array_values($table->foreignKeys);
            }
        }
    }

    protected function loadTablePrimaryKey(string $tableName): ?Constraint
    {
        return $this->loadTableConstraints($tableName, 'primaryKey');
    }

    private function loadTableConstraints(string $tableName, string $returnType): null|array|Constraint
    {
        static $sql = <<<'SQL'
SELECT
    `kcu`.`CONSTRAINT_NAME` AS `name`,
    `kcu`.`COLUMN_NAME` AS `column_name`,
    `tc`.`CONSTRAINT_TYPE` AS `type`,
    CASE
        WHEN ? IS NULL AND `kcu`.`REFERENCED_TABLE_SCHEMA` = DATABASE() THEN NULL
        ELSE `kcu`.`REFERENCED_TABLE_SCHEMA`
    END AS `foreign_table_schema`,
    `kcu`.`REFERENCED_TABLE_NAME` AS `foreign_table_name`,
    `kcu`.`REFERENCED_COLUMN_NAME` AS `foreign_column_name`,
    `rc`.`UPDATE_RULE` AS `on_update`,
    `rc`.`DELETE_RULE` AS `on_delete`,
    `kcu`.`ORDINAL_POSITION` AS `position`
FROM
    `information_schema`.`KEY_COLUMN_USAGE` AS `kcu`,
    `information_schema`.`REFERENTIAL_CONSTRAINTS` AS `rc`,
    `information_schema`.`TABLE_CONSTRAINTS` AS `tc`
WHERE
    `kcu`.`TABLE_SCHEMA` = COALESCE(?, DATABASE()) AND `kcu`.`CONSTRAINT_SCHEMA` = `kcu`.`TABLE_SCHEMA` AND `kcu`.`TABLE_NAME` = ?
    AND `rc`.`CONSTRAINT_SCHEMA` = `kcu`.`TABLE_SCHEMA` AND `rc`.`TABLE_NAME` = ? AND `rc`.`CONSTRAINT_NAME` = `kcu`.`CONSTRAINT_NAME`
    AND `tc`.`TABLE_SCHEMA` = `kcu`.`TABLE_SCHEMA` AND `tc`.`TABLE_NAME` = ? AND `tc`.`CONSTRAINT_NAME` = `kcu`.`CONSTRAINT_NAME` AND `tc`.`CONSTRAINT_TYPE` = 'FOREIGN KEY'
UNION
SELECT
    `kcu`.`CONSTRAINT_NAME` AS `name`,
    `kcu`.`COLUMN_NAME` AS `column_name`,
    `tc`.`CONSTRAINT_TYPE` AS `type`,
    NULL AS `foreign_table_schema`,
    NULL AS `foreign_table_name`,
    NULL AS `foreign_column_name`,
    NULL AS `on_update`,
    NULL AS `on_delete`,
    `kcu`.`ORDINAL_POSITION` AS `position`
FROM
    `information_schema`.`KEY_COLUMN_USAGE` AS `kcu`,
    `information_schema`.`TABLE_CONSTRAINTS` AS `tc`
WHERE
    `kcu`.`TABLE_SCHEMA` = COALESCE(?, DATABASE()) AND `kcu`.`TABLE_NAME` = ?
    AND `tc`.`TABLE_SCHEMA` = `kcu`.`TABLE_SCHEMA` AND `tc`.`TABLE_NAME` = ? AND `tc`.`CONSTRAINT_NAME` = `kcu`.`CONSTRAINT_NAME` AND `tc`.`CONSTRAINT_TYPE` IN ('PRIMARY KEY', 'UNIQUE')
ORDER BY `position` ASC
SQL;

        $resolvedName = $this->resolveTableName($tableName);
        $constraints = $this->db->createCommand($sql, [
            $resolvedName->schemaName,
            $resolvedName->schemaName,
            $resolvedName->name,
            $resolvedName->name,
            $resolvedName->name,
            $resolvedName->schemaName,
            $resolvedName->name,
            $resolvedName->name,
        ])->queryAll();
        $constraints = $this->normalizePdoRowKeyCase($constraints, true);
        $constraints = ArrayHelper::index($constraints, null, ['type', 'name']);
        $result = [
            'primaryKey' => null,
            'foreignKeys' => [],
            'uniques' => [],
        ];
        foreach ($constraints as $type => $names) {
            foreach ($names as $name => $constraint) {
                switch ($type) {
                    case 'PRIMARY KEY':
                        $result['primaryKey'] = create([
                            'class' => Constraint::class,
                            'columnNames' => ArrayHelper::getColumn($constraint, 'column_name'),
                        ]);
                        break;
                    case 'FOREIGN KEY':
                        $result['foreignKeys'][] = create([
                            'class' => ForeignKeyConstraint::class,
                            'name' => $name,
                            'columnNames' => ArrayHelper::getColumn($constraint, 'column_name'),
                            'foreignSchemaName' => $constraint[0]['foreign_table_schema'],
                            'foreignTableName' => $constraint[0]['foreign_table_name'],
                            'foreignColumnNames' => ArrayHelper::getColumn($constraint, 'foreign_column_name'),
                            'onDelete' => $constraint[0]['on_delete'],
                            'onUpdate' => $constraint[0]['on_update'],
                        ]);
                        break;
                    case 'UNIQUE':
                        $result['uniques'][] = create([
                            'class' => Constraint::class,
                            'name' => $name,
                            'columnNames' => ArrayHelper::getColumn($constraint, 'column_name'),
                        ]);
                        break;
                }
            }
        }
        foreach ($result as $type => $data) {
            $this->setTableMetadata($tableName, $type, $data);
        }

        return $result[$returnType];
    }

    protected function resolveTableName(string $name): TableSchema
    {
        $resolvedName = new TableSchema();
        $parts = explode('.', str_replace('`', '', $name));
        if (isset($parts[1])) {
            $resolvedName->schemaName = $parts[0];
            $resolvedName->name = $parts[1];
        } else {
            $resolvedName->schemaName = $this->defaultSchema;
            $resolvedName->name = $name;
        }
        $resolvedName->fullName = ($resolvedName->schemaName !== $this->defaultSchema ? $resolvedName->schemaName . '.' : '') . $resolvedName->name;
        return $resolvedName;
    }

    protected function loadTableForeignKeys(string $tableName): array
    {
        return $this->loadTableConstraints($tableName, 'foreignKeys');
    }

    protected function loadTableIndexes(string $tableName): array
    {
        static $sql = <<<'SQL'
SELECT
    `s`.`INDEX_NAME` AS `name`,
    `s`.`COLUMN_NAME` AS `column_name`,
    `s`.`NON_UNIQUE` ^ 1 AS `index_is_unique`,
    `s`.`INDEX_NAME` = 'PRIMARY' AS `index_is_primary`
FROM `information_schema`.`STATISTICS` AS `s`
WHERE `s`.`TABLE_SCHEMA` = COALESCE(?, DATABASE()) AND `s`.`INDEX_SCHEMA` = `s`.`TABLE_SCHEMA` AND `s`.`TABLE_NAME` = ?
ORDER BY `s`.`SEQ_IN_INDEX` ASC
SQL;

        $resolvedName = $this->resolveTableName($tableName);
        $indexes = $this->db->createCommand($sql, [
            $resolvedName->schemaName,
            $resolvedName->name,
        ])->queryAll();
        $indexes = $this->normalizePdoRowKeyCase($indexes, true);
        $indexes = ArrayHelper::index($indexes, null, 'name');
        $result = [];
        foreach ($indexes as $name => $index) {
            $result[] = create([
                'class' => IndexConstraint::class,
                'isPrimary' => (bool)$index[0]['index_is_primary'],
                'isUnique' => (bool)$index[0]['index_is_unique'],
                'name' => $name !== 'PRIMARY' ? $name : null,
                'columnNames' => ArrayHelper::getColumn($index, 'column_name'),
            ]);
        }

        return $result;
    }

    protected function loadTableUniques(string $tableName): ?array
    {
        return $this->loadTableConstraints($tableName, 'uniques');
    }

    protected function loadTableChecks(string $tableName): ?array
    {
        throw new NotSupportedException('MySQL does not support check constraints.');
    }
    protected function loadTableDefaultValues(string $tableName): ?array
    {
        throw new NotSupportedException('MySQL does not support default value constraints.');
    }

    protected function isOldMysql(): bool
    {
        if ($this->_oldMysql === null) {
            $version = $this->db->getSlavePdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);
            $this->_oldMysql = version_compare($version, '5.1', '<=');
        }

        return $this->_oldMysql;
    }
}
