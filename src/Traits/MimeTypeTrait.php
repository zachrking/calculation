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

namespace App\Traits;

use App\Util\Utils;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\Mime\MimeTypes;

/**
 * Trait to create file response headers.
 */
trait MimeTypeTrait
{
    /**
     * {@inheritDoc}
     */
    public function getAttachmentMimeType(): string
    {
        return 'application/x-download';
    }

    /**
     * {@inheritDoc}
     */
    public function getInlineMimeType(): string
    {
        /** @psalm-var string|null $mimeType */
        static $mimeType = null;
        if (null === $mimeType) {
            $types = new MimeTypes();
            $extension = $this->getFileExtension();
            $mimeType = $types->getMimeTypes($extension)[0];
        }

        return $mimeType;
    }

    /**
     * Build response header for an attachment.
     *
     * @param string $name   the document name
     * @param bool   $inline true to send the file inline to the browser. The document viewer is used if available,
     *                       false to send to the browser and force a file download with the name given.
     */
    protected function buildHeaders(string $name, bool $inline): array
    {
        $name = $this->validate($name);
        $encoded = Utils::ascii($name);
        $type = $inline ? $this->getInlineMimeType() : $this->getAttachmentMimeType();
        $disposition = $inline ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT;

        return [
            'Pragma' => 'public',
            'Content-Type' => $type,
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'Content-Disposition' => HeaderUtils::makeDisposition($disposition, $name, $encoded),
        ];
    }

    /**
     * Validate the given document name.
     */
    private function validate(string $name): string
    {
        $name = empty($name) ? 'document' : \basename($name);
        $extension = '.' . $this->getFileExtension();
        if (!\str_ends_with($name, $extension)) {
            return $name . $extension;
        }

        return $name;
    }
}
