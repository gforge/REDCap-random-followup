# REDCap Random Follow-up

A REDCap External Module that creates a random follow-up date within a set interval.

## Description

This module automatically generates a random follow-up date when a trigger field is populated. The follow-up date is calculated by adding a random number of days (within a configured range) to the trigger date.

## Features

- Automatically generate random follow-up dates
- Configure minimum and maximum day intervals
- Specify trigger field (e.g., enrollment date)
- Specify follow-up date field
- Prevents overwriting existing follow-up dates

## Installation

1. Download the module from the REDCap External Modules repository or clone this repository
2. Extract the files to your REDCap `modules` folder
3. Go to **Control Center > External Modules**
4. Enable the "Random Follow-up" module

## Configuration

### Project Settings

1. Navigate to your project
2. Go to **External Modules**
3. Click **Configure** next to "Random Follow-up"
4. Configure the following settings:

   - **Enable Random Follow-up**: Check to enable the module for this project
   - **Follow-up Date Field**: Select the field that will store the random follow-up date
   - **Trigger Field**: Select the field that triggers the calculation (e.g., enrollment date)
   - **Minimum Days**: Minimum number of days to add to the trigger date
   - **Maximum Days**: Maximum number of days to add to the trigger date

## How It Works

1. When a record is saved and the trigger field has a value
2. The module checks if the follow-up date field is empty
3. If empty, it generates a random number of days between the min and max values
4. It adds those days to the trigger date
5. The calculated follow-up date is saved to the follow-up date field

## Example

If you set:
- Trigger Field: `enrollment_date`
- Follow-up Date Field: `followup_date`
- Minimum Days: `30`
- Maximum Days: `60`

When a participant is enrolled on `2026-01-01`, the module will automatically generate a random follow-up date between `2026-01-31` and `2026-03-02`.

## Requirements

- REDCap version with External Module Framework v12 or higher

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Author

- Max Gordon (max@gforge.se)
