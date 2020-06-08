<?php
/*
 * This file is part of the Calculation package.
 *
 * Copyright (c) 2019 bibi.nu. All rights reserved.
 *
 * This computer code is protected by copyright law and international
 * treaties. Unauthorised reproduction or distribution of this code, or
 * any portion of it, may result in severe civil and criminal penalties,
 * and will be prosecuted to the maximum extent possible under the law.
 */

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Abstract constraint validator.
 *
 * @author Laurent Muller
 */
abstract class AbstractConstraintValidator extends ConstraintValidator
{
    /**
     * The constraint class.
     *
     * @var string
     */
    protected $class;

    /**
     * Constructor.
     *
     * @param string $class the constraint class
     */
    public function __construct(string $class)
    {
        $this->class = $class;
    }

    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint): void
    {
        if (!\is_a($constraint, $this->class)) {
            throw new UnexpectedTypeException($constraint, $this->class);
        }

        if ($this->isAllowEmpty() && (null === $value || '' === $value)) {
            return;
        }

        $this->doValidate($this->convert($value), $constraint);
    }

    /**
     * Checks and converts the given value.
     *
     * @param mixed $value the value to checks
     *
     * @throws UnexpectedValueException if the value can not be converted
     *
     * @return mixed the converted value
     */
    protected function convert($value)
    {
        if (!\is_scalar($value) && !(\is_object($value) && \method_exists($value, '__toString'))) {
            throw new UnexpectedValueException($value, 'string');
        }

        return (string) $value;
    }

    /**
     * Performs validation.
     *
     * @param mixed      $value      the value that should be validated
     * @param Constraint $constraint the constraint
     */
    abstract protected function doValidate($value, Constraint $constraint): void;

    /**
     * Returns a value indicating if the value to be tested can be null or empty.
     *
     * If true and the value to validate is null or empty, no validation is performed.
     */
    protected function isAllowEmpty(): bool
    {
        return true;
    }
}
