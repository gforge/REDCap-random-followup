## Quick setup checklist

Before enabling the module, ensure that:

- [ ] All follow-up date fields exist and are **date (Y-M-D)** fields
- [ ] All follow-up fields are in the **same event** as the base date
- [ ] A hidden text field named `random_followup_status` exists in that event
- [ ] If `event_date_field` is configured, it will be populated before saving
- [ ] The number of follow-ups is feasible for the chosen window and gap

## Recommended field naming

We recommend naming follow-up fields to reflect their window, not their exact timing:

- `followup_2_to_10_m`
- `followup_2_to_12_m_1`
- `followup_2_to_12_m_2`

This avoids implying fixed visit timing while keeping intent clear.
