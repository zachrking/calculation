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

namespace App\Service;

use App\Entity\User;
use App\Interfaces\ImageExtensionInterface;
use Vich\UploaderBundle\Exception\NameGenerationException;
use Vich\UploaderBundle\Mapping\PropertyMapping;
use Vich\UploaderBundle\Naming\NamerInterface;

/**
 * Namer for user images.
 *
 * @implements NamerInterface<User>
 */
class UserNamer implements ImageExtensionInterface, NamerInterface
{
    /**
     * Gets the base file name.
     */
    public static function getBaseName(User|int $key, int $size, ?string $ext = null): string
    {
        $id = (int) ($key instanceof User ? $key->getId() : $key);
        $name = \sprintf('USER_%06d_%03d', $id, $size);
        if (null !== $ext && '' !== $ext) {
            return "$name.$ext";
        }

        return $name;
    }

    /**
     * {@inheritdoc}
     */
    public function name($object, PropertyMapping $mapping): string
    {
        if (!$object instanceof User) {
            throw new NameGenerationException(\sprintf('Expected argument of type "%s", "%s" given.', User::class, \get_debug_type($object)));
        }

        return self::getBaseName($object, self::SIZE_DEFAULT, self::EXTENSION_PNG);
    }
}
