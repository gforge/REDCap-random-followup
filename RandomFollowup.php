<?php

namespace Gforge\RandomFollowup;

use ExternalModules\AbstractExternalModule;
use REDCap;

require_once __DIR__ . '/Scheduler.php';

final class RandomFollowup extends AbstractExternalModule
{
    private const STATUS_FIELD = 'random_followup_status'; // hidden text field in baseline event

    public function redcap_save_record(
        $project_id,
        $record,
        $instrument,
        $event_id,
        $group_id = null,
        $survey_hash = null,
        $response_id = null,
        $repeat_instance = 1
    ): void {
        if ((int)$repeat_instance > 1) {
            return;
        }

        $cfg = $this->read_cfg();
        if ($cfg === null) {
            return;
        }

        $slot_fields = $this->get_enabled_slot_fields();
        if ($slot_fields === []) {
            return;
        }

        $fields = $this->fields_to_fetch($cfg, $slot_fields);

        $row = $this->read_row(
            project_id: (int)$project_id,
            record: (string)$record,
            event_id: (int)$event_id,
            fields: $fields
        );
        if ($row === null) {
            return;
        }

        $base_date_value = $this->pick_base_date_value($row, $cfg);

        // If event_date_field is configured, it is mandatory (no fallback)
        if ($base_date_value === '') {
            if ($cfg->event_date_field !== '') {
                $this->log_event(
                    record: (string)$record,
                    event_id: (int)$event_id,
                    title: 'missing event date',
                    message: "Configured event_date_field '{$cfg->event_date_field}' is empty; scheduling aborted."
                );
            }
            return;
        }

        $base_date = Scheduler::parse_redcap_date($base_date_value);
        if ($base_date === null) {
            $this->log_event(
                record: (string)$record,
                event_id: (int)$event_id,
                title: 'invalid base date',
                message: "Invalid base date '{$base_date_value}'"
            );
            return;
        }

        $slots = $this->slot_state($row, $slot_fields);

        // Policy:
        // - all set => OK, silent
        // - partial => warn once, refuse
        // - none set => generate all at once
        if ($slots->all_set) {
            return;
        }

        if ($slots->is_partial) {
            $this->warn_partial_once(
                project_id: (int)$project_id,
                record: (string)$record,
                event_id: (int)$event_id,
                row: $row,
                set_fields: $slots->set_fields,
                empty_fields: $slots->empty_fields
            );
            return;
        }

        // All empty -> generate schedule for all slot fields (atomic)
        $target_fields = $slots->all_fields;
        sort($target_fields);

        // Runtime feasibility check (rule-of-thumb)
        if (!Scheduler::is_feasible(
            n: count($target_fields),
            min_days: $cfg->min_days,
            max_days: $cfg->max_days,
            min_gap_days: $cfg->min_gap_days
        )) {
            $max_n = Scheduler::max_n_by_gap($cfg->min_days, $cfg->max_days, $cfg->min_gap_days);
            $this->log_event(
                record: (string)$record,
                event_id: (int)$event_id,
                title: 'config infeasible',
                message: "Too many follow-ups (n=" . count($target_fields) . ") for window=[{$cfg->min_days},{$cfg->max_days}] and min_gap_days={$cfg->min_gap_days}. " .
                         "Rule-of-thumb max: {$max_n}."
            );
            return;
        }

        $new_values = Scheduler::schedule_binned(
            base_date: $base_date,
            min_days: $cfg->min_days,
            max_days: $cfg->max_days,
            min_gap_days: $cfg->min_gap_days,
            target_fields: $target_fields
        );

        if ($new_values === null) {
            $this->log_event(
                record: (string)$record,
                event_id: (int)$event_id,
                title: 'scheduling failed',
                message: "Could not generate spaced dates for n=" . count($target_fields) .
                         " within window=[{$cfg->min_days},{$cfg->max_days}] and min_gap_days={$cfg->min_gap_days}."
            );
            return;
        }

        // Mark OK (also prevents repeated partial warnings later)
        $new_values[self::STATUS_FIELD] = 'ok';

        $ok = $this->save_values(
            project_id: (int)$project_id,
            record: (string)$record,
            event_id: (int)$event_id,
            values: $new_values
        );
        if (!$ok) {
            return;
        }

        $this->log_event(
            record: (string)$record,
            event_id: (int)$event_id,
            title: 'set follow-up dates',
            message: "Base={$base_date_value}; min_days={$cfg->min_days}; max_days={$cfg->max_days}; min_gap_days={$cfg->min_gap_days}; set=" . json_encode($new_values)
        );
    }

    // ----------------------------
    // Config
    // ----------------------------

    private function read_cfg(): ?Cfg
    {
        $enabled = (bool)$this->getProjectSetting('enable_module');
        if (!$enabled) {
            return null;
        }

        $start_date_field = (string)$this->getProjectSetting('start_date_field');
        $event_date_field = (string)$this->getProjectSetting('event_date_field'); // optional-but-if-set => mandatory data

        $min_days_raw = $this->getProjectSetting('min_days');
        $max_days_raw = $this->getProjectSetting('max_days');
        $min_gap_raw  = $this->getProjectSetting('min_gap_days');

        if ($start_date_field === '' || !is_numeric($min_days_raw) || !is_numeric($max_days_raw) || !is_numeric($min_gap_raw)) {
            return null;
        }

        $min_days = (int)$min_days_raw;
        $max_days = (int)$max_days_raw;
        $min_gap_days = (int)$min_gap_raw;

        if ($min_days > $max_days || $min_gap_days < 0) {
            return null;
        }

        return new Cfg(
            start_date_field: $start_date_field,
            event_date_field: $event_date_field,
            min_days: $min_days,
            max_days: $max_days,
            min_gap_days: $min_gap_days
        );
    }

    /** @return string[] */
    private function get_enabled_slot_fields(): array
    {
        $slots = $this->getProjectSetting('random_followups');
        if (!is_array($slots) || $slots === []) {
            return [];
        }

        $fields = [];
        foreach ($slots as $slot) {
            if (!is_array($slot)) continue;

            $enabled = $slot['enabled'] ?? null;
            if ($enabled !== null && !$enabled) {
                continue;
            }

            $field = (string)($slot['followup_date_field'] ?? '');
            if ($field !== '') {
                $fields[] = $field;
            }
        }

        return array_values(array_unique($fields));
    }

    /** @param string[] $slot_fields @return string[] */
    private function fields_to_fetch(Cfg $cfg, array $slot_fields): array
    {
        return array_values(array_unique(array_filter(array_merge(
            [$cfg->start_date_field, $cfg->event_date_field, self::STATUS_FIELD],
            $slot_fields
        ))));
    }

    // ----------------------------
    // REDCap IO
    // ----------------------------

    /** @param string[] $fields */
    private function read_row(int $project_id, string $record, int $event_id, array $fields): ?array
    {
        $data = REDCap::getData([
            'project_id'    => $project_id,
            'return_format' => 'array',
            'records'       => [$record],
            'events'        => [$event_id],
            'fields'        => $fields,
        ]);

        return $data[$record][$event_id] ?? null;
    }

    /** @param array<string,mixed> $row */
    private function pick_base_date_value(array $row, Cfg $cfg): string
    {
        if ($cfg->event_date_field !== '') {
            return trim((string)($row[$cfg->event_date_field] ?? ''));
        }
        return trim((string)($row[$cfg->start_date_field] ?? ''));
    }

    /** @param array<string,string> $values */
    private function save_values(int $project_id, string $record, int $event_id, array $values): bool
    {
        $resp = REDCap::saveData($project_id, 'array', [
            $record => [
                $event_id => $values,
            ],
        ]);

        if (!empty($resp['errors'])) {
            $this->log_event(
                record: $record,
                event_id: $event_id,
                title: 'save error',
                message: json_encode($resp['errors'])
            );
            return false;
        }

        return true;
    }

    private function log_event(string $record, int $event_id, string $title, string $message): void
    {
        REDCap::logEvent('Random follow-up: ' . $title, $message, null, $record, $event_id);
    }

    // ----------------------------
    // Slot state + partial warning once
    // ----------------------------

    /**
     * @param array<string,mixed> $row
     * @param string[] $slot_fields
     */
    private function slot_state(array $row, array $slot_fields): SlotState
    {
        $set_fields = [];
        $empty_fields = [];

        foreach ($slot_fields as $field) {
            $val = trim((string)($row[$field] ?? ''));
            if ($val === '') $empty_fields[] = $field;
            else $set_fields[] = $field;
        }

        $all_set = (count($empty_fields) === 0 && count($slot_fields) > 0);
        $is_partial = (count($set_fields) > 0 && count($empty_fields) > 0);

        return new SlotState(
            all_fields: $slot_fields,
            set_fields: $set_fields,
            empty_fields: $empty_fields,
            all_set: $all_set,
            is_partial: $is_partial
        );
    }

    /**
     * Warn once per record/event when partial schedule is detected.
     *
     * @param array<string,mixed> $row
     * @param string[] $set_fields
     * @param string[] $empty_fields
     */
    private function warn_partial_once(
        int $project_id,
        string $record,
        int $event_id,
        array $row,
        array $set_fields,
        array $empty_fields
    ): void {
        $status = trim((string)($row[self::STATUS_FIELD] ?? ''));
        if ($status === 'partial') {
            return;
        }

        $this->log_event(
            record: $record,
            event_id: $event_id,
            title: 'partial schedule detected',
            message: 'Some follow-up slots are set and others are empty; refusing to generate to avoid too-close follow-ups. ' .
                     'Set fields: ' . json_encode($set_fields) . '; empty fields: ' . json_encode($empty_fields)
        );

        $this->save_values(
            project_id: $project_id,
            record: $record,
            event_id: $event_id,
            values: [self::STATUS_FIELD => 'partial']
        );
    }
}

/**
 * Small config carrier.
 */
final class Cfg
{
    public function __construct(
        public string $start_date_field,
        public string $event_date_field,
        public int $min_days,
        public int $max_days,
        public int $min_gap_days
    ) {}
}

/**
 * Slot state carrier.
 */
final class SlotState
{
    /** @param string[] $all_fields @param string[] $set_fields @param string[] $empty_fields */
    public function __construct(
        public array $all_fields,
        public array $set_fields,
        public array $empty_fields,
        public bool $all_set,
        public bool $is_partial
    ) {}
}
