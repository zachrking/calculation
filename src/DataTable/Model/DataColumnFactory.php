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

namespace App\DataTable\Model;

use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * Factory to create instances of {@link DataColumn}.
 *
 * @author Laurent Muller
 */
class DataColumnFactory
{
    /**
     * Creates a new instance for drop-down menu actions.
     *
     * @param string|callable|null $formatter the column formatter
     * @param string               $name      the field name
     */
    public static function actions($formatter, string $name = 'id'): DataColumn
    {
        return self::instance($name, 'actions rowlink-skip d-print-none')
            ->setTitle('common.empty')
            ->setFormatter($formatter)
            ->setSearchable(false)
            ->setOrderable(false)
            ->setRawData(true);
    }

    /**
     * Creates a new instance with the 'text-currency' class.
     *
     * @param string $name the field name
     */
    public static function currency(string $name): DataColumn
    {
        return self::instance($name, 'text-currency');
    }

    /**
     * Creates a new instance with the 'text-date' class.
     *
     * @param string $name the field name
     */
    public static function date(string $name): DataColumn
    {
        return self::instance($name, 'text-date');
    }

    /**
     * Creates a new instance with the 'text-date-time' class.
     *
     * @param string $name the field name
     */
    public static function dateTime(string $name): DataColumn
    {
        return self::instance($name, 'text-date-time');
    }

    /**
     * Creates data columns from the given JSON definitions.
     *
     * @param AbstractDataTable $parent the datatable owner
     * @param string            $path   the path to the JSON file definitions
     *
     * @return DataColumn[] the column definitions
     *
     * @throws \InvalidArgumentException if the definitions can not be parsed
     */
    public static function fromJson(AbstractDataTable $parent, string $path): array
    {
        //file?
        if (!\file_exists($path) || !\is_file($path)) {
            throw new \InvalidArgumentException("The file '$path' can not be found.");
        }

        // get content
        if (false === $json = \file_get_contents($path)) {
            throw new \InvalidArgumentException("Unable to get content of the file '$path'.");
        }

        // decode
        $definitions = \json_decode($json, true);
        if (JSON_ERROR_NONE !== \json_last_error()) {
            $message = \json_last_error_msg();
            throw new \InvalidArgumentException("Unable to decode the content of the file '$path' ($message).");
        }

        // definitions?
        if (empty($definitions)) {
            throw new \InvalidArgumentException("The file '$path' does not contain any definition.");
        }

        // accessor
        $accessor = PropertyAccess::createPropertyAccessor();

        // map
        return \array_map(function (array $definition) use ($parent, $accessor): DataColumn {
            $column = self::instance();
            foreach ($definition as $key => $value) {
                // special case for the formatter
                if ('formatter' === $key) {
                    $value = [$parent, $value];
                }
                $accessor->setValue($column, $key, $value);
            }

            return $column;
        }, $definitions);
    }

    /**
     * Creates a new instance with the visible, searchable and orderable properties set to false.
     *
     * @param string $name the field name
     */
    public static function hidden(string $name): DataColumn
    {
        return self::instance($name, 'd-none')
            ->setSearchable(false)
            ->setOrderable(false)
            ->setVisible(false);
    }

    /**
     * Creates a new instance with the identifier 'text-id' class.
     *
     * @param string $name the field name
     */
    public static function identifier(string $name): DataColumn
    {
        return self::instance($name, 'text-id');
    }

    /**
     * Creates a new instance.
     *
     * @param string $name  the field name
     * @param string $class the cell class name
     */
    public static function instance(string $name = null, string $class = null): DataColumn
    {
        return new DataColumn($name, $class);
    }

    /**
     * Creates a new instance with the 'text-percent' class.
     *
     * @param string $name the field name
     */
    public static function percent(string $name): DataColumn
    {
        return self::instance($name, 'text-percent');
    }

    /**
     * Creates a new instance with the 'text-unit' class.
     *
     * @param string $name the field name
     */
    public static function unit(string $name): DataColumn
    {
        return self::instance($name, 'text-unit');
    }
}