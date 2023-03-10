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

namespace App\Listener;

use App\Entity\User;
use App\Interfaces\ImageExtensionInterface;
use App\Service\ImageResizer;
use App\Service\UserNamer;
use App\Util\FileUtils;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Vich\UploaderBundle\Event\Event;
use Vich\UploaderBundle\Event\Events;
use Vich\UploaderBundle\Mapping\PropertyMapping;
use Vich\UploaderBundle\Naming\Polyfill\FileExtensionTrait;

/**
 * Listener to resize the profile image.
 */
#[AsEventListener(event: Events::PRE_UPLOAD, method: 'onPreUpload')]
#[AsEventListener(event: Events::PRE_REMOVE, method: 'onPreRemove')]
#[AsEventListener(event: Events::POST_UPLOAD, method: 'onPostUpload')]
class VichListener implements ImageExtensionInterface
{
    use FileExtensionTrait;

    /**
     * Constructor.
     */
    public function __construct(private readonly ImageResizer $resizer)
    {
    }

    /**
     * Create the small and medium image if applicable.
     *
     * @throws \ReflectionException
     */
    public function onPostUpload(Event $event): void
    {
        /** @var User $user */
        $user = $event->getObject();
        $mapping = $event->getMapping();

        $file = $mapping->getFile($user);
        if (!$file instanceof File || !$file->isReadable()) {
            return;
        }

        // new?
        if (\preg_match('/0{6}/m', $file->getFilename())) {
            $file = $this->rename($mapping, $user, $file);
        }

        // get values
        $source = (string) $file->getRealPath();
        $extension = $file->getExtension();
        $path = $file->getPath() . \DIRECTORY_SEPARATOR;

        // create medium image
        $target = $path . UserNamer::getBaseName($user, self::SIZE_MEDIUM, $extension);
        $this->resizer->resizeMedium($source, $target);

        // create small image
        $target = $path . UserNamer::getBaseName($user, self::SIZE_SMALL, $extension);
        $this->resizer->resizeSmall($source, $target);
    }

    /**
     * Remove the small and medium image if applicable.
     */
    public function onPreRemove(Event $event): void
    {
        /** @var User $user */
        $user = $event->getObject();
        $mapping = $event->getMapping();

        // directory
        $path = $mapping->getUploadDestination() . \DIRECTORY_SEPARATOR;

        // get file extension
        $filename = (string) $mapping->getFileName($user);
        $file = new File($filename, false);
        $ext = $file->getExtension();

        // delete medium image
        $filename = $path . UserNamer::getBaseName($user, self::SIZE_MEDIUM, $ext);
        FileUtils::remove($filename);

        // delete small image
        $filename = $path . UserNamer::getBaseName($user, self::SIZE_SMALL, $ext);
        FileUtils::remove($filename);
    }

    /**
     * Rename and resize the image if applicable.
     *
     * @throws \ReflectionException
     */
    public function onPreUpload(Event $event): void
    {
        /** @var User $user */
        $user = $event->getObject();
        $mapping = $event->getMapping();

        $file = $mapping->getFile($user);
        if (!$file instanceof UploadedFile || !$file->isReadable()) {
            return;
        }

        // target file name
        if ('' === $name = $mapping->getUploadName($user)) {
            return;
        }

        // resize
        $source = (string) $file->getRealPath();
        $this->resizer->resizeDefault($source, $source);

        // rename if not same extension
        $extension = $this->getFileExtension($file);
        if (self::EXTENSION_PNG !== $extension) {
            $pos = \strrpos($name, '.');
            if (false !== $pos) {
                $newName = \substr_replace($name, self::EXTENSION_PNG, $pos + 1);
                $mapping->setFileName($user, $newName);
            }
        }
    }

    /**
     * Gets the file extension.
     */
    private function getFileExtension(UploadedFile $file): string
    {
        $extension = $this->getExtension($file);

        return empty($extension) ? self::EXTENSION_PNG : \strtolower($extension);
    }

    private function rename(PropertyMapping $mapping, User $user, File $file): File
    {
        $name = UserNamer::getBaseName($user, self::SIZE_DEFAULT, $file->getExtension());
        $newFile = new File($file->getPath() . \DIRECTORY_SEPARATOR . $name, false);

        FileUtils::rename($file->getPathname(), $newFile->getPathname());

        $mapping->setFileName($user, $name);
        $mapping->setFile($user, $newFile);

        return $newFile;
    }
}
