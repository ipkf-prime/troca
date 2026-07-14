# External Coding Systems

Current milestone: `0.4.6-admin-users-organization-dev`

## Model

`external_coding_systems` identifies a source-owned coding registry. `external_code_sets` divides that registry into dictionaries for geography, organizations, classifications, geographic scopes, facilities, or other domains. `external_code_segments` describes fixed-width composite code structure with one-based positions. `external_code_values` preserves actual version-aware observations; this task creates no values.

Codes and identifiers are strings. Application and import code must not cast them to integers, trim leading zeroes, or infer canonical identity only from parsed segments. Original titles are preserved; normalized titles are comparison-only.

## System coding registries

The metadata seeder registers:

- Ministry administrative coding
- SCI statistical geography coding
- Rural Cooperation statistical-system coding

Ministry hierarchy codes and national identifiers remain separate code sets. SCI statistical/census identifiers remain separate from official administrative identity.

## Rural Cooperation 3/5/8 contract

The active Rural Cooperation integration uses fixed-width strings:

| Code set | Length | One-based segments |
| --- | ---: | --- |
| `province_code` | 3 | full code: positions 1-3 |
| `county_code` | 5 | province: 1-3; county sequence: 4-5 |
| `organization_code` | 8 | county: 1-5; organization sequence: 6-8 |

The system also registers data-driven sets for `geographic_level`, `organization_level`, `organization_kind`, `organization_type`, and `organization_subtype`. No actual values are seeded in this phase.

The existing bot province IDs/codes, county IDs/codes, organization codes, classification IDs, filtering queries, and selection flow remain active and unchanged. The future compatibility path is:

`bot code -> external code value -> reviewed canonical/operational mapping`

Bot organization mapping is explicitly deferred. No canonical organization is created from a bot row in this phase.

## Version and history

`external_code_values` permits a code to recur in different source snapshots. Parent links are source observations, not canonical hierarchy. Code changes, removals, and renamed titles remain historically visible. Raw metadata may retain non-sensitive source fields but must never include credentials or personal information.
