<?php
/*
 * This file is part of the Calculation package.
 *
 * (c) bibi.nu. <bibi@bibi.nu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Spreadsheet;

use App\Controller\AbstractController;
use App\Entity\User;
use App\Util\Utils;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Vich\UploaderBundle\Mapping\PropertyMappingFactory;
use Vich\UploaderBundle\Storage\StorageInterface;

/**
 * Excel document for the list of users.
 *
 * @author Laurent Muller
 */
class UserDocument extends AbstractArrayDocument
{
    /**
     * The mapping factory.
     *
     * @var PropertyMappingFactory
     */
    private $factory;

    /**
     * The configured file property name.
     *
     * @var string|null
     */
    private $fieldName;

    /**
     * The image storage.
     *
     * @var StorageInterface
     */
    private $storage;

    /**
     * Constructor.
     *
     * @param AbstractController     $controller the parent controller
     * @param User[]                 $entities   the users to render
     * @param PropertyMappingFactory $factory    the factory to get mapping informations
     * @param StorageInterface       $storage    the storage to get images path
     */
    public function __construct(AbstractController $controller, array $entities, PropertyMappingFactory $factory, StorageInterface $storage)
    {
        parent::__construct($controller, $entities);
        $this->factory = $factory;
        $this->storage = $storage;
    }

    /**
     * {@inheritdoc}
     */
    protected function doRender(array $entities): bool
    {
        // initialize
        $this->start('user.list.title');

        // headers
        $this->setHeaderValues([
            'user.fields.username' => [Alignment::HORIZONTAL_GENERAL, Alignment::VERTICAL_TOP],
            'user.fields.email' => [Alignment::HORIZONTAL_GENERAL, Alignment::VERTICAL_TOP],
            'user.fields.role' => [Alignment::HORIZONTAL_GENERAL, Alignment::VERTICAL_TOP],
            'user.fields.enabled' => [Alignment::HORIZONTAL_LEFT, Alignment::VERTICAL_TOP],
            'user.fields.lastLogin' => [Alignment::HORIZONTAL_CENTER, Alignment::VERTICAL_TOP],
            'user.fields.imageFile' => [Alignment::HORIZONTAL_LEFT, Alignment::VERTICAL_TOP],
        ]);

        // formats
        $this->setFormatBoolean(4, 'common.value_enabled', 'common.value_disabled', true)
            ->setFormatDateTime(5);

        // rows
        $row = 2;
        /** @var User $entity */
        foreach ($entities as $entity) {
            $this->setRowValues($row, [
                $entity->getUsername(),
                $entity->getEmail(),
                Utils::translateRole($this->translator, $entity->getRole()),
                $entity->isEnabled(),
                $entity->getLastLogin(),
            ]);

            // image
            $path = $this->getImagePath($entity);
            if (!empty($path)) {
                [$width, $height] = \getimagesize($path);
                $this->setCellImage($path, "F$row", $width, $height);
            }

            ++$row;
        }

        $this->finish();

        return true;
    }

    /**
     * Gets the configured file property name used to resolve path.
     *
     * @param User $user the user to get field name
     *
     * @return string|null the configured file property name or null if none
     */
    private function getFieldName(User $user): ?string
    {
        if (!$this->fieldName) {
            $mappings = $this->factory->fromObject($user);
            if (!empty($mappings)) {
                $this->fieldName = $mappings[0]->getFilePropertyName();
            }
        }

        return $this->fieldName;
    }

    /**
     * Gets the user's image full path.
     *
     * @param User $user the user to get image path for
     *
     * @return string|null the image path, if exists; null otherwise
     */
    private function getImagePath(User $user): ?string
    {
        if ($fieldName = $this->getFieldName($user)) {
            $path = $this->storage->resolvePath($user, $fieldName);
            if ($path) {
                $path = \str_replace('192', '032', $path);
                if (\is_file($path)) {
                    return $path;
                }
            }
        }

        return null;
    }
}
