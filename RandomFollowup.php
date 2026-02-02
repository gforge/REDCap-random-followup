<?php

namespace Gforge\RandomFollowup;

use ExternalModules\AbstractExternalModule;

/**
 * Random Follow-up External Module
 * 
 * Creates a random follow-up date within a set interval
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
        $trigger_field = $this->getProjectSetting('trigger_field');
        $min_days = $this->getProjectSetting('min_days');
        $max_days = $this->getProjectSetting('max_days');

        // Validate configuration
        if (empty($followup_field) || empty($trigger_field) || !is_numeric($min_days) || !is_numeric($max_days)) {
            return;
        }

        // Ensure min_days <= max_days
        if ($min_days > $max_days) {
            return;
        }

        // Get the trigger field value
        $trigger_value = $this->getFieldValue($record, $trigger_field, $event_id, $repeat_instance);

        // If trigger field is not set, return
        if (empty($trigger_value)) {
            return;
        }

        // Check if follow-up date is already set
        $followup_value = $this->getFieldValue($record, $followup_field, $event_id, $repeat_instance);
        
        // If follow-up date is already set, don't overwrite it
        if (!empty($followup_value)) {
            return;
        }

        // Generate random follow-up date
        $random_days = rand((int)$min_days, (int)$max_days);
        $trigger_date = new \DateTime($trigger_value);
        $followup_date = $trigger_date->modify("+{$random_days} days");

        // Save the follow-up date
        $data = [
            $record => [
                $event_id => [
                    $followup_field => $followup_date->format('Y-m-d')
                ]
            ]
        ];

        $this->saveData($project_id, $data);
    }

    /**
     * Helper method to get field value
     * 
     * @param string $record The record ID
     * @param string $field The field name
     * @param int $event_id The event ID
     * @param int $instance The repeat instance
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

    /**
     * Helper method to save data
     * 
     * @param int $project_id The project ID
     * @param array $data The data to save
     */
    private function saveData($project_id, $data)
    {
        // Convert data to REDCap format
        $save_data = [];
        
        foreach ($data as $record => $events) {
            foreach ($events as $event_id => $fields) {
                $row = [
                    'record_id' => $record,
                    'redcap_event_name' => $this->getUniqueEventName($event_id)
                ];
                
                foreach ($fields as $field => $value) {
                    $row[$field] = $value;
                }
                
                $save_data[] = $row;
            }
        }

        // Save using REDCap::saveData
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
}
