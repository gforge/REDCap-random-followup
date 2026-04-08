# REDCap Random Follow-up

A REDCap External Module that generates **one or more randomized follow-up dates** within a configurable time window, with guaranteed minimum spacing between follow-ups.

## Description

This module automatically generates randomized follow-up dates when a record is saved. Follow-up dates are calculated relative to a single **base date** (e.g. enrollment or fracture date) by adding randomized offsets within a configured interval.

The module supports **multiple follow-up time points**, enforces a **minimum gap** between them, and uses a binning strategy to avoid clustering. Follow-up dates are generated **atomically**: either all are set at once, or none are.

---

## Key Concepts

- **Base date**
  - If an _Event Date Field_ is configured, it is **mandatory**
  - Otherwise, the _Start Date Field_ is used

- **Follow-up slots**
  - One or more follow-up date fields configured in the module

- **Atomic scheduling**
  - All follow-ups are generated together, or generation is refused

---

## Features

- Generate **1 or more random follow-up dates**
- Configurable **minimum / maximum offset (days)**
- Configurable **minimum gap** between follow-ups
- Deterministic binning to spread follow-ups across the window
- Prevents overwriting existing schedules
- Warns (once) if a partial schedule is detected
- Fully auditable via REDCap logging

---

## Installation

1. Clone or download this repository
2. Place the module in your REDCap `modules/` directory
3. Enable **Random Follow-up** under **Control Center → External Modules**

---

## Configuration

Go to **Project → External Modules → Configure**.

### General

- **Enable Random Follow-up**
- **Start Date Field**
- **Event Date Field (optional but strict)**
  If configured, it must be populated or no follow-up will be generated.

### Randomization Window

- **Minimum Days**
- **Maximum Days**
- **Minimum Gap (Days)**

### Random Follow-ups (repeatable)

For each follow-up slot:

- **Enabled**
- **Follow-up Date Field**

---

## How It Works (Short)

On record save:

1. The base date is resolved (event date if configured, otherwise start date).
2. Follow-up slots are checked:
   - All set → do nothing
   - None set → generate all follow-ups
   - Partially set → warn once and refuse to generate
3. The offset window is split into equal bins (one per follow-up).
4. One date is randomly sampled per bin, enforcing the minimum gap.
5. All follow-up dates are saved in a single operation.

A feasibility guard prevents impossible configurations:

- max_followups ≈ floor((max_days - min_days) / min_gap_days) + 1

---

## Examples

### Single Follow-up

Configuration:

- Start Date Field: `enrollment_date`
- Follow-up Date Field: `followup_2_to_10_m`
- Minimum Days: `60`
- Maximum Days: `300`

Enrollment on `2026-01-01` → follow-up randomly scheduled between
`2026-03-02` and `2026-10-28`.

---

### Multiple Follow-ups with Minimum Gap

Configuration:

- Start Date Field: `enrollment_date`
- Follow-up fields:
  - `followup_2_to_12_m_1`
  - `followup_2_to_12_m_2`
  - `followup_2_to_12_m_3`
- Minimum Days: `60`
- Maximum Days: `365`
- Minimum Gap Days: `30`

The module splits the window into 3 bins and schedules one follow-up per bin, ensuring ≥30 days between all follow-ups.

---

### Event Date Anchoring (Strict)

If an Event Date Field is configured and empty, **no follow-up is generated**.
There is **no fallback** to the start date.

---

## Status Field

The module uses a hidden text field:

- random_followup_status

Values:

- `ok` – schedule generated successfully
- `partial` – some follow-ups already set; generation refused

---

## Requirements

- REDCap External Module Framework v12+
- PHP 8.0+

---

## Testing

Unit tests cover the scheduling logic in `Scheduler.php`.

Run:

```bash
composer test
```

## Build

Create the release package with:

```bash
make build
```

This runs tests, prepares the release folder, and creates a ZIP file under `dist/`.

## Limitations

- Repeating instruments and repeating events are not supported
- Follow-up dates must use standard REDCap date format (`YYYY-MM-DD`)
- Scheduling is based on days, not calendar months

---

## License

MIT License – see [LICENSE](LICENSE)

---

## Author

**Max Gordon**
max.gordon@ki.se
