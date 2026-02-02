<?php

namespace Gforge\RandomFollowup;

final class Scheduler
{
    /**
     * Strict parse of REDCap date (YYYY-MM-DD).
     */
    public static function parse_redcap_date(string $value): ?\DateTimeImmutable
    {
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        $errs = \DateTimeImmutable::getLastErrors();

        if ($dt === false || !empty($errs['warning_count']) || !empty($errs['error_count'])) {
            return null;
        }

        return $dt;
    }

    public static function is_feasible(int $n, int $min_days, int $max_days, int $min_gap_days): bool
    {
        if ($n <= 0) return false;
        if ($min_days > $max_days) return false;
        if ($min_gap_days <= 0) return true;

        return $n <= self::max_n_by_gap($min_days, $max_days, $min_gap_days);
    }

    public static function max_n_by_gap(int $min_days, int $max_days, int $min_gap_days): int
    {
        if ($min_gap_days <= 0) {
            return PHP_INT_MAX;
        }
        $window = $max_days - $min_days; // rule-of-thumb convention
        return intdiv($window, $min_gap_days) + 1;
    }

    /**
     * Schedule one date per bin across the window, enforcing minimum spacing.
     *
     * @param \DateTimeImmutable $base_date
     * @param int $min_days
     * @param int $max_days
     * @param int $min_gap_days
     * @param string[] $target_fields
     * @return array<string,string>|null field => Y-m-d
     */
    public static function schedule_binned(
        \DateTimeImmutable $base_date,
        int $min_days,
        int $max_days,
        int $min_gap_days,
        array $target_fields
    ): ?array {
        $n = count($target_fields);
        if ($n === 0) return null;

        $bins = self::make_bins($min_days, $max_days, $n);

        $chosen_dates = []; // DateTimeImmutable[]
        $out = [];          // field => 'Y-m-d'
        $max_attempts_per_bin = 50;

        for ($i = 0; $i < $n; $i++) {
            $field = $target_fields[$i];
            [$bin_start, $bin_end] = $bins[$i];

            $dt = self::sample_in_bin_spaced(
                base_date: $base_date,
                bin_start: $bin_start,
                bin_end: $bin_end,
                min_gap_days: $min_gap_days,
                existing_dates: $chosen_dates,
                max_attempts: $max_attempts_per_bin
            );

            if ($dt === null) {
                return null;
            }

            $out[$field] = $dt->format('Y-m-d');
            $chosen_dates[] = $dt;
        }

        return $out;
    }

    /**
     * Split inclusive range [min_days, max_days] into n bins.
     *
     * @return array<int, array{0:int,1:int}>
     */
    private static function make_bins(int $min_days, int $max_days, int $n): array
    {
        $len = $max_days - $min_days + 1;
        $bin_size = intdiv($len, $n);
        $remainder = $len % $n;

        $bins = [];
        $cursor = $min_days;

        for ($i = 0; $i < $n; $i++) {
            $size = $bin_size + ($i < $remainder ? 1 : 0);
            $start = $cursor;
            $end = $cursor + $size - 1;
            $bins[] = [$start, $end];
            $cursor = $end + 1;
        }

        return $bins;
    }

    /**
     * @param \DateTimeImmutable[] $existing_dates
     */
    private static function sample_in_bin_spaced(
        \DateTimeImmutable $base_date,
        int $bin_start,
        int $bin_end,
        int $min_gap_days,
        array $existing_dates,
        int $max_attempts
    ): ?\DateTimeImmutable {
        for ($i = 0; $i < $max_attempts; $i++) {
            $offset_days = random_int($bin_start, $bin_end);
            $candidate = $base_date->modify("+{$offset_days} days");

            if (self::is_far_enough($candidate, $existing_dates, $min_gap_days)) {
                return $candidate;
            }
        }
        return null;
    }

    /**
     * @param \DateTimeImmutable[] $dates
     */
    private static function is_far_enough(\DateTimeImmutable $candidate, array $dates, int $min_gap_days): bool
    {
        if ($min_gap_days <= 0) return true;

        $cand_ts = $candidate->getTimestamp();
        $gap_seconds = $min_gap_days * 86400;

        foreach ($dates as $dt) {
            if (abs($cand_ts - $dt->getTimestamp()) < $gap_seconds) {
                return false;
            }
        }

        return true;
    }
}
