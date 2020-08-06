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

namespace App\Util;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Utility class for strings and sort.
 *
 * @author Laurent Muller
 */
final class Utils
{
    // prevent instance creation
    private function __construct()
    {
        // no-op
    }

    /**
     * Applies the callback to the keys and elements of the given array.
     * <p>
     * The callable function must by of type <code>function($key, $value)</code>.
     * </p>.
     *
     * @param callable $callback the callback function to run for each key and element in array
     * @param array    $array    an array to run through the callback function
     *
     * @return array an array containing the results of applying the callback function
     */
    public static function arrayMapKey(callable $callback, array $array): array
    {
        return \array_map($callback, \array_keys($array), $array);
    }

    /**
     * Iteratively reduce the array to a single value using a callback function.
     *
     * The callable function must by of type <code>function($carry, $key, $value)</code>.
     *
     * @param callable $callback the callback function
     * @param array    $array    the input array
     * @param mixed    $initial  the optional initial value. It will be used at the beginning of the process, or as a final result in case the array is empty
     *
     * @return mixed the resulting value
     */
    public static function arrayReduceKey(callable $callback, array $array, $initial = null)
    {
        return \array_reduce(\array_keys($array), function ($carry, $key) use ($callback, $array) {
            return $callback($carry, $key, $array[$key]);
        }, $initial);
    }

    /**
     * Capitalizes a string.
     *
     * The first character will be uppercase, all others lowercase:
     * <pre>
     * 'my first Car' => 'My first car'
     * </pre>
     *
     * @param string $string   the string to capitalize
     * @param string $encoding the character encoding
     *
     * @return string the capitalized string
     */
    public static function capitalize(string $string, string $encoding = 'UTF-8'): string
    {
        $first = \mb_strtoupper(\mb_substr($string, 0, 1, $encoding), $encoding);
        $other = \mb_strtolower(\mb_substr($string, 1, null, $encoding), $encoding);

        return $first . $other;
    }

    /**
     * Compare 2 values.
     *
     * @param mixed                     $a         the first object to get field value from
     * @param mixed                     $b         the second object to get field value from
     * @param string                    $field     the field name to get value for
     * @param PropertyAccessorInterface $accessor  the property accessor to get values
     * @param bool                      $ascending true to sort ascending, false to sort descending
     *
     * @return int -1 if the first value is less than the second value;
     *             1 if the second value is greater than the first value and
     *             0 if both values are equal.
     *             If $ascending if false, the result is inversed.
     */
    public static function compare($a, $b, string $field, PropertyAccessorInterface $accessor, bool $ascending = true): int
    {
        $result = 0;
        $valueA = $accessor->getValue($a, $field);
        $valueB = $accessor->getValue($b, $field);
        if (\is_string($valueA) && \is_string($valueB)) {
            $result = \strnatcasecmp($valueA, $valueB);
        } else {
            $result = $valueA <=> $valueB;
        }

        return $ascending ? $result : -$result;
    }

    /**
     * Tests if a substring is contained within a string.
     *
     * <b>NB:</b> If the needle is empty, this function return false.
     *
     * @param string $haystack   the string to search in
     * @param string $needle     the string to search for
     * @param bool   $ignorecase true for case-insensitive; false for case-sensitive
     *
     * @return bool true if substring is contained within a string
     */
    public static function contains(string $haystack, string $needle, bool $ignorecase = false): bool
    {
        if (empty($needle)) {
            return false;
        } elseif ($ignorecase) {
            return false !== \stripos($haystack, $needle);
        } else {
            return false !== \strpos($haystack, $needle);
        }
    }

    /**
     * Tests if a string ends within a substring.
     *
     * <b>NB:</b> If the needle is empty, this function return false.
     *
     * @param string $haystack   the string to search in
     * @param string $needle     the string to search for
     * @param bool   $ignorecase true for case-insensitive; false for case-sensitive
     *
     * @return bool true if ends with substring
     */
    public static function endwith(string $haystack, string $needle, bool $ignorecase = false): bool
    {
        if (empty($needle)) {
            return false;
        }

        $len = \strlen($needle);
        $pos = \strlen($haystack) - $len;
        if ($ignorecase) {
            return \stripos($haystack, $needle, -$len) === $pos;
        } else {
            return \strpos($haystack, $needle, -$len) === $pos;
        }
    }

    /**
     * Returns a parsable string representation of a variable.
     *
     * @param mixed $expression the variable to export
     *
     * @return string the variable representation
     */
    public static function exportVar($expression): ?string
    {
        try {
            $export = \var_export($expression, true);

            $searches = [
                '\\\\' => '\\',
                '\'' => '"',
                ',' => '',
            ];
            $export = \str_replace(\array_keys($searches), \array_values($searches), $export);

            $patterns = [
                "/array \(/" => '[',
                "/^([ ]*)\)(,?)$/m" => '$1]$2',
                "/=>[ ]?\n[ ]+\[/" => '=> [',
                "/([ ]*)(\'[^\']+\') => ([\[\'])/" => '$1$2 => $3',
            ];
            $export = \preg_replace(\array_keys($patterns), \array_values($patterns), $export);

            return $export;
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Returns the first matching item in the given array.
     *
     * <p>
     * The callable function must by of type <code>function($value): bool</code>.
     * </p>
     *
     * @param array    $array    the array to search in
     * @param callable $callback the filter callback
     *
     * @return mixed|null the first matching item, if any; null otherwise
     */
    public static function findFirst(array $array, callable $callback)
    {
        foreach ($array as $value) {
            if ($callback($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Create a property accessor.
     */
    public static function getAccessor(): PropertyAccessorInterface
    {
        return PropertyAccess::createPropertyAccessor();
    }

    /**
     * Gets the value in an array for the given key.
     *
     * @param array $array   the array to search in
     * @param mixed $key     the key to search for
     * @param mixed $default the default value if the key is not found
     *
     * @return mixed the value, if found; the default value otherwise
     */
    public static function getArrayValue(array $array, $key, $default = null)
    {
        return $array[$key] ?? $default;
    }

    /**
     * Gets the short class name of the given variable.
     *
     * @param mixed $var either a string containing the name of the class to reflect, or an object
     *
     * @return string|null the short name or null if the variable is null
     *
     * @throws \ReflectionException if the class to reflect does not exist
     */
    public static function getShortName($var): ?string
    {
        if ($var) {
            return (new \ReflectionClass($var))->getShortName();
        }

        return null;
    }

    /**
     * Groups an array by a given key.
     *
     * Any additional keys (if any) will be used for grouping the next set of sub-arrays.
     *
     * @param array                    $array the array to be grouped
     * @param string|int|callable|null $key   a set of keys to group by
     */
    public static function groupBy(array $array, $key): array
    {
        // check key
        if (!\is_string($key) && !\is_int($key) && !\is_callable($key)) { // && !\is_float($key)
            \trigger_error('groupBy(): The key should be a string, an integer or a function', E_USER_ERROR);
        }
        $isFunction = \is_callable($key);

        // load the new array, splitting by the target key
        $grouped = [];
        foreach ($array as $value) {
            $groupKey = null;
            if ($isFunction) {
                $groupKey = $key($value);
            } elseif (\is_object($value)) {
                $groupKey = $value->{$key};
            } else {
                $groupKey = $value[$key];
            }
            $grouped[$groupKey][] = $value;
        }

        // Recursively build a nested grouping if more parameters are supplied
        // Each grouped array value is grouped according to the next sequential key
        if (\func_num_args() > 2) {
            $args = \func_get_args();
            $callback = [__CLASS__, __FUNCTION__];
            foreach ($grouped as $groupKey => $value) {
                $params = \array_merge([$value], \array_slice($args, 2, \func_num_args()));
                $grouped[$groupKey] = \call_user_func_array($callback, $params);
            }
        }

        return $grouped;
    }

    /**
     * Returns if the given variable is a string and not empty.
     *
     * @param mixed $var the variable to be tested
     *
     * @return bool true if not empty string
     */
    public static function isString($var): bool
    {
        return \is_string($var) && 0 !== \strlen($var);
    }

    /**
     * Sorts an array for the given fields.
     *
     * @param array  $array     the array to sort
     * @param string $field     the field name to get values for
     * @param bool   $ascending true to sort ascending, false to sort descending
     */
    public static function sortField(array &$array, string $field, bool $ascending = true): void
    {
        $accessor = self::getAccessor();
        \usort($array, function ($a, $b) use ($accessor, $field, $ascending) {
            return self::compare($a, $b, $field, $accessor, $ascending);
        });
    }

    /**
     * Sorts an array for the given fields.
     *
     * @param array           $array     the array to sort
     * @param string|string[] $fields    the array of field names to get values for
     * @param bool            $ascending true to sort ascending, false to sort descending
     */
    public static function sortFields(array &$array, $fields, bool $ascending = true): void
    {
        if (!\is_array($fields)) {
            $fields = [$fields];
        }

        $accessor = self::getAccessor();
        \usort($array, function ($a, $b) use ($accessor, $fields, $ascending) {
            $result = 0;
            foreach ($fields as $field) {
                $result = self::compare($a, $b, $field, $accessor, $ascending);
                if (0 !== $result) {
                    break;
                }
            }

            return $result;
        });
    }

    /**
     * Tests if a string starts within a substring.
     *
     * <b>NB:</b> If the needle is empty, this function return false.
     *
     * @param string $haystack   the string to search in
     * @param string $needle     the string to search for
     * @param bool   $ignorecase true for case-insensitive; false for case-sensitive
     *
     * @return bool true if starts with substring
     */
    public static function startwith(string $haystack, string $needle, bool $ignorecase = false): bool
    {
        if (empty($needle)) {
            return false;
        } elseif ($ignorecase) {
            return 0 === \stripos($haystack, $needle);
        } else {
            return 0 === \strpos($haystack, $needle);
        }
    }

    /**
     * Create temporary file with an unique name.
     *
     * @param string $prefix the prefix of the generated temporary file name
     *
     * @return string|null the new temporary file name (with path), or null on failure
     */
    public static function tempfile(string $prefix = 'tmp'): ?string
    {
        $tempName = \tempnam(\sys_get_temp_dir(), $prefix);

        return false === $tempName ? null : $tempName;
    }

    /**
     * Ensure that the given variable is a float.
     *
     * @param mixed $var the variable to cast
     *
     * @return float the variable as float
     */
    public static function toFloat($var): float
    {
        return \is_float($var) ? $var : (float) $var;
    }

    /**
     * Ensure that the given variable is an integer.
     *
     * @param mixed $var the variable to cast
     *
     * @return int the variable as integer
     */
    public static function toInt($var): int
    {
        return \is_int($var) ? $var : (int) $var;
    }

    /**
     * Ensure that the given variable is a string.
     *
     * @param mixed $var the variable to cast
     *
     * @return string the variable as string
     */
    public static function toString($var): string
    {
        return \is_string($var) ? $var : (string) $var;
    }

    /**
     * Translate the given role.
     *
     * @param TranslatorInterface $translator the translator
     * @param string              $role       the role name
     *
     * @return string the translated role
     */
    public static function translateRole(TranslatorInterface $translator, string $role): string
    {
        $role = \strtolower(\str_replace('ROLE_', 'user.roles.', $role));

        return $translator->trans($role);
    }

    /**
     * Deletes a file.
     *
     * @param string|\SplFileInfo $file the file to delete
     *
     * @return bool true on success or false on failure
     */
    public static function unlink($file): bool
    {
        if ($file instanceof \SplFileInfo) {
            $file = $file->getRealPath();
        }
        if (\is_string($file) && \is_file($file)) {
            return \unlink($file);
        }

        return false;
    }
}
