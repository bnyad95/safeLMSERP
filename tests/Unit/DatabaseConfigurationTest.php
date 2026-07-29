<?php

namespace Tests\Unit;

use Tests\TestCase;

class DatabaseConfigurationTest extends TestCase
{
    public function test_mysql_pdo_options_use_scalar_attribute_values(): void
    {
        foreach (['mysql', 'mariadb'] as $connection) {
            $options = config("database.connections.{$connection}.options");

            $this->assertIsArray($options);

            foreach ($options as $attribute => $value) {
                $this->assertIsInt($attribute);
                $this->assertIsNotArray($value);
            }
        }
    }
}
