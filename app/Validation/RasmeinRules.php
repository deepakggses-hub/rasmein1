<?php

declare(strict_types=1);

namespace App\Validation;

/**
 * Validation rules particular to Rasmein.
 *
 * Registered in Config\Validation::$ruleSets. A rule here is available to every
 * model and controller by name, exactly like a framework one.
 */
class RasmeinRules
{
    /**
     * A date that is today or later.
     *
     * For "when do you need this by". A date in the past is a typo or a bot,
     * and either way it enters the pipeline already reading as overdue.
     *
     * Empty passes: the field is optional, and `permit_empty` is what decides
     * that — a rule that rejected blank would make the two contradict.
     *
     * Compared as Y-m-d strings rather than timestamps, so it is a calendar-day
     * test: "today" is valid all day, in the server's timezone, with no
     * hours-and-minutes edge at either end.
     */
    public function rs_not_past(?string $str, ?string &$error = null): bool
    {
        $value = trim((string) $str);

        if ($value === '') {
            return true;
        }

        $time = strtotime($value);

        if ($time === false) {
            $error = 'That does not look like a date.';

            return false;
        }

        if (date('Y-m-d', $time) < date('Y-m-d')) {
            $error = 'That date is in the past.';

            return false;
        }

        return true;
    }

    /**
     * A date no further ahead than $days.
     *
     * Not currently required by a form, but it pairs with rs_not_past: an
     * unbounded date field accepts the year 9999, which sorts to the end of
     * every pipeline view and looks like corruption rather than a typo.
     */
    public function rs_within_days(?string $str, string $days, array $data, ?string &$error = null): bool
    {
        $value = trim((string) $str);

        if ($value === '') {
            return true;
        }

        $time = strtotime($value);

        if ($time === false) {
            return false;
        }

        $limit = strtotime('+' . max(1, (int) $days) . ' days');

        if (date('Y-m-d', $time) > date('Y-m-d', $limit)) {
            $error = 'That date is too far ahead.';

            return false;
        }

        return true;
    }
}
