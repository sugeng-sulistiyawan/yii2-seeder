<?php

namespace diecoding\seeder;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
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
    public $templateSeederFile = '@diecoding/seeder/views/Seeder.php';

    /** @var string path view template seeder, support path alias */
    public $templateTableFile = '@diecoding/seeder/views/TableSeeder.php';

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

        $this->seederPath         = Yii::getAlias($this->seederPath);
        $this->tablesPath         = Yii::getAlias($this->tablesPath);
        $this->templateSeederFile = Yii::getAlias($this->templateSeederFile);
        $this->templateTableFile  = Yii::getAlias($this->templateTableFile);
    }

    /**
     * Seed action
     *
     * @param string|null $name
     * @return int ExitCode::OK
     */
    public function actionSeed($name = null)
    {
        if (YII_ENV_PROD && !$this->runOnProd) {
            $this->stdout("YII_ENV is set to 'prod'.\nUse seeder is not possible on production systems. use '--runOnProd' to ignore it.\n");
            return ExitCode::OK;
        }

        $explode  = explode(':', $name);
        $name     = $explode[0];
        $function = $explode[1] ?? null;

        if ($name) {
            $func = $function ?? 'run';

            $seederClasses = [
                $name,
                "{$name}TableSeeder",
                "{$this->tableSeederNamespace}\\{$name}TableSeeder",
                "{$this->tableSeederNamespace}\\{$name}",
            ];

            foreach ($seederClasses as $seederClass) {
                if ($seeder = $this->getClass($seederClass)) {
                    $seeder->{$func}();
                    break;
                }
            }
        } else if ($defaultSeeder = $this->getDefaultSeeder()) {
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
        if (!class_exists($modelName)) {
            $this->model = $this->getClass($modelName);
        } else {
            $modelNamespace = $this->modelNamespace;

            if (strpos($modelName, '/')) {
                $explode         = explode('/', $modelName);
                $modelName       = array_pop($explode);
                $modelNamespace .= '\\' . implode('\\', $explode);

                $file = "{$modelNamespace}\\{$modelName}";
                if (!class_exists($file)) {
                    $modelName = Inflector::camelize($modelName);
                    $file      = "{$modelNamespace}\\{$modelName}";
                }
            } else {
                $file = "{$modelNamespace}\\{$modelName}";
                if (!class_exists($file)) {
                    $modelName = Inflector::camelize($modelName);
                    $file      = "{$modelNamespace}\\{$modelName}";
                }
            }

            $this->model = $this->getClass($file);
        }

        if ($this->model === null) {
            return ExitCode::OK;
        }

        $className = StringHelper::basename($this->model::class) . 'TableSeeder';
        $file      = "{$this->tablesPath}/{$className}.php";
        if ($this->confirm("Create new seeder '{$file}'?")) {
            $content = $this->renderFile($this->templateTableFile, [
                'className' => $className,
                'namespace' => $this->tableSeederNamespace,
                'model'     => $this->model,
                'fields'    => $this->generateFields(),
            ]);
            FileHelper::createDirectory($this->tablesPath);

            if (!file_exists($file) || $this->confirm("\n'{$className}' already exists, overwrite?\nAll data will be lost irreversibly!")) {
                file_put_contents($file, $content, LOCK_EX);
                $this->stdout("New seeder created successfully.\n", Console::FG_GREEN);
            }
        }

        return ExitCode::OK;
    }

    /**
     * @param string $path
     * @param string $eol
     * @return \stdClass|\yii\db\ActiveRecord|null
     */
    protected function getClass($path, $eol = PHP_EOL)
    {
        if (class_exists($path)) {
            $this->stdout("Use {$path} class. {$eol}");
            return new $path;
        }

        $this->stdout("Class {$path} not exists. {$eol}");
        return null;
    }

    /**
     * Generate fields for views template
     *
     * @return \stdClass
     */
    public function generateFields()
    {
        $schema      = $this->model->tableSchema;
        $columns     = $schema->columns;
        $foreignKeys = $schema->foreignKeys;
        $fields      = [];

        foreach ($foreignKeys as $fk_str => $foreignKey) {
            unset($foreignKeys[$fk_str]);
            $table  = array_shift($foreignKey);
            $column = array_keys($foreignKey)[0];

            $errorMsg = "Foreign Key for '$column' column will be ignored and a common column will be generated.\n";

            $model = $this->getClass($this->modelNamespace . '\\' . Inflector::camelize($table), $errorMsg);
            $foreignKeys[$column] = $model;
        }

        /** @var \yii\db\ColumnSchema $data */
        foreach ($columns as $column => $data) {
            if ($data->autoIncrement) {
                continue;
            }

            $foreign      = null;
            $ref_table_id = null;
            $faker        = null;

            if (isset($foreignKeys[$column])) {
                $foreign      = $foreignKeys[$column];
                $ref_table_id = $foreign->tableSchema->primaryKey[0];
            }

            switch ($data->name) {
                case 'full_name':
                case 'name':
                    $faker = 'name';
                    break;
                case 'short_name':
                case 'first_name':
                case 'nickname':
                    $faker = 'firstName';
                    break;
                case 'last_name':
                    $faker = 'lastName';
                    break;
                case 'description':
                    $faker = 'realText()';
                    break;
                case 'company':
                case 'business_name':
                    $faker = 'company';
                    break;
                case 'email':
                    $faker = 'email';
                    break;
                case 'phone':
                case 'hp':
                    $faker = 'phoneNumber';
                    break;
            }

            if (!$faker) {
                switch ($data->type) {
                    case 'integer':
                    case 'smallint':
                    case 'tinyint':
                        $faker = 'numberBetween(0, 10)';
                        if ($data->dbType === 'tinyint(1)') {
                            $faker = 'boolean';
                            break;
                        }
                    case 'mediumint':
                    case 'int':
                    case 'bigint':
                        $faker = 'numberBetween(0, 10)';
                        break;
                    case 'date':
                        $faker = 'date()';
                        break;
                    case 'datetime':
                    case 'timestamp':
                        $faker = 'dateTime()';
                        break;
                    case 'year':
                        $faker = 'year()';
                        break;
                    case 'time':
                        $faker = 'time()';
                        break;
                    default:
                        $faker = 'text';
                }
            }

            $fields[$column] = (object)[
                'faker'        => $faker,
                'foreign'      => $foreign,
                'ref_table_id' => $ref_table_id
            ];
        }

        return (object) $fields;
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

        if ($defaultSeederClass instanceof TableSeeder) {
            return new $defaultSeederClass;
        }

        $this->stdout("\nClass {$defaultSeederClass} must instance of `\diecoding\seeder\TableSeeder`.\n");
        return null;
    }
}
