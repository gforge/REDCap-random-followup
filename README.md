# REDCap Random Follow-up

A REDCap External Module that creates a random follow-up date within a set interval.

## Description

This module automatically generates a random follow-up date when a start date field is populated. The follow-up date is calculated by adding a random number of days (within a configured range) to either a start date (e.g., enrollment date) or an optional event date (e.g., fracture date).

## Features

- Automatically generate random follow-up dates
- Configure minimum and maximum day intervals
- Specify start date field (e.g., enrollment date)
- Optional event date field (e.g., fracture date) - if provided and populated, this will be used instead of the start date
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
   - **Start Date Field**: Select the field that contains the start date for calculation (e.g., enrollment date)
   - **Event Date Field (Optional)**: Optional field for event date (e.g., fracture date). If provided and not empty, this will be used instead of the start date field
   - **Minimum Days**: Minimum number of days to add to the base date
   - **Maximum Days**: Maximum number of days to add to the base date

## How It Works

1. When a record is saved, the module checks if it is enabled
2. It checks if the follow-up date field is already populated (if so, it doesn't overwrite)
3. It determines which date to use as the base date:
   - If Event Date Field is configured and has a value, it uses that date
   - Otherwise, it uses the Start Date Field value
4. If a base date is available, it generates a random number of days between the min and max values
5. It adds those days to the base date and saves the calculated follow-up date

## Example

### Basic Example
If you set:
- Start Date Field: `enrollment_date`
- Follow-up Date Field: `followup_date`
- Minimum Days: `30`
- Maximum Days: `60`

When a participant is enrolled on `2026-01-01`, the module will automatically generate a random follow-up date between `2026-01-31` and `2026-03-02`.

### Example with Event Date
If you set:
- Start Date Field: `enrollment_date`
- Event Date Field: `fracture_date`
- Follow-up Date Field: `followup_date`
- Minimum Days: `30`
- Maximum Days: `60`

Scenario:
- Patient enrolled: `2026-02-01`
- Patient had fracture: `2026-01-20`

The module will use the fracture date (`2026-01-20`) to calculate the follow-up date, resulting in a random date between `2026-02-19` and `2026-03-21`. If the fracture date field is empty, it will fall back to using the enrollment date.

## Requirements

- REDCap version with External Module Framework v12 or higher

## Limitations

- This module currently does not support repeating instruments or repeating events
- Follow-up dates can only be calculated for non-repeating fields

## Future Enhancements

Potential future features:
- Support for repeating instruments/events
- Multiple follow-up date calculations
- Business day calculations (excluding weekends/holidays)
- Custom random distribution patterns

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Author

- Max Gordon (max@gforge.se)
