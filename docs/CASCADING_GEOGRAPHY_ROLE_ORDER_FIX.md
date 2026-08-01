# Cascading Geography and Role Order Fix

## Geography root cause

The dynamic geography repository returned these keys:

```text
province_id
county_id
```

The user forms expected:

```text
province_location_id
county_location_id
```

Every generated option therefore received parent ID `0`, so selecting Tehran
did not restrict the county list.

The patch also limits graph traversal to active, primary
`administrative_parent` relations. Non-administrative or secondary relations
cannot become the parent used by the address form.

Both address forms now rebuild their county and city option lists:

```text
Province selected
→ only counties in that province
→ county selected
→ only cities in that county
```

## Role order

The role repository now returns roles using:

```sql
ORDER BY roles.priority ASC, roles.id ASC
```

The form no longer applies `sortRoles('code')` during initialization. Manual
column sorting remains available after the initial render.

## Migration

No migration or seed is required.
