# Fuel Type & Variant on Fuel Entries

## Problem

Fuel entries currently record no information about what fuel was actually put
in the car. We want to capture:

- **Fuel type** — a fixed category: Gasoline, Diesel, LPG, or Electric.
- **Fuel variant** — free plaintext for the specific grade/product, e.g.
  `"95"`, `"98"`, `"B7"`, `"Shell V-Power"`.

Both must be **mandatory when creating or editing** a fuel entry (through the
Filament form and the MCP tools), except that fuel variant is optional when
fuel type is Electric (charging doesn't have a "grade").

Existing fuel entries have no sane default for either field, so the database
columns must **not** enforce NOT NULL / a default — enforcement lives entirely
at the application layer (Filament form validation + the shared model
validation rules used by the MCP tools). Existing rows stay `NULL` until next
edited.

## Data model

### New enum: `App\Enums\FuelType`

A string-backed enum, following the existing `App\Services\AnalyticsPeriod`
pattern already used in this codebase:

```php
enum FuelType: string
{
    case Gasoline = 'gasoline';
    case Diesel = 'diesel';
    case Lpg = 'lpg';
    case Electric = 'electric';
}
```

Add a `label()` method (or Filament's `HasLabel` interface) returning
human-readable labels ("Gasoline", "Diesel", "LPG", "Electric") for use in the
Select and table badge.

### Migration

New migration adds two nullable columns to `fuel_entries`, no default:

- `fuel_type` (`string`, nullable) — stores the enum's string value.
- `fuel_variant` (`string`, nullable) — free text.

No backfill of existing rows. No DB-level CHECK constraint (unlike the
positive-value constraints already on this table) since NULL is a valid,
expected state for old rows — the constraint that "these must be present" only
applies at create/edit time, not at rest.

### Model (`App\Models\FuelEntry`)

- Add `fuel_type` and `fuel_variant` to the `#[Fillable]` attribute list.
- Cast `fuel_type` to the `FuelType` enum in `casts()`.
- Extend `validationRules()`:
  - `fuel_type` → `[$required, 'string', new Enum(FuelType::class)]`
  - `fuel_variant` → `['required_unless:fuel_type,'.FuelType::Electric->value, 'string', 'max:255']` when all fields required; `['sometimes', ...]`-compatible equivalent for the edit path (same `required_unless` rule works whether or not other fields are present, since Laravel's `required_unless` only fires when the field is missing/empty and the comparison field's value matches).

This keeps validation in one place, shared by the Filament form (via the
resource) and both MCP tools (which already call
`FuelEntry::validationRules()`), so it can't drift between entry points — same
discipline as every other field on this model.

## Filament UI

### Form (`FuelEntryForm`)

Add, right after the `car_id` select:

- `Select::make('fuel_type')` — options from `FuelType` cases, required,
  `->live()` so the variant field can react to it.
- `TextInput::make('fuel_variant')` — `->required(fn (Get $get) => $get('fuel_type') !== FuelType::Electric->value)`, max 255.

### Table (`FuelEntriesTable`)

Add two columns after `car.name`:

- `fuel_type` as a badge column (using the enum's label/color).
- `fuel_variant` as plain text.

Both should render gracefully (blank/dash) for existing `NULL` rows.

## MCP tools

`AddFuelEntryTool` and `EditFuelEntryTool` both already delegate validation to
`FuelEntry::validationRules()`, so no duplicate rule logic is needed. Add to
both tools' `schema()`:

- `fuel_type` — string, enum-constrained via description listing valid
  values (`gasoline`, `diesel`, `lpg`, `electric`), required on add /
  optional on edit.
- `fuel_variant` — string, described as free text for the specific
  grade/product; required on add unless fuel_type is electric.

## Factory

`FuelEntryFactory::definition()` sets `fuel_type` to a random `FuelType` case
and `fuel_variant` to a plausible fake value (e.g. one of `"95"`, `"98"`,
`"B7"`), so existing tests and any new tests that use the factory get valid
data without special-casing.

## Out of scope

- No backfill of existing rows.
- No DB-level CHECK constraint tying fuel_variant's requiredness to
  fuel_type — that logic lives in app-layer validation only, matching the
  instruction that this must not be enforced at the database level.
- No changes to `CarFuelHistory` analytics page/blade — fuel type is not part
  of the current L/100km or spend calculations.
