<?php

namespace Tests\Generators;

use Eris\Generator;

class AccountManagementGenerators
{
    public static function validProfile(): Generator\Generator
    {
        return Generator\associative([
            'first_name' => Generator\string(),
            'last_name' => Generator\string(),
            'middle_name' => Generator\string(),
            'suffix' => Generator\string(),
            'email' => Generator\map(
                fn($s) => $s . '@example.com',
                Generator\string()
            ),
            'student_id' => Generator\string(),
            'department_id' => Generator\pos(),
            'course' => Generator\string(),
            'year_level' => Generator\choose(1, 5),
        ]);
    }

    public static function invalidProfile(): Generator\Generator
    {
        // Generates profiles that are missing required fields or have invalid types
        return Generator\associative([
            'first_name' => Generator\elements('', null),
            'last_name' => Generator\elements('', null),
            'email' => Generator\elements('not-an-email', null),
        ]);
    }
}
