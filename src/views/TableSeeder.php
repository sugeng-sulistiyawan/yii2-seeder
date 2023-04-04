<?php
/**
 * This view is used by diecoding\seeder\SeederController.php.
 *
 * The following variables are available in this view:
 */

use yii\helpers\StringHelper;

/** @var string $className the new seeder class name without namespace */
/** @var string $namespace the new seeder class namespace */
/** @var \yii\db\ActiveRecord $model model */
/** @var array $fields the fields */

echo "<?php\n";
if (!empty($namespace)) {
    echo "\nnamespace {$namespace};\n";
}

$modelClass = $model::class;
$vars = [];
?>

use diecoding\seeder\TableSeeder;
<?= "use {$modelClass};\n" ?>
<?php foreach ($fields as $column => $properties) {
    $classes = [];
    $check[$modelName] = $modelName;
    if ($foreign = $properties->foreign) {
        $_class     = $foreign::class;
        $_className = StringHelper::basename($_class);
        if (isset($check[$_className])) {
            $_class .= count($check);
        }
        $classes[$_class]   = $_class;
        $check[$_className] = $_className;

        echo "use {$_class};\n";
    }
} ?>

/**
 * Handles the creation of seeder `<?= $table ?>`.
 */
class <?= $className ?> extends TableSeeder
{
    /**
     * {@inheritdoc}
     */
    function run()
    {
        loop(function ($i) <?= count($vars) ? 'use ('. implode(', ', $vars) .') ' : null ?>{
            $this->insert(<?= $modelName ?>::tableName(), [
                <?php
                    $i = 0;
                    foreach ($fields as $column => $properties) {
                        $space = $i++ === 0 ? '' : "\t\t\t\t";
                        if($foreign = $properties->foreign) {
                            $count = strtoupper(preg_replace("/[{%}]/", '', $foreign::tableName())) . '_COUNT';
                        
                            echo $space . "'$column' => \$this->faker->numberBetween(1, DatabaseSeeder::$count),\n";
                        } else {
                            echo $space . "'$column' => \$this->faker->$properties->faker,\n";
                        }
                    } ?>
            ]);
        }, DatabaseSeeder::<?= strtoupper(preg_replace("/[{%}]/", '', $table)) ?>_COUNT);
    }
}
