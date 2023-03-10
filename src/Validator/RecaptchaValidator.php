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

namespace App\Validator;

use ReCaptcha\ReCaptcha as ReCaptchaService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\Constraint;

/**
 * Google reCaptcha constraint validator.
 *
 * @extends AbstractConstraintValidator<Recaptcha>
 */
class RecaptchaValidator extends AbstractConstraintValidator
{
    /**
     * Constructor.
     *
     * @param string $secret the reCaptcha secret key
     */
    public function __construct(
        #[Autowire('%google_recaptcha_secret_key%')]
        private readonly string $secret
    ) {
        parent::__construct(Recaptcha::class);
    }

    /**
     * {@inheritdoc}
     *
     * @param Recaptcha $constraint
     */
    protected function doValidate(string $value, Constraint $constraint): void
    {
        $recaptcha = new ReCaptchaService($this->secret);
        $result = $recaptcha->verify($value);
        if (!$result->isSuccess()) {
            /** @var string[] $errorCodes */
            $errorCodes = $result->getErrorCodes();
            foreach ($errorCodes as $code) {
                $this->context->buildViolation("recaptcha.$code")
                    ->setCode($code)
                    ->addViolation();
            }
        }
    }
}
