<?php

namespace diecoding\seeder;

use Faker\Factory;
use Yii;
use yii\base\NotSupportedException;
use yii\db\Exception;
use yii\db\Migration;
use yii\helpers\ArrayHelper;

/**
 * Class TableSeeder
 * 
 * @package diecoding\seeder
 *
 * @link [sugeng-sulistiyawan.github.io](sugeng-sulistiyawan.github.io)
 * @author Sugeng Sulistiyawan <sugeng.sulistiyawan@gmail.com>
 * @copyright Copyright (c) 2023
 */
abstract class TableSeeder extends Migration
{
    /** @var \Faker\Generator|Factory */
    public $faker;

    /** @var bool `true` for truncate tables before run seeder, default `true` */
    public $truncateTable = true;

    /** @var string|null if `null` use `Yii::$app->language`, default `null` */
    public $locale;

    /** @var array */
    protected $insertedColumns = [];

    /** @var array */
    protected $batch = [];

    /**
     * TableSeeder constructor.
     * 
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        if ($this->locale === null) {
            $this->locale = str_replace('-', '_', Yii::$app->language);
        }
        $this->faker = Factory::create($this->locale);

        parent::__construct($config);
    }

    /**
     * TableSeeder destructor.
     * 
     * @throws Exception
     * @throws NotSupportedException
     */
    public function __destruct()
    {
        if ($this->truncateTable) {
            $this->disableForeignKeyChecks();
            foreach ($this->batch as $table => $values) {
                $this->truncateTable($table);
            }
            $this->enableForeignKeyChecks();
        }

        foreach ($this->batch as $table => $values) {
            $total = 0;
            foreach ($values as $columns => $rows) {
                $total += count($rows);
                parent::batchInsert($table, explode(',', $columns), $rows);
            }
            echo "      $total row" . ($total > 1 ? 's' : null) . " inserted in $table" . "\n";
        }

        $this->checkMissingColumns($this->insertedColumns);
    }

    /**
     * @return void
     * @throws Exception
     * @throws NotSupportedException
     */
    public function disableForeignKeyChecks()
    {
        $this->db->createCommand()->checkIntegrity(false)->execute();
    }

    /**
     * @return void
     * @throws Exception
     * @throws NotSupportedException
     */
    public function enableForeignKeyChecks()
    {
        $this->db->createCommand()->checkIntegrity(true)->execute();
    }

    /**
     * Creates and executes an INSERT SQL statement.
     * The method will properly escape the column names, and bind the values to be inserted.
     * @param string $table the table that new rows will be inserted into.
     * @param array $columns the column data (name => value) to be inserted into the table.
     */
    public function insert($table, $columns)
    {
        $this->insertedColumns[$table] = ArrayHelper::merge(
            array_keys($columns),
            isset($this->insertedColumns[$table]) ? $this->insertedColumns[$table] : []
        );

        $this->batch[$table][implode(',', array_keys($columns))][] = array_values($columns);
    }

    /**
     * Creates and executes a batch INSERT SQL statement.
     * The method will properly escape the column names, and bind the values to be inserted.
     * @param string $table the table that new rows will be inserted into.
     * @param array $columns the column names.
     * @param array $rows the rows to be batch inserted into the table
     */
    public function batchInsert($table, $columns, $rows)
    {
        $this->insertedColumns[$table] = ArrayHelper::merge(
            $columns,
            isset($this->insertedColumns[$table]) ? $this->insertedColumns[$table] : []
        );

        foreach ($rows as $row) {
            $this->batch[$table][implode(',', $columns)][] = $row;
        }
    }

    /**
     * @return static
     */
    public static function create()
    {
        return new static;
    }

    /**
     * @return void
     */
    abstract public function run();

    /**
     * Check missing column
     *
     * @param array $insertedColumns
     * @return void
     */
    protected function checkMissingColumns($insertedColumns)
    {
        $missingColumns = [];

        foreach ($insertedColumns as $table => $columns) {
            $tableColumns = $this->db->getTableSchema($table)->columns;

            foreach ($tableColumns as $tableColumn) {
                if (!$tableColumn->autoIncrement && !in_array($tableColumn->name, $columns, true)) {
                    $missingColumns[$table][] = [$tableColumn->name, $tableColumn->dbType];
                }
            }
        }

        if (count($missingColumns)) {
            echo "    > #" . str_pad(' MISSING COLUMNS ', 70, '#', STR_PAD_BOTH) . "#\n";
            foreach ($missingColumns as $table => $columns) {
                echo "    > #" . str_pad('', 70, ' ') . "#\n";
                echo "    > #" . str_pad("    TABLE: {$table}", 70, ' ') . "#\n";
                echo "    > # " . str_pad('', 68, '-') . " #\n";
                foreach ($columns as [$tableColumn, $type]) {
                    echo "    > #" . str_pad("    - $tableColumn => $type", 70, ' ') . "#\n";
                }
                echo "    > # " . str_pad('', 68, '-') . " #\n";
                echo "    > #" . str_pad('', 70, ' ') . "#\n";
            }
            echo "    > #" . str_pad('', 70, '#') . "#\n";
        }
    }
}
