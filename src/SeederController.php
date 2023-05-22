<?php

namespace diecoding\seeder;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\ColumnSchema;
use yii\helpers\ArrayHelper;
use yii\helpers\Console;
use yii\helpers\FileHelper;
use yii\helpers\Inflector;
use yii\helpers\StringHelper;

/**
 * Class SeederController
 * 
 * @package diecoding\seeder
 * 
 * @link [sugeng-sulistiyawan.github.io](sugeng-sulistiyawan.github.io)
 * @author Sugeng Sulistiyawan <sugeng.sulistiyawan@gmail.com>
 * @copyright Copyright (c) 2023
 */
class SeederController extends Controller
{
    /** @var string the default command action. */
    public $defaultAction = 'seed';

    /** @var string seeder path, support path alias */
    public $seederPath = '@console/seeder';

    /** @var string seeder namespace */
    public $seederNamespace = 'console\seeder';

    /** 
     * @var string this class look like `$this->seederNamespace\Seeder` 
     * default seeder class run if no class selected, 
     * must instance of `\diecoding\seeder\TableSeeder` 
     */
    public $defaultSeederClass = 'Seeder';

    /** @var string tables path, support path alias */
    public $tablesPath = '@console/seeder/tables';

    /** @var string seeder table namespace */
    public $tableSeederNamespace = 'console\seeder\tables';

    /** @var string model namespace */
    public $modelNamespace = 'common\models';

    /** @var string path view template table seeder, support path alias */
    public $templateSeederFile = '@vendor/diecoding/yii2-seeder/src/views/Seeder.php';

    /** @var string path view template seeder, support path alias */
    public $templateTableFile = '@vendor/diecoding/yii2-seeder/src/views/TableSeeder.php';

    /** @var bool run on production or Seeder on YII_ENV === 'prod' */
    public $runOnProd;

    /** @var \yii\db\ActiveRecord */
    protected $model = null;

    /**
     * {@inheritdoc}
     */
    public function options($actionID)
    {
        return ['runOnProd'];
    }

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        $this->seederPath         = (string) Yii::getAlias($this->seederPath);
        $this->tablesPath         = (string) Yii::getAlias($this->tablesPath);
        $this->templateSeederFile = (string) Yii::getAlias($this->templateSeederFile);
        $this->templateTableFile  = (string) Yii::getAlias($this->templateTableFile);
    }

    /**
     * Seed action
     *
     * @param string $name
     * @return int ExitCode::OK
     */
    public function actionSeed($name = "")
    {
        if (YII_ENV_PROD && !$this->runOnProd) {
            $this->stdout("YII_ENV is set to 'prod'.\nUse seeder is not possible on production systems. use '--runOnProd' to ignore it.\n");
            return ExitCode::OK;
        }

        $explode  = explode(':', $name);
        $name     = $explode[0];
        $function = $explode[1] ?? null;

        if ($name) {
            $modelClass     = str_replace('/', '\\', $name);
            $explode        = explode('\\', $modelClass);
            $modelName      = Inflector::camelize(array_pop($explode));
            $modelNamespace = implode('\\', $explode);

            $modelClass    = $modelNamespace ? "{$modelNamespace}\\{$modelName}" : $modelName;
            $func          = $function ?? 'run';
            $seederClasses = [
                $modelClass,
                "{$modelClass}TableSeeder",
                "{$this->seederNamespace}\\{$modelClass}",
                "{$this->seederNamespace}\\{$modelClass}TableSeeder",
                "{$this->tableSeederNamespace}\\{$modelClass}",
                "{$this->tableSeederNamespace}\\{$modelClass}TableSeeder",
                $name,
                "{$name}TableSeeder",
                "{$this->seederNamespace}\\{$name}",
                "{$this->seederNamespace}\\{$name}TableSeeder",
                "{$this->tableSeederNamespace}\\{$name}",
                "{$this->tableSeederNamespace}\\{$name}TableSeeder",
            ];

            $this->runMethod($seederClasses, $func, $name);
        } else if (($defaultSeeder = $this->getDefaultSeeder()) !== null) {
            $defaultSeeder->run();
        }

        return ExitCode::OK;
    }

    /**
     * Create a new seeder.
     *
     * This command create a new seeder using the available seeder template.
     * After using this command, developers should modify the created seeder
     * skeleton by filling up the actual seeder logic.
     *
     * ```shell
     * yii seeder/create model_name
     * ```
     * or
     * ```shell
     * yii seeder/create modelName
     * ```
     * or
     * ```shell
     * yii seeder/create model-name
     * ```
     * 
     * @see https://www.yiiframework.com/doc/api/2.0/yii-helpers-inflector#camelize()-detail
     *
     * For example:
     *
     * ```shell
     * yii seeder/create user
     * ```
     * or
     * ```shell
     * yii seeder/create example/user
     * ```
     * if User's Model directory is "common\models\example\User", this default use `$modelNamespace` configuration
     * 
     * or you can use full path of your class name
     * 
     * ```shell
     * yii seeder/create \app\models\User
     * ```
     * or
     * ```shell
     * yii seeder/create \backend\modules\example\models\User
     * ```
     *
     * @param string $modelName the name of the new seeder or class
     *
     * @return int ExitCode::OK
     */
    public function actionCreate($modelName)
    {
        $modelName = str_replace('/', '\\', $modelName);

        $this->model = $this->getClass($modelName);
        if ($this->model === null) {
            $modelNamespace = $this->modelNamespace;
            $file           = $this->normalizeFile($modelNamespace, $modelName);

            $this->model = $this->getClass($file);
            if ($this->model === null) {
                $this->printError("Class {$file} not exists.\n");

                return ExitCode::OK;
            }
        }

        $modelClass = $this->model::class;
        $className  = StringHelper::basename($modelClass) . 'TableSeeder';
        $file       = "{$this->tablesPath}/{$className}.php";
        if ($this->confirm("Create new seeder '{$file}'?")) {
            $content = $this->renderFile($this->templateTableFile, [
                'className' => $className,
                'namespace' => $this->tableSeederNamespace,
                'model'     => $this->model,
                'fields'    => $this->generateFields(),
            ]);
            FileHelper::createDirectory($this->tablesPath);

            if (!file_exists($file) || $this->confirm("\n'{$file}' already exists, overwrite?\nAll data will be lost irreversibly!")) {
                file_put_contents($file, $content, LOCK_EX);
                $this->stdout("New seeder created successfully.\n", Console::FG_GREEN);
            }
        }

        return ExitCode::OK;
    }

    /**
     * @param string $path
     * @return \yii\db\ActiveRecord|null
     */
    protected function getClass($path)
    {
        if (class_exists($path)) {
            return new $path;
        }

        return null;
    }

    /**
     * @param string $message
     * @param bool $print
     * @return void
     */
    protected function printError($message, $print = true)
    {
        if ($print) {
            $this->stdout($message, Console::FG_RED);
        }
    }

    /**
     * Generate fields for views template
     *
     * @return object
     */
    protected function generateFields()
    {
        $modelClass     = $this->model::class;
        $modelNamespace = str_replace('/', '\\', StringHelper::dirname($modelClass));

        $schema      = $this->model->tableSchema;
        $columns     = $schema->columns;
        $foreignKeys = $schema->foreignKeys;
        $fields      = [];

        foreach ($foreignKeys as $fk_str => $foreignKey) {
            unset($foreignKeys[$fk_str]);
            $table  = array_shift($foreignKey);
            $column = array_keys($foreignKey)[0];

            $model = $this->getClass(($class = $modelNamespace . '\\' . Inflector::camelize($table)));
            $foreignKeys[$column] = $model;

            $this->printError("Class {$class} not exists. Foreign Key for '$column' column will be ignored and a common column will be generated.\n", $model === null);
        }

        foreach ($columns as $column => $data) {
            /** @var ColumnSchema $data */
            if ($data->autoIncrement) {
                continue;
            }

            $foreign = $ref_table_id = null;
            if (isset($foreignKeys[$column])) {
                $foreign      = $foreignKeys[$column];
                $ref_table_id = $foreign->tableSchema->primaryKey[0];
            }

            $faker = $this->generateFakerField($data->name) ?? $this->generateFakerField($data->type);
            if ($data->dbType === 'tinyint(1)') {
                $faker = 'boolean';
            }

            $fields[$column] = (object) [
                'faker'        => $faker,
                'foreign'      => $foreign,
                'ref_table_id' => $ref_table_id
            ];
        }

        return (object) $fields;
    }

    /**
     * Generate Faker Field Name
     *
     * @param string $key
     * @return string
     */
    protected function generateFakerField($key)
    {
        $faker = [
            'full_name'     => 'name',
            'name'          => 'name',
            'short_name'    => 'firstName',
            'first_name'    => 'firstName',
            'nickname'      => 'firstName',
            'last_name'     => 'lastName',
            'description'   => 'realText()',
            'company'       => 'company',
            'business_name' => 'company',
            'email'         => 'email',
            'phone'         => 'phoneNumber',
            'hp'            => 'phoneNumber',
            'start_date'    => 'dateTime()->format("Y-m-d H:i:s")',
            'end_date'      => 'dateTime()->format("Y-m-d H:i:s")',
            'created_at'    => 'dateTime()->format("Y-m-d H:i:s")',
            'updated_at'    => 'dateTime()->format("Y-m-d H:i:s")',
            'token'         => 'uuid',
            'duration'      => 'numberBetween()',

            'integer'       => 'numberBetween(0, 10)',
            'smallint'      => 'numberBetween(0, 10)',
            'tinyint'       => 'numberBetween(0, 10)',
            'mediumint'     => 'numberBetween(0, 10)',
            'int'           => 'numberBetween(0, 10)',
            'bigint'        => 'numberBetween()',
            'date'          => 'date()',
            'datetime'      => 'dateTime()->format("Y-m-d H:i:s")',
            'timestamp'     => 'dateTime()->format("Y-m-d H:i:s")',
            'year'          => 'year()',
            'time'          => 'time()',
        ];

        return ArrayHelper::getValue($faker, $key, 'word');
    }

    /**
     * Get Default Seeder Class if no class selected
     *
     * @return TableSeeder|null
     */
    protected function getDefaultSeeder()
    {
        $defaultSeederClass = "{$this->seederNamespace}\\{$this->defaultSeederClass}";
        $defaultSeederFile = "{$defaultSeederClass}.php";

        if (!class_exists($defaultSeederClass) || !file_exists($defaultSeederFile)) {
            FileHelper::createDirectory($this->seederPath);
            $content = $this->renderFile($this->templateSeederFile, [
                'namespace' => $this->seederNamespace,
            ]);

            $this->stdout("\nClass {$defaultSeederClass} created in {$defaultSeederFile}.\n");

            file_put_contents($defaultSeederFile, $content, LOCK_EX);
        }

        if (($seederClass = new $defaultSeederClass) instanceof TableSeeder) {
            /** @var TableSeeder $seederClass */
            return $seederClass;
        }

        return null;
    }

    /**
     * Execute function
     *
     * @param array|string $class
     * @param string $method
     * @param string|null $defaultClass
     * @return void
     */
    protected function runMethod($class, $method = 'run', $defaultClass = null)
    {
        $count = 0;
        foreach ((array) $class as $seederClass) {
            if ($seeder = $this->getClass($seederClass)) {
                $seeder->{$method}();
                $count++;
                break;
            }
        }
        $defaultClass = $defaultClass ?? $class[0];
        $this->printError("Class {$defaultClass} not exists.\n", $count === 0);
    }

    /**
     * @param string $modelNamespace
     * @param string $modelName
     * @return string
     */
    private function normalizeFile($modelNamespace, $modelName)
    {
        $file = "{$modelNamespace}\\{$modelName}";
        if (strpos($modelName, '\\') !== false) {
            $explode         = explode('\\', $modelName);
            $modelName       = array_pop($explode);
            $modelNamespace .= '\\' . implode('\\', $explode);

            $file = "{$modelNamespace}\\{$modelName}";
        }
        if (!class_exists($file)) {
            $modelName = Inflector::camelize($modelName);
            $file      = "{$modelNamespace}\\{$modelName}";
        }

        return $file;
    }
}
