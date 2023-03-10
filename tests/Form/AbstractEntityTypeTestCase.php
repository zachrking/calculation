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

namespace App\Tests\Form;

use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * Test for type class.
 */
abstract class AbstractEntityTypeTestCase extends TypeTestCase
{
    /**
     * Test.
     */
    public function testSubmitValidData(): void
    {
        $data = $this->getData();
        $className = $this->getEntityClass();

        // create model and form
        $model = new $className();
        $form = $this->factory->create($this->getFormTypeClass(), $model);

        // populate form data
        $expected = $this->populate($className, $data);

        // submit the data to the form directly
        $form->submit($data);

        // check form
        self::assertTrue($form->isSynchronized());

        // check data
        self::assertEquals($expected, $model);

        // check view
        $view = $form->createView();
        $children = $view->children;
        foreach (\array_keys($data) as $key) {
            self::assertArrayHasKey($key, $children);
        }
    }

    /**
     * Gets the data to test.
     *
     * @return array an array where keys are field
     */
    abstract protected function getData(): array;

    /**
     * Gets the entity class name.
     *
     * @psalm-return class-string
     */
    abstract protected function getEntityClass(): string;

    /**
     * Gets the form type class name.
     *
     * @psalm-return class-string
     */
    abstract protected function getFormTypeClass(): string;

    /**
     * Update the given entity with the given data.
     */
    protected function populate(string $className, array $data): mixed
    {
        $entity = new $className();
        $accessor = PropertyAccess::createPropertyAccessor();
        foreach ($data as $key => $value) {
            $accessor->setValue($entity, $key, $value);
        }

        return $entity;
    }
}
