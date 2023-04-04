<?php

/** @var string $namespace */

echo "<?php\n";
?>
namespace <?= $namespace ?>;

use diecoding\seeder\TableSeeder;

/**
 * Default Seeder
 */
class Seeder extends TableSeeder
{
    /**
     * Default execution
     *
     * @return void
     */
    public function run()
    {
        // TableSeeder::create()->run();
    }

}
