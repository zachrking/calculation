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
use Elao\Enum\ReadableEnumInterface;
use Elao\Enum\ReadableEnumTrait;

/**
 * Entity action enumeration.
 *
 * @implements SortableEnumInterface<EntityAction>
 */
enum EntityAction: string implements DefaultEnumInterface, ReadableEnumInterface, SortableEnumInterface
{
    use DefaultEnumTrait;
    use ReadableEnumTrait;

    /*
     * Edit the entity (default value).
     */
    #[EnumCase('entity_action.edit', ['default' => true])]
    case EDIT = 'edit';
    /*
     * No action.
     */
    #[EnumCase('entity_action.none')]
    case NONE = 'none';
    /*
     * Show the entity.
     */
    #[EnumCase('entity_action.show')]
    case SHOW = 'show';

    /**
     * @return EntityAction[]
     */
    public static function sorted(): array
    {
        return [
            EntityAction::EDIT,
            EntityAction::SHOW,
            EntityAction::NONE,
        ];
    }

    /**
     * Gets the action values.
     *
     * @return string[]
     */
    public static function values(): array
    {
        return \array_map(fn (EntityAction $action): string => $action->value, EntityAction::sorted());
    }
}
