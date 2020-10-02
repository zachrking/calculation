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

namespace App\Calendar;

use App\Util\DateUtils;

/**
 * Represents a calendar for a specified year.
 *
 * @author Laurent Muller
 */
class Calendar extends CalendarItem implements MonthsInterface, WeekDaysInterface
{
    use DaysTrait;
    use ModelTrait;

    /**
     * The default day model class.
     */
    public const DEFAULT_DAY_MODEL = Day::class;

    /**
     * The default month model class.
     */
    public const DEFAULT_MONTH_MODEL = Month::class;

    /**
     * The default week model class.
     */
    public const DEFAULT_WEEK_MODEL = Week::class;

    /**
     * The day model class.
     *
     * @var string
     */
    protected $dayModel = self::DEFAULT_DAY_MODEL;

    /**
     * The month model class.
     *
     * @var string
     */
    protected $monthModel = self::DEFAULT_MONTH_MODEL;

    /**
     * The full month names.
     *
     * @var string[]
     */
    protected $monthNames;

    /**
     * Array with instances of Month objects.
     *
     * @var Month[]
     */
    protected $months;

    /**
     * The short month names.
     *
     * @var string[]
     */
    protected $monthShortNames;

    /**
     * The today day.
     *
     * @var Day
     */
    protected $today;

    /**
     * The week model class.
     *
     * @var string
     */
    protected $weekModel = self::DEFAULT_WEEK_MODEL;

    /**
     * The full name of the week days.
     *
     * @var string[]
     */
    protected $weekNames;

    /**
     * Array with instances of Week objects.
     *
     * @var Week[]
     */
    protected $weeks;

    /**
     * The short name of the week days.
     *
     * @var string[]
     */
    protected $weekShortNames;

    /**
     * Year for calendar.
     *
     * @var int
     */
    protected $year;

    /**
     * Constructor.
     *
     * @param int $year the year to generate
     */
    public function __construct(?int $year = null)
    {
        parent::__construct($this);

        // today
        $date = new \DateTime();
        $date = $date->setTime(0, 0, 0, 0);
        $this->today = new Day($this, $date);

        // names
        $this->initNames();

        // generate if applicable
        if ($year) {
            $this->generate($year);
        }
    }

    /**
     * Generates months, weeks and days for the given year.
     *
     * @param int $year the year to generate
     */
    public function generate(int $year): self
    {
        // check year
        $this->year = DateUtils::completYear($year);

        // clean
        $this->reset();

        // calculate first and last days of year
        $oneDayInterval = new \DateInterval('P1D');
        $firstYearDate = \DateTime::createFromFormat('d.m.Y H:i:s', \sprintf('01.01.%s 00:00:00', $year));
        $lastYearDate = (clone $firstYearDate)
            ->add(new \DateInterval('P1Y'))
            ->sub($oneDayInterval);

        // calculate first and last days in calendar.
        // It's monday on the 1st week and sunday on the last week
        /** @var \DateTime $firstDate */
        $firstDate = clone $firstYearDate;
        while (self::MONDAY !== (int) $firstDate->format('N')) {
            $firstDate = $firstDate->sub($oneDayInterval);
        }

        /** @var \DateTime $lastDate */
        $lastDate = clone $lastYearDate;
        while (self::SUNNDAY !== (int) $lastDate->format('N')) {
            $lastDate = $lastDate->add($oneDayInterval);
        }

        /** @var Week $currentWeek */
        $currentWeek = null;
        /** @var Month $currentMonth */
        $currentMonth = null;
        /** @var \DateTime $currentDate */
        $currentDate = clone $firstDate;

        // build calendar
        while ($currentDate <= $lastDate) {
            // create and add day
            $day = new $this->dayModel($this, $currentDate);
            $this->addDay($day);

            // calculate month and week numbers
            $monthNumber = (int) $currentDate->format('n');
            $monthYear = (int) $currentDate->format('Y');
            $weekNumber = (int) $currentDate->format('W');

            if ($monthYear === $this->year) {
                // create month if needed
                if (null === $currentMonth || $monthNumber !== $currentMonth->getNumber()) {
                    $currentMonth = $this->addMonth($monthNumber);
                }
                $currentMonth->addDay($day);
            }

            // create week if needed
            if (null === $currentWeek || $weekNumber !== $currentWeek->getNumber()) {
                $currentWeek = $this->addWeek($weekNumber);
            }
            $currentWeek->addDay($day);

            // next day
            $currentDate->add($oneDayInterval);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getKey(): string
    {
        return (string) $this->getYear();
    }

    /**
     * Gets the month for the given key.
     *
     * @param int|\DateTimeInterface|string $key the month key. Can be an integer (1 - 12), a date time interface or a formatted date ('n.Y').
     *
     * @return Month|null the month, if found, null otherwise
     */
    public function getMonth($key): ?Month
    {
        if ($key instanceof \DateTimeInterface) {
            $key = (int) $key->format('n');
        }

        if (\is_int($key)) {
            return $this->months[$key] ?? null;
        }

        if (\is_string($key)) {
            foreach ($this->months as $month) {
                if ($key === $month->getKey()) {
                    return $month;
                }
            }
        }

        return null;
    }

    /**
     * Gets the full name of the months.
     *
     * @return string[]
     */
    public function getMonthNames(): array
    {
        return $this->monthNames;
    }

    /**
     * Gets months where key is month number (1 - 12).
     *
     * @return Month[]
     */
    public function getMonths(): array
    {
        return $this->months;
    }

    /**
     * Gets the short name of the months.
     *
     * @return string[]
     */
    public function getMonthShortNames(): array
    {
        return $this->monthShortNames;
    }

    /**
     * This implementation returns the generated year.
     */
    public function getNumber(): int
    {
        return $this->getYear();
    }

    /**
     * {@inheritdoc}
     */
    public function getToday(): Day
    {
        return $this->today;
    }

    /**
     * Gets the week for the given key.
     *
     * @param int|\DateTimeInterface|string $key the week key. Can be an integer (1 - 53), a date time interface or a formatted date ('W.Y').
     *
     * @return Week|null the week, if found, null otherwise
     */
    public function getWeek($key): ?Week
    {
        if ($key instanceof \DateTimeInterface) {
            $key = (int) $key->format('W');
        }

        if (\is_int($key)) {
            return $this->weeks[$key] ?? null;
        }

        if (\is_string($key)) {
            foreach ($this->weeks as $week) {
                if ($key === $week->getKey()) {
                    return $week;
                }
            }
        }

        return null;
    }

    /**
     * Gets the full name of the week days.
     *
     * @return string[]
     */
    public function getWeekNames(): array
    {
        return $this->weekNames;
    }

    /**
     * Gets weeks.
     *
     * @return Week[]
     */
    public function getWeeks(): array
    {
        return $this->weeks;
    }

    /**
     * Gets the short name of the week days.
     *
     * @return string[]
     */
    public function getWeekShortNames(): array
    {
        return $this->weekShortNames;
    }

    /**
     * {@inheritdoc}
     */
    public function getYear(): int
    {
        return (int) $this->year;
    }

    /**
     * {@inheritdoc}
     */
    public function isCurrent(): bool
    {
        $today = $this->getToday();

        return $this->getYear() === $today->getYear();
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return [
            'year' => $this->year,
            'startDate' => $this->localeDate($this->getFirstDate()),
            'endDate' => $this->localeDate($this->getLastDate()),
        ];
    }

    /**
     * Sets the models.
     *
     * @param string|null $monthModel the month model class or null for default
     * @param string|null $weekModel  the week model class or null for default
     * @param string|null $dayModel   the day model class or null for default
     *
     * @throws CalendarException if the month, the week or the day class model does not exist
     */
    public function setModels(?string $monthModel = null, ?string $weekModel = null, ?string $dayModel = null): self
    {
        $this->monthModel = $this->checkClass($monthModel, self::DEFAULT_MONTH_MODEL);
        $this->weekModel = $this->checkClass($weekModel, self::DEFAULT_WEEK_MODEL);
        $this->dayModel = $this->checkClass($dayModel, self::DEFAULT_DAY_MODEL);

        return $this;
    }

    /**
     * Sets the full name of the months. The array must have 12 values and keys from 1 to 12.
     *
     * @param string[] $monthNames the month names to set
     *
     * @throws CalendarException if the array does not contains 12 values, if a key is missing or if one of the values is not a string
     */
    public function setMonthNames(array $monthNames): self
    {
        $this->monthNames = $this->checkArray($monthNames, self::MONTHS_COUNT);

        return $this;
    }

    /**
     * Sets the short name of the months. The array must have 12 values and keys from 1 to 12.
     *
     * @param string[] $monthShortNames the month short names to set
     *
     * @throws CalendarException if the array does not contains 12 values, if a key is missing or if one of the values is not a string
     */
    public function setMonthShortNames(array $monthShortNames): self
    {
        $this->monthShortNames = $this->checkArray($monthShortNames, self::MONTHS_COUNT);

        return $this;
    }

    /**
     * Sets the full name of the week days. The array must have 7 values and keys from 1 to 7.
     *
     * @param string[] $weekNames the week names to set
     *
     * @throws CalendarException if the array does not contains 7 values, if a key is missing or if one of the values is not a string
     */
    public function setWeekNames(array $weekNames): self
    {
        $this->weekNames = $this->checkArray($weekNames, self::DAYS_COUNT);

        return $this;
    }

    /**
     * Sets the short name of the week days. The array must have 7 values and keys from 1 to 7.
     *
     * @param string[] $weekShortNames the week short names to set
     *
     * @throws CalendarException if the array does not contains 7 values, if a key is missing or if one of the values is not a string
     */
    public function setWeekShortNames(array $weekShortNames): self
    {
        $this->weekShortNames = $this->checkArray($weekShortNames, self::DAYS_COUNT);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function reset(): void
    {
        parent::reset();
        $this->months = [];
        $this->weeks = [];
        $this->days = [];
    }

    /**
     * Create and add a month.
     *
     * @param int $index the month index (1 - 12)
     */
    private function addMonth(int $index): Month
    {
        $month = new $this->monthModel($this, $index);
        $this->months[$index] = $month;

        return $month;
    }

    /**
     * Create and add a week.
     *
     * @param int $index the week index (1 - 53)
     */
    private function addWeek(int $index): Week
    {
        $week = new $this->weekModel($this, $index);
        $this->weeks[$index] = $week;

        return $week;
    }

    /**
     * Checks if the given array has the given length and that all keys from 1 to length are present.
     *
     * @param array $array  the array to verify
     * @param int   $length the length to match
     *
     * @return array the given array
     *
     * @throws CalendarException if the array has the wrong length or if a key is missing or if one of the values is not a string
     */
    private function checkArray(array $array, int $length): array
    {
        if ($length !== \count($array)) {
            throw new CalendarException("The array must contains {$length} values.");
        }
        for ($i = 1; $i <= $length; ++$i) {
            if (!\array_key_exists($i, $array)) {
                throw new CalendarException("The array must contains the key {$i}.");
            }
            if (!\is_string($array[$i])) {
                throw new CalendarException("The value {$array[$i]} for the key {$i} must be a string.");
            }
        }

        return $array;
    }

    /**
     * Intialize the months and week days names.
     */
    private function initNames(): self
    {
        if (!$this->monthNames) {
            $this->monthNames = DateUtils::getMonths();
        }
        if (!$this->monthShortNames) {
            $this->monthShortNames = DateUtils::getShortMonths();
        }
        if (!$this->weekNames) {
            $this->weekNames = DateUtils::getWeekdays('monday');
        }
        if (!$this->weekShortNames) {
            $this->weekShortNames = DateUtils::getShortWeekdays('monday');
        }

        return $this;
    }
}
