<!--
PROJECT-SPECIFIC VALUES (replace before submission):

BASE_DATE_DESCRIPTION = enrollment date
BASE_DATE_FIELD = enrollment_date
MIN_DAYS = 60
MAX_DAYS = 365
MIN_GAP_DAYS = 30
N_FOLLOWUPS = 3
-->

# Random Follow-up Date Generation – Methods Text

## Short Methods Description

Follow-up dates are generated using a predefined randomization procedure implemented as a REDCap External Module. For each participant, follow-up dates are anchored to a single base date (%BASE_DATE_DESCRIPTION%). A configurable time window of %MIN_DAYS% to %MAX_DAYS% days after the base date is specified. %N_FOLLOWUPS% follow-up dates are randomly sampled within this window, subject to a minimum spacing constraint of %MIN_GAP_DAYS% days between follow-ups.

## Detailed Methods

### Base Date

All follow-up dates are calculated relative to a single base date stored in the REDCap record (%BASE_DATE_DESCRIPTION%; REDCap field: `%BASE_DATE_FIELD%`). If an event date field is configured (e.g. fracture date), this date is treated as mandatory. If no event date field is configured, the enrollment date is used as the base date. Follow-up scheduling is not performed if the required base date is missing.

### Randomization Window

A minimum and maximum offset (in days) from the base date define the allowable follow-up window. All randomized follow-up dates fall within this interval.

### Multiple Follow-ups and Spacing

When multiple follow-up time points are configured, the total window is divided into equally sized contiguous sub-intervals (“bins”), one per follow-up. One follow-up date is randomly sampled from each bin. A minimum spacing constraint (in days) is enforced between all generated follow-up dates. If a candidate date violates this constraint, resampling is performed within the same bin.

### Feasibility Guard

To prevent infeasible configurations, an approximate upper bound on the number of follow-ups is enforced based on the window length and minimum spacing:

- max_followups ≈ floor((%MAX_DAYS% − %MIN_DAYS%) / %MIN_GAP_DAYS%) + 1

If the configured number of follow-up slots exceeds this bound, no follow-up dates are generated and the configuration is rejected and logged.

### Atomic Scheduling and Idempotence

Follow-up dates are generated only if all follow-up fields are empty. If some follow-up dates are already present, no additional dates are generated in order to avoid introducing overly close follow-up intervals. This ensures that follow-up schedules are either complete or absent, and never partially generated.

### Audit Trail

All scheduling decisions are logged using REDCap’s native logging system. A dedicated status field is used to mark records where partial schedules were detected, preventing repeated warnings on subsequent saves.

## Design Rationale

- Follow-up dates are randomized in days rather than calendar months to ensure reproducibility.
- Binning is used to avoid clustering of follow-ups at the edges of the window.
- Atomic generation avoids dependency on the order of data entry.
- Strict handling of missing base dates prevents silent misalignment of follow-up timing.

## Reproducibility

The randomization procedure is deterministic given the configured parameters and REDCap record state. All parameters governing follow-up generation are stored in the REDCap project configuration and can be audited retrospectively.
