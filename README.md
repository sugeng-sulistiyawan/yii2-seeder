# Yii2 Seeder

Create fast-fill database with fake data or bulk data for Yii2.

[![Latest Stable Version](https://img.shields.io/packagist/v/diecoding/yii2-seeder?label=stable)](https://packagist.org/packages/diecoding/yii2-seeder)
[![Total Downloads](https://img.shields.io/packagist/dt/diecoding/yii2-seeder)](https://packagist.org/packages/diecoding/yii2-seeder)
[![Latest Stable Release Date](https://img.shields.io/github/release-date/sugeng-sulistiyawan/yii2-seeder)](https://github.com/sugeng-sulistiyawan/yii2-seeder)
[![Quality Score](https://img.shields.io/scrutinizer/quality/g/sugeng-sulistiyawan/yii2-seeder)](https://scrutinizer-ci.com/g/sugeng-sulistiyawan/yii2-seeder)
[![Build Status](https://img.shields.io/travis/com/sugeng-sulistiyawan/yii2-seeder)](https://app.travis-ci.com/sugeng-sulistiyawan/yii2-seeder)
[![License](https://img.shields.io/github/license/sugeng-sulistiyawan/yii2-seeder)](https://github.com/sugeng-sulistiyawan/yii2-seeder)
[![PHP Version Require](https://img.shields.io/packagist/dependency-v/diecoding/yii2-seeder/php?color=6f73a6)](https://packagist.org/packages/diecoding/yii2-seeder)

## Table of Contents

- [Yii2 Seeder](#yii2-seeder)
  - [Table of Contents](#table-of-contents)
  - [Instalation](#instalation)
  - [Dependencies](#dependencies)
  - [Usage](#usage)
    - [Config](#config)
      - [Basic Config](#basic-config)
      - [Advanced Config](#advanced-config)
    - [Commands](#commands)
    - [Example](#example)
    - [Seeder](#seeder)

## Instalation

Package is available on [Packagist](https://packagist.org/packages/diecoding/yii2-seeder), you can install it using [Composer](https://getcomposer.org).

```shell
composer require diecoding/yii2-seeder "^1.0"
```

or add to the require section of your `composer.json` file.

```shell
"diecoding/yii2-seeder": "^1.0"
```

## Dependencies

- PHP 7.4+
- [yiisoft/yii2](https://github.com/yiisoft/yii2)
- [yiisoft/yii2-faker](https://github.com/yiisoft/yii2-faker)

## Usage

### Config

> Add `controllerMap` to your `config` file `console/config/main.php` or in yii2-basic `config/console.php`

#### Basic Config

```php
// ...
'controllerMap' => [
    // ...
    'seeder' => [
        'class' => \diecoding\seeder\SeederController::class,
    ],
    // ...
],
// ...
```

#### Advanced Config

```php
// ...
'controllerMap' => [
    // ...
    'seeder' => [
        'class' => \diecoding\seeder\SeederController::class,

        /** @var string the default command action. */
        'defaultAction' => 'seed',

        /** @var string seeder path, support path alias */
        'seederPath' => '@console/seeder',

        /** @var string seeder namespace */
        'seederNamespace' => 'console\seeder',

        /** 
         * @var string this class look like `$this->seederNamespace\Seeder` 
         * default seeder class run if no class selected, 
         * must instance of `\diecoding\seeder\TableSeeder` 
         */
        'defaultSeederClass' => 'Seeder',

        /** @var string tables path, support path alias */
        'tablesPath' => '@console/seeder/tables',

        /** @var string seeder table namespace */
        'tableSeederNamespace' => 'console\seeder\tables',

        /** @var string model namespace */
        'modelNamespace' => 'common\models',

        /** @var string path view template table seeder, support path alias */
        'templateSeederFile' => '@vendor/diecoding/yii2-seeder/src/views/Seeder.php';

        /** @var string path view template seeder, support path alias */
        'templateTableFile' => '@vendor/diecoding/yii2-seeder/src/views/TableSeeder.php';
    ],
    // ...
],
// ...
```

### Commands

`yii seeder` Seed all tables in `Seeder::run()` or this seed call `$defaultSeederClass::run`

`yii seeder [name]` Seed a table

`yii seeder [name]:[funtion_name]` Seed a table and run a specific function from selected TableSeeder

- `name`

  - full path / class name (e.g `yii seeder console\seeder\tables\UserTableSeeder` for `UserTableSeeder`)
  - without TableSeeder (e.g `yii seeder user` for `UserTableSeeder`)
  - with TableSeeder (e.g `yii seeder userTableSeeder` for `UserTableSeeder`)
  - @see https://github.com/sugeng-sulistiyawan/yii2-seeder/blob/main/src/SeederController.php#L109 for more usage

`yii seeder/create [model_name]` Create a TableSeeder in `console\seeder\tables\ModelNameTableSeeder`

- `model_name`

  - `yii seeder/create model_name`
  - `yii seeder/create model-name`
  - `yii seeder/create full\path\Class`
  - @see https://www.yiiframework.com/doc/api/2.0/yii-helpers-inflector#camelize()-detail for handle `[model_name]` options

> For seeder, if the model is not at the root of the `common/models`, just add the folder where the model is located inside the `common/models` directory. You can configure with `$modelNamespace` config in console\config.

Example:

`yii seeder/create entity/user`

`entity` is the folder where `User` (model) is located inside the `common/models` directory.

To change the default path for models, just change the `$modelNamespace` variable in `SeederController`

**Only Seeders within `Seeder::run()` will be used in `yii seeder` command**

### Example

```php
<?php

namespace console\seeder\tables;

use common\models\User;
use common\models\Province;
use diecoding\seeder\TableSeeder;

class UserTableSeeder extends TableSeeder
{
    // public $truncateTable = false;
    // public $locale = 'en_US';

    /**
     * Default execution
     *
     * @return void
     */
    public function run()
    {
        $province = Province::find()->all();

        $count = 100;
        for ($i = 0; $i < $count; $i++) { 
            $this->insert(User::tableName(), [
                'province_id' => $this->faker->randomElement($province)->id,
                'email'       => $this->faker->email,
                'name'        => $this->faker->name,
                'phone'       => $this->faker->phoneNumber,
                'company'     => $this->faker->company,
                'street'      => $this->faker->streetName,
                'zip_code'    => $this->faker->postcode,
                'city'        => $this->faker->city,
                'created_at'  => $this->faker->dateTime(),
                'updated_at'  => $this->faker->dateTime(),
                'status'      => User::STATUS_USER_ACTIVE
            ]);
        }
    }
}
```

By default, all TableSeeder truncate the table before inserting new data, if you didn't want that to happen in a Seeder, just overwrite `$truncateTable`:

```php
public $truncateTable = false;
```

**default in TableSeeder:**

```php
public $truncateTable = true;

// ...

// truncate table
$this->disableForeignKeyChecks();
$this->truncateTable(/* table names */);
$this->enableForeignKeyChecks();
```

By default, all TableSeeder faker locale/language get from `Yii::$app->language`, if you want to use another locale just overwrite `$locale`:

```php
public $locale = 'en_US';
```

At the end of every Seeder, if any columns have been forgotten, a message with all the missing columns will appear

```shell
    > ########################### MISSING COLUMNS ############################
    > #                                                                      #
    > #    TABLE: {{%user}}                                                  #
    > # -------------------------------------------------------------------- #
    > #    - full_name => varchar(1)                                         #
    > #    - birth_date => date                                              #
    > #    - thumbnail => varchar(64)                                        #
    > # -------------------------------------------------------------------- #
    > #                                                                      #
    > ########################################################################
```

### Seeder

`Seeder` will be created on first `yii seeder` if not exist

Here you will put all TableSeeder in `::run()`

to run, use `yii seeder` or `yii seeder [name]`

**Seeder template:**

**`Seeder` location in `console\seeder`**

```php
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
        ModelTableSeeder::create()->run();
    }
}
```

---

Read more docs: https://sugengsulistiyawan.my.id/docs/opensource/yii2/seeder/
