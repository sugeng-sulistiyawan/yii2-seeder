<?php

/**
 * This view is used by diecoding\seeder\SeederController.php.
 *
 * The following variables are available in this view:
 */

use yii\helpers\Inflector;
use yii\helpers\StringHelper;

/** @var string $className the new seeder class name without namespace */
/** @var string $namespace the new seeder class namespace */
/** @var \yii\db\ActiveRecord $model model */
/** @var array $fields the fields */

echo "<?php\n";
if (!empty($namespace)) {
    echo "\nnamespace {$namespace};\n";
}

$modelClass  = $model::class;
$modelName   = StringHelper::basename($modelClass);
$foreignVars = [];

$checkClass[$modelClass]    = $modelClass;
$checkClassName[$modelName] = [$modelClass];

?>

use diecoding\seeder\TableSeeder;
<?= "use {$modelClass};\n" ?>
<?php
$i = 0;
foreach ($fields as $column => $properties) {
    if ($foreign = $properties->foreign) {
        $foreignClass     = $foreign::class;
        $foreignClassName = StringHelper::basename($foreignClass);
        if (!isset($checkClass[$foreignClass])) {
            if (isset($checkClassName[$foreignClassName])) {
                $foreignClassName .= count($checkClassName[$foreignClassName]);
                $checkClassName[$foreignClassName][] = $foreignClass;

                echo "use {$foreignClass} as {$foreignClassName};\n";
            } else {
                $checkClassName[$foreignClassName] = [$foreignClass];

                echo "use {$foreignClass};\n";
            }
            $checkClass[$foreignClass] = $foreignClass;
        }

        $ref_table_id                   = $properties->ref_table_id;
        $vars[$ref_table_id . $column]  = '$' . Inflector::variablize($foreignClassName);
        $foreignVars[$foreignClassName] = "{$vars[$ref_table_id .$column]} = {$foreignClassName}::find()->all();\n";
    }
} ?>

/**
 * Handles the creation of seeder `<?= $modelName ?>::tableName()`.
 */
class <?= $className ?> extends TableSeeder
{
    // public $truncateTable = false;
    // public $locale = 'en_US';

    /**
     * {@inheritdoc}
     */
    public function run()
    {
        <?= implode("        ", $foreignVars) ?>

        $count = 100;
        for ($i = 0; $i < $count; $i++) {
            $this->insert(<?= $modelName ?>::tableName(), [
                <?php
                $i = 0;
                foreach ($fields as $column => $properties) {
                    $ref_table_id = $properties->ref_table_id;
                    $space        = $i++ === 0 ? '' : "\t\t\t\t";
                    if (isset($vars[$ref_table_id . $column])) {
                        echo $space . "'$column' => \$this->faker->randomElement({$vars[$ref_table_id .$column]})->{$properties->ref_table_id},\n";
                    } else {
                        echo $space . "'$column' => \$this->faker->{$properties->faker},\n";
                    }
                }
                ?>
            ]);
        }
    }
}
