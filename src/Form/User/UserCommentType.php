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

namespace App\Form\User;

use App\Form\FormHelper;
use App\Service\ThemeService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Type to send a comment.
 *
 * @author Laurent Muller
 */
class UserCommentType extends AbstractType
{
    /**
     * The dark theme state.
     *
     * @var bool
     */
    protected $dark;

    /**
     * Constructor.
     */
    public function __construct(ThemeService $service)
    {
        $this->dark = $service->getCurrentTheme()->isDark();
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $isMail = $options['data']->isMail();
        $helper = new FormHelper($builder, 'user.fields.');

        if ($isMail) {
            $helper->field('to')
                ->addPlainType(true);
        } else {
            $helper->field('from')
                ->addPlainType(true);
        }

        $helper->field('subject')
            ->addPlainType(true);

        $helper->field('message')
            ->minLength(10)
            ->updateAttribute('data-skin', $this->dark ? 'oxide-dark' : 'oxide')
            ->addEditorType();

        $helper->field('attachments')
            ->updateOption('multiple', true)
            ->updateOption('maxfiles', 3)
            ->updateOption('maxsize', '10mi')
            ->updateOption('maxsizetotal', '30mi')
            ->notRequired()
            ->addFileType();
    }
}
