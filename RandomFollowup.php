<?php

namespace Gforge\RandomFollowup;

use ExternalModules\AbstractExternalModule;

/**
 * Random Follow-up External Module
 * 
 * Creates a random follow-up date within a set interval
 * 
 * Note: This module currently does not support repeating instruments/events.
 * The repeat_instance parameter is included in the hook signature but not used.
 */
class RandomFollowup extends AbstractExternalModule
{
    /**
     * Hook: redcap_save_record
     * 
     * Triggered when a record is saved.
     * Generates a random follow-up date if conditions are met.
     * 
     * @param int $project_id The project ID
     * @param string $record The record ID
     * @param string $instrument The instrument name
     * @param int $event_id The event ID
     * @param int $group_id The group ID
     * @param string $survey_hash The survey hash
     * @param int $response_id The response ID
     * @param int $repeat_instance The repeat instance number
     */
    public function redcap_save_record($project_id, $record, $instrument, $event_id, $group_id = null, $survey_hash = null, $response_id = null, $repeat_instance = 1)
    {
        // Check if module is enabled
        if (!$this->getProjectSetting('enable_module')) {
            return;
        }

        // Get configuration
        $followup_field = $this->getProjectSetting('followup_field');
        $start_date_field = $this->getProjectSetting('start_date_field');
        $event_date_field = $this->getProjectSetting('event_date_field');
        $min_days = $this->getProjectSetting('min_days');
        $max_days = $this->getProjectSetting('max_days');

        // Validate configuration - start_date_field is required
        if (empty($followup_field) || empty($start_date_field) || !is_numeric($min_days) || !is_numeric($max_days)) {
            return;
        }

        // Ensure min_days <= max_days
        if ($min_days > $max_days) {
            return;
        }

        // Check if follow-up date is already set
        $followup_value = $this->getFieldValue($record, $followup_field, $event_id, $repeat_instance);
        
        // If follow-up date is already set, don't overwrite it
        if (!empty($followup_value)) {
            return;
        }

        // Determine which date to use as the base date
        $base_date_value = null;
        
        // First, try to use event_date_field if configured
        if (!empty($event_date_field)) {
            $base_date_value = $this->getFieldValue($record, $event_date_field, $event_id, $repeat_instance);
        }
        
        // If event_date_field is not set or empty, fall back to start_date_field
        if (empty($base_date_value)) {
            $base_date_value = $this->getFieldValue($record, $start_date_field, $event_id, $repeat_instance);
        }
        
        // If no base date is available, return
        if (empty($base_date_value)) {
            return;
        }

        // Generate random follow-up date
        try {
            $random_days = rand((int)$min_days, (int)$max_days);
            $base_date = new \DateTime($base_date_value);
            $followup_date = $base_date->modify("+{$random_days} days");
        } catch (\Exception $e) {
            // Invalid date format in base date field
            $this->log('Invalid date format in base date field', ['record' => $record, 'base_date_value' => $base_date_value]);
            return;
        }

        // Prepare data for saving using record => event structure
        $write = [
            $followup_field => $followup_date->format('Y-m-d')
        ];
        
        $save_data = [
            $record => [
                $event_id => $write
            ]
        ];

        // Save using REDCap::saveData directly
        $response = \REDCap::saveData(
            $project_id,
            'array',
            $save_data,
            'overwrite'
        );

        // Log any errors
        if (!empty($response['errors'])) {
            $this->log('Error saving follow-up date', $response);
        }
    }

    /**
     * Helper method to get field value
     * 
     * Note: This method does not support repeating instruments/events.
     * It will only retrieve data from the first instance.
     * 
     * @param string $record The record ID
     * @param string $field The field name
     * @param int $event_id The event ID
     * @param int $instance The repeat instance (not currently used - repeating instruments not supported)
     * @return string The field value
     */
    private function getFieldValue($record, $field, $event_id, $instance = 1)
    {
        $params = [
            'project_id' => $this->getProjectId(),
            'records' => $record,
            'fields' => [$field]
        ];

        if ($event_id) {
            $params['events'] = $event_id;
        }

        $data = \REDCap::getData($params);

        if (isset($data[$record][$event_id][$field])) {
            return $data[$record][$event_id][$field];
        }

        return null;
    }
}
