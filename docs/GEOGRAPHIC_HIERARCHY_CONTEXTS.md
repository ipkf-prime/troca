# Geographic Hierarchy Contexts

Current milestone: `0.4.6-admin-users-organization-dev`

## Coexisting hierarchies

`geographic_hierarchy_types` distinguishes parent relationships that have different meaning:

- `official_administrative`: authoritative Ministry hierarchy.
- `statistical`: SCI census/statistical context.
- `rural_cooperation_operational`: Rural Cooperation network geography.
- `custom`: explicitly configured deployment hierarchy.

The migration extends `geographic_location_relations` with nullable hierarchy, source, source-snapshot, and review context. Existing relations remain valid compatibility relations and are not classified by guesswork. A canonical county may therefore have an official administrative parent and a separate operational parent at the same time.

## South Kerman model

South Kerman is the motivating operational-region use case. It may group Jiroft, Kahnuj, and other network-member counties for Rural Cooperation operations while each county retains its Ministry-defined official parent.

`operational_region` is a data-driven geographic level titled `منطقه عملیاتی`. It is not an official province. This phase seeds only the level/hierarchy metadata; it does not create a South Kerman location or assign any county to it.

## Source precedence

- Ministry wins conflicts about the canonical official administrative parent.
- SCI may supplement villages, settlements, statistical points, and census identifiers.
- SCI `CODEREC=5` or unfamiliar DIAG/type values do not automatically establish an official city.
- Statistical urban subdivisions do not become official cities by inference.
- Rural Cooperation operational parents are stored only in the operational hierarchy.
- A matching title never merges records automatically.
- Missing or virtual parents are reported as issues, not fabricated.
- Source removal never automatically deletes a canonical location.

Validity periods and history apply within each hierarchy context. Future services must prevent cycles per hierarchy, enforce source/domain conflict policy, and retain historical relationships.

## External identifiers and mappings

`geographic_external_identifiers` allows one canonical location to retain Ministry codes, Ministry national identifiers, SCI identifiers, and Rural Cooperation codes independently. Identifier values are strings and are not globally unique without source/type context.

`geographic_external_code_mappings` records reviewable links from versioned external values to canonical locations. Supported workflow states include `proposed`, `exact`, `review_required`, `confirmed`, `ambiguous`, `rejected`, and `superseded`. No mapping is created in this phase.

## Legacy and bot compatibility

Existing canonical geography, `geographic_legacy_mappings`, legacy province/city fields, optional lookup tables, and bot data remain untouched. A bot table named `cities` may semantically represent counties; it is mapped through an explicit source contract rather than renamed or rewritten. City is never substituted for county.

## Ministry validation hierarchy

The validate-only Ministry adapter derives official source parents from configured hierarchy-code prefixes: county to province, district to county, and rural district/city to district. The configured Iran root is used only as the province parent reference. Missing, virtual, duplicate, malformed, or level-incompatible source parents become staging issues; they do not create canonical locations or relations.

## SCI validation hierarchy

SCI parent and duplicate checks use complete source context rather than globally
unique component codes. A settlement uses its province, county, district, optional
rural/statistical unit, and settlement code. Missing parents become issues; no
parent is fabricated. SCI hierarchy observations never replace Ministry official
parents, and a statistical urban unit is not substituted for an official city.

## Parent-first crosswalk hierarchy

The candidate engine first resolves province and then carries only deterministic
parent candidates into county, district, rural-district, and city comparisons.
Every lower-level target must belong to the exact matched Ministry parent row.
Ambiguous or unmatched parents cannot produce exact children. Same-name locations
under different parents therefore remain distinct.
## Official Ministry apply

The Ministry canonicalization workflow writes only the
`official_administrative` hierarchy. It uses `administrative_parent` and applies
Iran, province, county, district, rural district, and city parent-first. Existing
statistical, Rural Cooperation operational, custom, and historical relations are
never modified. A conflicting active official parent becomes review work rather
than an automatic reparent.
