<?php
/*
 * This file is part of the Calculation package.
 *
 * (c) bibi.nu <bibi@bibi.nu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Faker;

/**
 * Faker provider to generate custom person.
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
class CustomPerson extends \Faker\Provider\fr_CH\Person
{
    /** @psalm-var mixed */
    protected static $titleFemale = ['Madame', 'Mademoiselle'];

    /** @psalm-var mixed */
    protected static $titleMale = ['Monsieur'];
}
