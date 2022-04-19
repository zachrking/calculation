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

namespace App\Enums;

/**
 * Flash bag type enumeration.
 */
enum FlashType: string
{
    case DANGER = 'danger';
    case INFO = 'info';
    case SUCCESS = 'success';
    case WARNING = 'warning';
}
