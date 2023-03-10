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

namespace App\Form\Extension;

use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormConfigInterface;
use Symfony\Component\Form\FormInterface;

/**
 * Extends the FileType to use within the FileInput plugin.
 */
class FileTypeExtension extends AbstractFileTypeExtension
{
    /**
     * {@inheritdoc}
     */
    public static function getExtendedTypes(): iterable
    {
        return [FileType::class];
    }

    /**
     * {@inheritdoc}
     */
    protected function updateAttributes(FormInterface $form, array &$attributes, array &$options): void
    {
        // merge options from parent (VichFileType or VichImageType)
        if (null !== $parent = $form->getParent()) {
            $configuration = $parent->getConfig();
            foreach (['placeholder', 'maxfiles', 'maxsize'] as $name) {
                $this->updateOption($configuration, $options, $name);
            }
        }

        // default
        parent::updateAttributes($form, $attributes, $options);
    }

    /**
     * Update an option.
     *
     * @param FormConfigInterface $configuration the form configuration to get value from
     * @param array               $options       the options array to update
     * @param string              $name          the option name to search for
     *
     * @psalm-suppress MixedAssignment
     */
    private function updateOption(FormConfigInterface $configuration, array &$options, string $name): void
    {
        if ($configuration->hasOption($name)) {
            $options[$name] = $configuration->getOption($name);
        }
    }
}
