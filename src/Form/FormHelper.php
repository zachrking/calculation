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

namespace App\Form;

use App\Form\Type\CurrentPasswordType;
use App\Form\Type\FaxType;
use App\Form\Type\PlainType;
use App\Form\Type\RepeatPasswordType;
use App\Form\Type\YesNoType;
use App\Interfaces\SortableEnumInterface;
use App\Util\FormatUtils;
use Elao\Enum\Bridge\Symfony\Form\Type\EnumType as ElaoEnumType;
use Elao\Enum\Bridge\Symfony\Form\Type\FlagBagType;
use Elao\Enum\ReadableEnumInterface;
use Symfony\Component\Form\Extension\Core\Type\BirthdayType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\PercentType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\Form\Type\VichImageType;

/**
 * Helper class to add types to a form builder.
 */
class FormHelper
{
    /**
     * The attributes.
     *
     * @var array<string, mixed>
     */
    private array $attributes = [];

    /**
     * The field identifier.
     */
    private ?string $field = null;

    /**
     * The help attributes.
     *
     * @var array<string, mixed>
     */
    private array $helpAttributes = [];

    /**
     * The label attributes.
     *
     * @var array<string, mixed>
     */
    private array $labelAttributes = [];

    /**
     * The labels prefix.
     */
    private readonly ?string $labelPrefix;

    /**
     * The options.
     *
     * @var array<string, mixed>
     */
    private array $options = [];

    /**
     * The row attributes.
     *
     * @var array<string, mixed>
     */
    private array $rowAttributes = [];

    /**
     * Constructor.
     *
     * @param FormBuilderInterface $builder     the parent builder
     * @param ?string              $labelPrefix the label prefix. If the prefix is not null,
     *                                          the label is automatically added when the field property is
     *                                          set.
     */
    public function __construct(private readonly FormBuilderInterface $builder, ?string $labelPrefix = null)
    {
        $this->labelPrefix = empty($labelPrefix) ? null : $labelPrefix;
    }

    /**
     * Adds a new field to this builder and reset all values to default.
     *
     * @param string $type the child type to add
     */
    public function add(string $type): self
    {
        // merge options and attributes
        if (!empty($this->attributes)) {
            $this->options['attr'] = $this->attributes;
        }
        if (!empty($this->rowAttributes)) {
            $this->options['row_attr'] = $this->rowAttributes;
        }
        if (!empty($this->helpAttributes)) {
            $this->options['help_attr'] = $this->helpAttributes;
        }
        if (!empty($this->labelAttributes)) {
            $this->options['label_attr'] = $this->labelAttributes;
        }

        // add
        $this->builder->add((string) $this->field, $type, $this->options);

        return $this->reset();
    }

    /**
     * Add a birthday type to the builder and reset all values to default.
     */
    public function addBirthdayType(): self
    {
        return $this->updateOption('widget', 'single_text')
            ->add(BirthdayType::class);
    }

    /**
     * Add a checkbox input to confirm an operation.
     *
     * @param bool $disabled true if the checkbox must be disabled
     */
    public function addCheckboxConfirm(?TranslatorInterface $translator, bool $disabled): self
    {
        return $this->field('confirm')
            ->label('simulate.confirm')
            ->updateAttributes([
                'data-error' => $translator?->trans('simulate.error'),
                'disabled' => $disabled ? 'disabled' : null,
            ])
            ->notMapped()
            ->addCheckboxType();
    }

    /**
     * Add a checkbox input to simulate an operation.
     */
    public function addCheckboxSimulate(): self
    {
        return $this->field('simulate')
            ->label('simulate.label')
            ->help('simulate.help')
            ->helpClass('ml-4')
            ->notRequired()
            ->addCheckboxType();
    }

    /**
     * Add a checkbox type to the builder and reset all values to default.
     *
     * @param bool $switchStyle true to render the checkbox with the toggle switch style
     */
    public function addCheckboxType(bool $switchStyle = true): self
    {
        if ($switchStyle) {
            $this->labelClass('switch-custom');
        }

        return $this->add(CheckboxType::class);
    }

    /**
     * Adds a choice type to the builder and reset all values to default.
     *
     * @param array $choices an array, where the array key is the item's label and the array value is the item's value
     */
    public function addChoiceType(array $choices): self
    {
        return $this->updateOption('choices', $choices)
            ->add(ChoiceType::class);
    }

    /**
     * Add a collection type to the builder with the given entry type and reset all values to default.
     *
     * @param string $entryType    the entry type class, must be a subclass of FormTypeInterface class
     * @param bool   $allow_add    true to allow user to add a new entry
     * @param bool   $allow_delete true to allow user to delete an entry
     *
     * @throws UnexpectedValueException if the entry type is not an instance of FormTypeInterface class
     */
    public function addCollectionType(string $entryType, bool $allow_add = true, bool $allow_delete = true): self
    {
        if (!\is_a($entryType, FormTypeInterface::class, true)) {
            throw new UnexpectedValueException($entryType, FormTypeInterface::class);
        }

        return $this->updateOptions([
                'entry_type' => $entryType,
                'entry_options' => ['label' => false],
                'allow_delete' => $allow_delete,
                'allow_add' => $allow_add,
                'by_reference' => false,
                'label' => false,
            ])->add(CollectionType::class);
    }

    /**
     * Add a color type to the builder and reset all values to default.
     *
     * @param bool $colorPicker true to wrap widget to a color-picker
     */
    public function addColorType(bool $colorPicker = true): self
    {
        if ($colorPicker) {
            $this->widgetClass('color-picker');
        }

        return $this->add(ColorType::class);
    }

    /**
     * Add a current password type to the builder and reset all values to default.
     */
    public function addCurrentPasswordType(): self
    {
        return $this->add(CurrentPasswordType::class);
    }

    /**
     * Add a date type to the builder and reset all values to default.
     */
    public function addDateType(): self
    {
        return $this->updateOption('widget', 'single_text')
            ->add(DateType::class);
    }

    /**
     * Add an email type to the builder and reset all values to default.
     */
    public function addEmailType(): self
    {
        return $this->updateAttribute('inputmode', 'email')
            ->add(EmailType::class);
    }

    /**
     * Add an enum type to the builder and reset all values to default.
     *
     * @param string $class the enumeration class
     *
     * @psalm-template T of \UnitEnum
     *
     * @psalm-param class-string<T> $class
     */
    public function addEnumType(string $class): self
    {
        $this->updateOption('class', $class);
        if (\is_a($class, SortableEnumInterface::class, true)) {
            $this->updateOption('choices', $class::sorted());
        }
        if (\is_a($class, ReadableEnumInterface::class, true)) {
            return $this->add(ElaoEnumType::class);
        }

        return $this->add(EnumType::class);
    }

    /**
     * Adds an event listener to an event on this form builder.
     *
     * @param string   $eventName the event name
     * @param callable $listener  the event listener
     * @param int      $priority  The priority of the listener. Listeners
     *                            with a higher priority are called before
     *                            listeners with a lower priority.
     *
     * @psalm-suppress ArgumentTypeCoercion
     * @psalm-suppress MixedArgumentTypeCoercion
     */
    public function addEventListener(string $eventName, callable $listener, int $priority = 0): self
    {
        $this->builder->addEventListener($eventName, $listener, $priority);

        return $this;
    }

    /**
     * Add a fax (telephone) type to the builder and reset all values to default.
     */
    public function addFaxType(string $pattern = null): self
    {
        return $this->updateAttribute('pattern', $pattern)
            ->add(FaxType::class);
    }

    /**
     * Add a file type to the builder and reset all values to default.
     */
    public function addFileType(): self
    {
        return $this->add(FileType::class);
    }

    /**
     * Add a flag bag enum type to the builder and reset all values to default.
     */
    public function addFlagBagType(string $class): self
    {
        return $this->updateOption('class', $class)
            ->add(FlagBagType::class);
    }

    /**
     * Add a hidden type to the builder and reset all values to default.
     */
    public function addHiddenType(): self
    {
        return $this->add(HiddenType::class);
    }

    /**
     * Add a money type to the builder and reset all values to default.
     */
    public function addMoneyType(): self
    {
        return $this->updateAttribute('inputmode', 'decimal')
            ->updateOption('currency', 'CHF')
            ->updateOption('html5', true)
            ->widgetClass('text-right')
            ->add(MoneyType::class);
    }

    /**
     * Add a number type to the builder and reset all values to default.
     *
     * @param int $scale the number of decimals to set
     */
    public function addNumberType(int $scale = 2): self
    {
        $input_mode = $scale > 0 ? 'decimal' : 'numeric';

        return $this->widgetClass('text-right')
            ->updateAttribute('inputmode', $input_mode)
            ->updateAttribute('scale', $scale)
            ->updateOption('html5', true)
            ->add(NumberType::class);
    }

    /**
     * Add a percent type to the builder and reset all values to default.
     *
     * @param int   $min  the minimum value allowed (inclusive) or <code>PHP_INT_MIN</code> if none
     * @param int   $max  the maximum value allowed (inclusive) or <code>PHP_INT_MAX</code> if none
     * @param float $step the step increment or -1 if none
     */
    public function addPercentType(int $min = \PHP_INT_MIN, int $max = \PHP_INT_MAX, float $step = 1.0): self
    {
        $this->widgetClass('text-right')
            ->updateAttribute('inputmode', 'decimal')
            ->updateOption('rounding_mode', \NumberFormatter::ROUND_HALFUP)
            ->updateOption('html5', true)
            ->autocomplete('off');

        if (\PHP_INT_MIN !== $min) {
            $this->updateAttribute('min', $min);
        }
        if (\PHP_INT_MAX !== $max) {
            $this->updateAttribute('max', $max);
        }
        if (-1 !== (int) $step) {
            $this->updateAttribute('step', $step);
        }

        return $this->add(PercentType::class);
    }

    /**
     * Add a plain type to the builder and reset all values to default.
     * This type just renders the field as a span tag. This is useful for
     * forms where certain field need to be shown but not editable.
     *
     *  @param bool $expanded true to render the plain type within the label
     */
    public function addPlainType(bool $expanded = false): self
    {
        if ($expanded) {
            $this->updateOption('expanded', true);
        }

        return $this->notRequired()->add(PlainType::class);
    }

    /**
     * Adds a post-set-data-submit event listener.
     * The FormEvents::POST_SET_DATA event is dispatched at the end of the Form::setData() method.
     *
     * @param callable $listener the event listener
     * @param int      $priority The priority of the listener. Listeners
     *                           with a higher priority are called before
     *                           listeners with a lower priority.
     */
    public function addPostSetDataListener(callable $listener, int $priority = 0): self
    {
        return $this->addEventListener(FormEvents::POST_SET_DATA, $listener, $priority);
    }

    /**
     * Adds a post-submit event listener.
     * The FormEvents::POST_SUBMIT event is dispatched at the very end of the Form::submit().
     *
     * @param callable $listener the event listener
     * @param int      $priority The priority of the listener. Listeners
     *                           with a higher priority are called before
     *                           listeners with a lower priority.
     */
    public function addPostSubmitListener(callable $listener, int $priority = 0): self
    {
        return $this->addEventListener(FormEvents::POST_SUBMIT, $listener, $priority);
    }

    /**
     * Adds a pre-set-data event listener.
     *
     * The FormEvents::PRE_SET_DATA event is dispatched at the beginning of the Form::setData() method.
     *
     * @param callable $listener the event listener
     * @param int      $priority The priority of the listener. Listeners
     *                           with a higher priority are called before
     *                           listeners with a lower priority.
     */
    public function addPreSetDataListener(callable $listener, int $priority = 0): self
    {
        return $this->addEventListener(FormEvents::PRE_SET_DATA, $listener, $priority);
    }

    /**
     * Adds a pre-submit event listener.
     *
     * The PRE_SUBMIT event is dispatched at the beginning of the Form::submit() method.
     *
     * @param callable $listener the event listener
     * @param int      $priority The priority of the listener. Listeners
     *                           with a higher priority are called before
     *                           listeners with a lower priority.
     */
    public function addPreSubmitListener(callable $listener, int $priority = 0): self
    {
        return $this->addEventListener(FormEvents::PRE_SUBMIT, $listener, $priority);
    }

    /**
     * Add a repeat password type to the builder and reset all values to default.
     *
     * @param string $passwordLabel the label used for the password
     * @param string $confirmLabel  the label used for the confirmation password
     */
    public function addRepeatPasswordType(string $passwordLabel = RepeatPasswordType::PASSWORD_LABEL, string $confirmLabel = RepeatPasswordType::CONFIRM_LABEL): self
    {
        if (RepeatPasswordType::PASSWORD_LABEL !== $passwordLabel) {
            $first_options = \array_replace_recursive(
                RepeatPasswordType::getPasswordOptions(),
                ['label' => $passwordLabel]
            );
            $this->updateOption('first_options', $first_options);
        }
        if (RepeatPasswordType::CONFIRM_LABEL !== $confirmLabel) {
            $second_options = \array_replace_recursive(
                RepeatPasswordType::getConfirmOptions(),
                ['label' => $confirmLabel]
            );
            $this->updateOption('second_options', $second_options);
        }

        return $this->add(RepeatPasswordType::class);
    }

    /**
     * Adds a submit event listener.
     *
     * The SUBMIT event is dispatched after the Form::submit() method
     * has changed the view data by the request data.
     *
     * @param callable $listener the event listener
     * @param int      $priority The priority of the listener. Listeners
     *                           with a higher priority are called before
     *                           listeners with a lower priority.
     */
    public function addSubmitListener(callable $listener, int $priority = 0): self
    {
        return $this->addEventListener(FormEvents::SUBMIT, $listener, $priority);
    }

    /**
     * Add a telephone type to the builder and reset all values to default.
     */
    public function addTelType(string $pattern = null): self
    {
        return $this->updateAttribute('inputmode', 'tel')
            ->updateAttribute('pattern', $pattern)
            ->add(TelType::class);
    }

    /**
     * Add a text area type to the builder and reset all values to default.
     */
    public function addTextareaType(int $rows = 2): self
    {
        return $this->updateAttribute('rows', $rows)
            ->widgetClass('resizable')
            ->add(TextareaType::class);
    }

    /**
     * Add a text type to the builder and reset all values to default.
     */
    public function addTextType(): self
    {
        return $this->add(TextType::class);
    }

    /**
     * Add an Url type to the builder and reset all values to default.
     *
     * @param ?string $default_protocol If a value is submitted that doesn't begin with some protocol (e.g. http://, ftp://, etc), this protocol will be prepended to the string when the data is submitted to the form.
     */
    public function addUrlType(?string $default_protocol = 'https'): self
    {
        return $this->updateOption('default_protocol', $default_protocol, true)
            ->updateAttribute('inputmode', 'url')
            ->add(UrlType::class);
    }

    /**
     * Adds a Vich image type and reset all values to default.
     */
    public function addVichImageType(): self
    {
        // see https://github.com/kartik-v/bootstrap-fileinput
        $this->notRequired()
            ->updateRowAttribute('class', 'mb-0')
            ->updateOptions([
                'translation_domain' => 'messages',
                'download_uri' => false,
            ])->updateAttributes([
                'accept' => 'image/gif,image/jpeg,image/png,image/bmp',
                'title' => '',
            ]);

        // labels
        if (!isset($this->options['delete_label'])) {
            $this->updateOption('delete_label', false);
        }

        return $this->add(VichImageType::class);
    }

    /**
     * Add a Yes/No choice type to the builder and reset all values to default.
     */
    public function addYesNoType(): self
    {
        return $this->add(YesNoType::class);
    }

    /**
     * Sets the autocomplete attribute.
     *
     * For Google Chrome, if You want to disable the auto-complete set a random string as attribute like 'nope'.
     *
     * @param bool|string $autocomplete the autocomplete ('on'/'off') or false to remove
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTML/Attributes/autocomplete
     */
    public function autocomplete(bool|string $autocomplete): self
    {
        $autocomplete = empty($autocomplete) ? null : $autocomplete;

        return $this->updateAttribute('autocomplete', $autocomplete);
    }

    /**
     * Sets auto-focus attribute.
     */
    public function autofocus(): self
    {
        return $this->updateAttribute('autofocus', true);
    }

    /**
     * Sets the constraints option.
     */
    public function constraints(Constraint ...$constrains): self
    {
        if (1 === \count($constrains)) {
            $constrains = \reset($constrains);
        }

        return $this->updateOption('constraints', $constrains);
    }

    /**
     * Creates the form within the underlying form builder.
     *
     * @return FormInterface the form
     *
     * @see FormBuilderInterface::getForm()
     * @see FormHelper::createView()
     */
    public function createForm(): FormInterface
    {
        return $this->builder->getForm();
    }

    /**
     * Create the form view.
     *
     * @return FormView the form view
     *
     * @see FormInterface::createView()
     * @see FormHelper::createForm()
     */
    public function createView(FormView $parent = null): FormView
    {
        return $this->createForm()->createView($parent);
    }

    /**
     * Sets the disabled property to true.
     */
    public function disabled(): self
    {
        return $this->updateOption('disabled', true);
    }

    /**
     * Sets the translation domain.
     *
     * @param ?string $domain the translation domain or null for default
     */
    public function domain(?string $domain): self
    {
        $domain = empty($domain) ? null : $domain;

        return $this->updateOption('translation_domain', $domain);
    }

    /**
     * Sets the field name property.
     *
     * If the label prefix is defined, the label is added automatically.
     *
     * @param string $field the field name
     */
    public function field(string $field): self
    {
        $this->field = $field;

        // add label if applicable
        if (null !== $this->labelPrefix && !\in_array('label_format', $this->options, true)) {
            return $this->label($this->labelPrefix . $field);
        }

        return $this;
    }

    /**
     * Gets the form builder.
     */
    public function getBuilder(): FormBuilderInterface
    {
        return $this->builder;
    }

    /**
     * Sets the help property.
     *
     * @param ?string $help the help identifier to translate
     */
    public function help(?string $help): self
    {
        $help = empty($help) ? null : $help;

        return $this->updateOption('help', $help);
    }

    /**
     * Add a class name to the help class attributes.
     *
     * @param string $name one or more space-separated classes to be added to the help class attribute
     */
    public function helpClass(string $name): self
    {
        return $this->addClasses($this->helpAttributes, $name);
    }

    /**
     * Sets the help parameters.
     *
     * @param array $parameters the help parameters
     */
    public function helpParameters(array $parameters): self
    {
        return $this->updateOption('help_translation_parameters', $parameters);
    }

    /**
     * Hides the label.
     */
    public function hideLabel(): self
    {
        return $this->updateOption('label', false);
    }

    /**
     * Sets the label property.
     *
     * @param ?string $label the label identifier to translate
     */
    public function label(?string $label): self
    {
        $label = empty($label) ? null : $label;

        return $this->updateOption('label_format', $label);
    }

    /**
     * Add a class name to the label class attributes.
     *
     * @param string $name one or more space-separated classes to be added to the label class attribute
     */
    public function labelClass(string $name): self
    {
        return $this->addClasses($this->labelAttributes, $name);
    }

    /**
     * Sets the maximum length.
     *
     * @param int $maxLength the maximum length or 0 if none
     */
    public function maxLength(int $maxLength): self
    {
        return $this->updateAttribute('maxLength', $maxLength > 0 ? $maxLength : null);
    }

    /**
     * Sets the minimum length.
     *
     * @param int $minLength the minimum length or 0 if none
     */
    public function minLength(int $minLength): self
    {
        return $this->updateAttribute('minLength', $minLength > 0 ? $minLength : null);
    }

    /**
     * Sets the mapped property to false.
     *
     * Used if you wish the field to be ignored when reading or writing to the object.
     */
    public function notMapped(): self
    {
        return $this->updateOption('mapped', false);
    }

    /**
     * Sets the required property to false.
     */
    public function notRequired(): self
    {
        return $this->updateOption('required', false);
    }

    /**
     * Sets the percent symbol visibility.
     *
     * @param bool $visible true to display the percent symbol; false to hide
     */
    public function percent(bool $visible): self
    {
        return $this->updateOption('symbol', $visible ? FormatUtils::getPercent() : false);
    }

    /**
     * Sets the priority.
     *
     * @param int $priority the priority to set. Fields with higher priorities are rendered first and fields with same priority are rendered in their original order.
     */
    public function priority(int $priority): self
    {
        return $this->updateOption('priority', $priority);
    }

    /**
     * Sets the read-only property to true.
     */
    public function readonly(): self
    {
        return $this->updateAttribute('readonly', true);
    }

    /**
     * Reset all options and attributes to the default values.
     */
    public function reset(): self
    {
        $this->options = [];
        $this->attributes = [];
        $this->rowAttributes = [];
        $this->helpAttributes = [];
        $this->labelAttributes = [];

        return $this;
    }

    /**
     * Add a class name to the row class attributes.
     *
     * @param string $name one or more space-separated classes to be added to the row class attribute
     */
    public function rowClass(string $name): self
    {
        return $this->addClasses($this->rowAttributes, $name);
    }

    /**
     * Sets the tab index.
     *
     * @param ?int $index the index or null to remove
     */
    public function tabindex(?int $index): self
    {
        $index = \is_int($index) ? $index : null;

        return $this->updateAttribute('tabIndex', $index);
    }

    /**
     * Updates an attribute.
     *
     * @param string $name  the attribute name
     * @param mixed  $value the attribute value or null to remove
     * @param bool   $force true to put the attribute, even if the value is null
     */
    public function updateAttribute(string $name, mixed $value, bool $force = false): self
    {
        return $this->updateEntry($this->attributes, $name, $value, $force);
    }

    /**
     * Update attributes.
     *
     * @param array<string, mixed> $attributes the attribute's name and value
     * @param bool                 $force      true to put the option, even if the value is null
     */
    public function updateAttributes(array $attributes, bool $force = false): self
    {
        /** @psalm-var mixed $value */
        foreach ($attributes as $name => $value) {
            $this->updateAttribute($name, $value, $force);
        }

        return $this;
    }

    /**
     * Updates a help attribute.
     *
     * @param string $name  the attribute name
     * @param mixed  $value the attribute value
     * @param bool   $force true to put the attribute, even if the value is null
     */
    public function updateHelpAttribute(string $name, mixed $value, bool $force = false): self
    {
        return $this->updateEntry($this->helpAttributes, $name, $value, $force);
    }

    /**
     * Updates a label attribute.
     *
     * @param string $name  the attribute name
     * @param mixed  $value the attribute value
     * @param bool   $force true to put the attribute, even if the value is null
     */
    public function updateLabelAttribute(string $name, mixed $value, bool $force = false): self
    {
        return $this->updateEntry($this->labelAttributes, $name, $value, $force);
    }

    /**
     * Updates an option.
     *
     * @param string $name  the option name
     * @param mixed  $value the option value
     * @param bool   $force true to put the option, even if the value is null
     */
    public function updateOption(string $name, mixed $value, bool $force = false): self
    {
        return $this->updateEntry($this->options, $name, $value, $force);
    }

    /**
     * Update options.
     *
     * @param array<string, mixed> $options the option's name and value
     * @param bool                 $force   true to put the option, even if the value is null
     */
    public function updateOptions(array $options, bool $force = false): self
    {
        /** @psalm-var mixed $value */
        foreach ($options as $name => $value) {
            $this->updateOption($name, $value, $force);
        }

        return $this;
    }

    /**
     * Updates a row attribute.
     *
     * @param string $name  the attribute name
     * @param mixed  $value the attribute value
     * @param bool   $force true to put the attribute, even if the value is null
     */
    public function updateRowAttribute(string $name, mixed $value, bool $force = false): self
    {
        return $this->updateEntry($this->rowAttributes, $name, $value, $force);
    }

    /**
     * Add a class name to the widget class attribute.
     *
     * @param string $name one or more space-separated classes to be added to the widget class attribute
     */
    public function widgetClass(string $name): self
    {
        return $this->addClasses($this->attributes, $name);
    }

    /**
     * Add one or more classes.
     *
     * @param array<string, mixed> $array the array attributes where to find and update existing classes
     * @param string               $name  one or more space-separated classes to add
     */
    private function addClasses(array &$array, string $name): self
    {
        if ('' === \trim($name)) {
            return $this;
        }

        /** @var string $existing */
        $existing = $array['class'] ?? '';
        $newValues = \array_filter(\explode(' ', $name));
        $oldValues = \array_filter(\explode(' ', $existing));
        $className = \implode(' ', \array_unique([...$oldValues, ...$newValues]));

        return $this->updateEntry($array, 'class', empty($className) ? null : $className, false);
    }

    /**
     * Update an entry in the given array.
     *
     * @param array<string, mixed> $array the array to update
     * @param string               $name  the entry name
     * @param mixed                $value the entry value
     * @param bool                 $force true to put the entry, even if the value is null
     *
     * @psalm-suppress MixedAssignment
     */
    private function updateEntry(array &$array, string $name, mixed $value, bool $force): self
    {
        if (null !== $value || $force) {
            $array[$name] = $value;
        } else {
            unset($array[$name]);
        }

        return $this;
    }
}
