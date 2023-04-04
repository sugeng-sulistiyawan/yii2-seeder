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

$modelClass        = $model::class;
$modelName         = StringHelper::basename($modelClass);
$varsPre           = [];
$check[$modelName] = [$modelName];

?>

use diecoding\seeder\TableSeeder;
<?= "use {$modelClass};\n" ?>
<?php 
$i = 0;
foreach ($fields as $column => $properties) {
    if ($foreign = $properties->foreign) {
        $_class     = $foreign::class;
        $_className = StringHelper::basename($_class);
        if (isset($check[$_className])) {
            $_className = $_className . count($check[$_className]);

            echo "use {$_class} as {$_className};\n";
        } else {
            echo "use {$_class};\n";
        }

        $check[$_className]               = [$_class];
        $ref_table_id                     = $properties->ref_table_id;
        $space                            = $i++ === 0 ? '' : "\t\t";
        $vars[$ref_table_id . $column]    = '$' . Inflector::variablize($_className);
        $varsPre[$ref_table_id . $column] = "{$space}{$vars[$ref_table_id . $column]} = {$_className}::find()->all();\n";
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
    function run()
    {
        <?= implode("", $varsPre) ?>

        $count = 100;
        for ($i = 0; $i < $count; $i++) { 
            $this->insert(<?= $modelName ?>::tableName(), [
                <?php
                    $i = 0;
                    foreach ($fields as $column => $properties) {
                        $ref_table_id = $properties->ref_table_id;
                        $space        = $i++ === 0 ? '' : "\t\t\t\t";
                        if (isset($vars[$ref_table_id . $column])) {
                            echo $space . "'$column' => \$this->faker->randomElement({$vars[$ref_table_id . $column]})->{$properties->ref_table_id},\n";
                        } else {
                            echo $space . "'$column' => \$this->faker->{$properties->faker},\n";
                        }
                    } 
                ?>
            ]);
        }
    }
}
