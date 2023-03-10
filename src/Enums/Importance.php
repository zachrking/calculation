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

use App\Interfaces\DefaultEnumInterface;
use App\Interfaces\SortableEnumInterface;
use App\Traits\DefaultEnumTrait;
use Elao\Enum\Attribute\EnumCase;
use Elao\Enum\Attribute\ReadableEnum;
use Elao\Enum\ReadableEnumInterface;
use Elao\Enum\ReadableEnumTrait;

/**
 * The notification email importance enumeration.
 *
 *  @implements SortableEnumInterface<Importance>
 */
#[ReadableEnum(prefix: 'importance.', useValueAsDefault: true)]
enum Importance: string implements DefaultEnumInterface, ReadableEnumInterface, SortableEnumInterface
{
    use DefaultEnumTrait;
    use ReadableEnumTrait;

    /*
     * High importance.
     */
    case HIGH = 'high';
    /*
     * Low importance (default value).
     */
    #[EnumCase(extras: ['default' => true])]
    case LOW = 'low';
    /*
     * Medium  importance.
     */
    case MEDIUM = 'medium';
    /*
     * Urgente importance.
     */
    case URGENT = 'urgent';

    /**
     * Gets the full human representation of the value.
     */
    public function getReadableFull(): string
    {
        return $this->getReadable() . '_full';
    }

    /**
     * @return Importance[]
     */
    public static function sorted(): array
    {
        return [
            Importance::LOW,
            Importance::MEDIUM,
            Importance::HIGH,
            Importance::URGENT,
        ];
    }
}
