# Fuel Type & Variant Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let users record a fuel type (Gasoline/Diesel/LPG/Electric) and a plaintext variant (e.g. "95", "B7", "Shell V-Power") on every fuel entry, mandatory at the app layer (Filament form + MCP tools) but nullable in the database since existing rows have no sane default.

**Architecture:** A new string-backed `App\Enums\FuelType` enum (implementing Filament's `HasLabel`/`HasColor` contracts) backs a `Select` field in the Filament form and a badge column in the table. Two new nullable columns (`fuel_type`, `fuel_variant`) are added to `fuel_entries` with no default and no DB constraint. Requiredness is enforced entirely in the application layer: Filament's own fluent `->required()` closures for the form, and the existing shared `FuelEntry::validationRules()` method (already the single source of truth consumed by both MCP tools) for the MCP entry points. `fuel_variant` is required unless `fuel_type` is Electric.

**Tech Stack:** Laravel 13 (PHP 8.3), Filament 5, PHPUnit-style feature/unit tests (`Tests\TestCase`, `RefreshDatabase`), SQLite in-memory test DB.

## Global Constraints

- `fuel_type` and `fuel_variant` columns must be nullable with no default at the DB level — existing rows keep `NULL` until next edited (per spec: no backfill, no DB-level CHECK constraint).
- Both fields are mandatory when creating/editing via the Filament form and the MCP tools, except `fuel_variant` is optional when `fuel_type` is `Electric`.
- Reuse `FuelEntry::validationRules()` for both MCP tools — do not duplicate validation logic per tool (matches existing pattern in this file).
- Follow existing code patterns: enum lives in `App\Enums`, mirrors the style of `App\Services\AnalyticsPeriod`; Filament conditional-required fields mirror the existing `trip_km`/`is_partial_fill` pattern in `FuelEntryForm`.

---

### Task 1: `FuelType` enum, migration, model, and factory

**Files:**
- Create: `app/Enums/FuelType.php`
- Create: `database/migrations/2026_08_25_150000_add_fuel_type_and_fuel_variant_to_fuel_entries_table.php`
- Modify: `app/Models/FuelEntry.php`
- Modify: `database/factories/FuelEntryFactory.php`
- Test: `tests/Feature/FuelEntryValidationRulesTest.php`

**Interfaces:**
- Produces: `App\Enums\FuelType` — string-backed enum with cases `Gasoline = 'gasoline'`, `Diesel = 'diesel'`, `Lpg = 'lpg'`, `Electric = 'electric'`; implements `Filament\Support\Contracts\HasLabel::getLabel(): string` and `Filament\Support\Contracts\HasColor::getColor(): string`.
- Produces: `FuelEntry::validationRules(bool $allFieldsRequired = true): array` now also returns `fuel_type` and `fuel_variant` keys (extends the existing method, same signature).
- Produces: `fuel_entries.fuel_type` (nullable string column, stores enum value) and `fuel_entries.fuel_variant` (nullable string column) — consumed by Task 2 (Filament) and Task 3 (MCP).
- Produces: `FuelEntry::$fuel_type` cast to `FuelType` enum; `FuelEntry::$fuel_variant` plain string.
- Consumes: nothing from other tasks (this is the foundation task).

- [ ] **Step 1: Create the `FuelType` enum**

```php
<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FuelType: string implements HasColor, HasLabel
{
    case Gasoline = 'gasoline';
    case Diesel = 'diesel';
    case Lpg = 'lpg';
    case Electric = 'electric';

    public function getLabel(): string
    {
        return match ($this) {
            self::Gasoline => 'Gasoline',
            self::Diesel => 'Diesel',
            self::Lpg => 'LPG',
            self::Electric => 'Electric',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Gasoline => 'warning',
            self::Diesel => 'gray',
            self::Lpg => 'info',
            self::Electric => 'success',
        };
    }
}
```

- [ ] **Step 2: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fuel_entries', function (Blueprint $table) {
            // Nullable, no default: existing rows have no sane fuel
            // type/variant to backfill. Requiredness on create/edit is
            // enforced at the application layer only (Filament form +
            // FuelEntry::validationRules()), not here.
            $table->string('fuel_type')->nullable()->after('car_id');
            $table->string('fuel_variant')->nullable()->after('fuel_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fuel_entries', function (Blueprint $table) {
            $table->dropColumn(['fuel_type', 'fuel_variant']);
        });
    }
};
```

- [ ] **Step 3: Run the migration against the test/dev DB**

Run: `php artisan migrate`
Expected: `... DONE` including the new migration filename, no errors.

- [ ] **Step 4: Update `FuelEntry` model — fillable, casts, validation rules**

Edit `app/Models/FuelEntry.php`:

```php
<?php

namespace App\Models;

use App\Enums\FuelType;
use Database\Factories\FuelEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

#[Fillable([
    'car_id',
    'user_id',
    'fuel_type',
    'fuel_variant',
    'gas_station',
    'litres_filled',
    'amount',
    'odometer_at_fill',
    'date_of_filling',
    'is_partial_fill',
    'trip_km',
])]
class FuelEntry extends Model
{
    /** @use HasFactory<FuelEntryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'fuel_type' => FuelType::class,
            'litres_filled' => 'decimal:3',
            'amount' => 'decimal:2',
            'odometer_at_fill' => 'integer',
            'date_of_filling' => 'datetime',
            'is_partial_fill' => 'boolean',
            'trip_km' => 'decimal:1',
        ];
    }

    public function car()
    {
        return $this->belongsTo(Car::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Shared validation constraints for every entry point that creates or
     * updates a fuel entry outside the Filament form's own fluent field
     * rules (currently just the MCP tools) — kept in one place so the
     * create and edit tools can't drift apart from each other. These
     * mirror the DB-level CHECK constraints in the fuel_entries migration
     * (which remain the actual last line of defense regardless of which
     * entry point is used), plus the fields those constraints can't
     * express (required-ness, foreign key existence).
     *
     * fuel_type/fuel_variant are deliberately NOT backed by a DB
     * constraint (existing rows have no sane default) — required-ness for
     * them lives here only. fuel_variant is required unless fuel_type is
     * Electric; on edit (allFieldsRequired=false) both stay optional like
     * every other partial-update field, so a caller can still patch just
     * fuel_type without being forced to also resend fuel_variant.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function validationRules(bool $allFieldsRequired = true): array
    {
        $required = $allFieldsRequired ? 'required' : 'sometimes';

        return [
            'car_id' => [$required, 'integer', 'exists:cars,id'],
            'fuel_type' => [$required, Rule::enum(FuelType::class)],
            'fuel_variant' => $allFieldsRequired
                ? ['required_unless:fuel_type,'.FuelType::Electric->value, 'string', 'max:255']
                : ['sometimes', 'nullable', 'string', 'max:255'],
            'gas_station' => [$required, 'string', 'max:255'],
            'litres_filled' => [$required, 'numeric', 'min:0.001'],
            'amount' => [$required, 'numeric', 'min:0.01'],
            'odometer_at_fill' => [$required, 'integer', 'min:0'],
            'date_of_filling' => [$required, 'date'],
            'is_partial_fill' => ['sometimes', 'boolean'],
            'trip_km' => ['nullable', 'numeric', 'min:0.1'],
        ];
    }
}
```

- [ ] **Step 5: Update the factory**

Edit `database/factories/FuelEntryFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\FuelType;
use App\Models\Car;
use App\Models\FuelEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FuelEntry>
 */
class FuelEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fuelType = fake()->randomElement(FuelType::cases());

        return [
            'car_id' => Car::factory(),
            'user_id' => User::factory(),
            'fuel_type' => $fuelType,
            'fuel_variant' => $fuelType === FuelType::Electric
                ? null
                : fake()->randomElement(['95', '98', '100', 'B7']),
            'gas_station' => fake()->company(),
            'litres_filled' => fake()->randomFloat(3, 5, 60),
            'amount' => fake()->randomFloat(2, 500, 8000),
            'odometer_at_fill' => fake()->numberBetween(0, 300000),
            'date_of_filling' => fake()->dateTimeBetween('-1 year', 'now'),
            'is_partial_fill' => false,
            'trip_km' => null,
        ];
    }

    public function partial(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_partial_fill' => true,
        ]);
    }
}
```

- [ ] **Step 6: Write the failing test for validation rules and DB nullability**

Create `tests/Feature/FuelEntryValidationRulesTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\FuelType;
use App\Models\Car;
use App\Models\FuelEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class FuelEntryValidationRulesTest extends TestCase
{
    use RefreshDatabase;

    private function baseData(Car $car): array
    {
        return [
            'car_id' => $car->id,
            'gas_station' => 'Test Station',
            'litres_filled' => 40,
            'amount' => 5000,
            'odometer_at_fill' => 1000,
            'date_of_filling' => now(),
        ];
    }

    public function test_fuel_type_and_fuel_variant_columns_are_nullable_in_the_database(): void
    {
        $this->assertTrue(Schema::hasColumn('fuel_entries', 'fuel_type'));
        $this->assertTrue(Schema::hasColumn('fuel_entries', 'fuel_variant'));

        $car = Car::factory()->create();
        $userId = \App\Models\User::factory()->create()->id;

        // Raw insert bypassing the model/validation layer entirely --
        // proves the DB itself does not enforce NOT NULL on these columns.
        DB::table('fuel_entries')->insert([
            'car_id' => $car->id,
            'user_id' => $userId,
            'fuel_type' => null,
            'fuel_variant' => null,
            'gas_station' => 'Legacy Station',
            'litres_filled' => 10,
            'amount' => 1000,
            'odometer_at_fill' => 500,
            'date_of_filling' => now(),
            'is_partial_fill' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('fuel_entries', [
            'gas_station' => 'Legacy Station',
            'fuel_type' => null,
        ]);
    }

    public function test_create_validation_requires_fuel_type(): void
    {
        $car = Car::factory()->create();

        $validator = Validator::make(
            $this->baseData($car),
            FuelEntry::validationRules(),
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('fuel_type', $validator->errors()->toArray());
    }

    public function test_create_validation_requires_fuel_variant_when_fuel_type_is_not_electric(): void
    {
        $car = Car::factory()->create();

        $validator = Validator::make(
            [...$this->baseData($car), 'fuel_type' => FuelType::Gasoline->value],
            FuelEntry::validationRules(),
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('fuel_variant', $validator->errors()->toArray());
    }

    public function test_create_validation_allows_missing_fuel_variant_when_fuel_type_is_electric(): void
    {
        $car = Car::factory()->create();

        $validator = Validator::make(
            [...$this->baseData($car), 'fuel_type' => FuelType::Electric->value],
            FuelEntry::validationRules(),
        );

        $this->assertFalse($validator->fails());
    }

    public function test_edit_validation_allows_omitting_fuel_type_and_fuel_variant_entirely(): void
    {
        $validator = Validator::make(
            ['gas_station' => 'Updated Station'],
            FuelEntry::validationRules(allFieldsRequired: false),
        );

        $this->assertFalse($validator->fails());
    }
}
```

- [ ] **Step 7: Run the tests to verify they fail first (enum/migration not yet applied) then pass**

Run: `php artisan test --filter=FuelEntryValidationRulesTest`
Expected (before Steps 1-5, if run early): FAIL / class not found. After Steps 1-5: `Tests: 5 passed`.

- [ ] **Step 8: Run the full test suite to check nothing else broke**

Run: `php artisan test`
Expected: `FuelEntryResourceTest` and `McpFuelHistoryServerTest` failures are expected at this point (they don't send `fuel_type`/`fuel_variant` yet — the Filament form and MCP tools don't require them until Tasks 2-3 wire the requirement in, but the factory now always sets valid values, so **existing factory-created records stay valid**; failures here should only come from tests that build form/tool payloads by hand without new fields). Confirm `FuelEntryValidationRulesTest` passes and note which other tests still fail (Tasks 2-3 fix those).

- [ ] **Step 9: Commit**

```bash
git add app/Enums/FuelType.php \
  database/migrations/2026_08_25_150000_add_fuel_type_and_fuel_variant_to_fuel_entries_table.php \
  app/Models/FuelEntry.php \
  database/factories/FuelEntryFactory.php \
  tests/Feature/FuelEntryValidationRulesTest.php
git commit -m "feat: add fuel type/variant enum, migration, and shared validation rules"
```

---

### Task 2: Filament form and table

**Files:**
- Modify: `app/Filament/Resources/FuelEntries/Schemas/FuelEntryForm.php`
- Modify: `app/Filament/Resources/FuelEntries/Tables/FuelEntriesTable.php`
- Modify: `tests/Feature/FuelEntryResourceTest.php`

**Interfaces:**
- Consumes: `App\Enums\FuelType` (Task 1) — used in `Select::make('fuel_type')->options(FuelType::class)`.
- Consumes: `fuel_entries.fuel_type` / `fuel_entries.fuel_variant` columns and casts (Task 1).
- Produces: nothing new consumed by later tasks — this task is UI-only.

- [ ] **Step 1: Update the Filament form**

Edit `app/Filament/Resources/FuelEntries/Schemas/FuelEntryForm.php`:

```php
<?php

namespace App\Filament\Resources\FuelEntries\Schemas;

use App\Enums\FuelType;
use App\Models\Car;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class FuelEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('car_id')
                    ->label('Car')
                    ->options(fn () => Car::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                Select::make('fuel_type')
                    ->label('Fuel type')
                    ->options(FuelType::class)
                    ->required()
                    ->live(),
                TextInput::make('fuel_variant')
                    ->label('Fuel variant')
                    ->helperText('The specific grade or product, e.g. "95", "B7", "Shell V-Power".')
                    ->maxLength(255)
                    ->required(fn (Get $get): bool => $get('fuel_type') !== FuelType::Electric->value),
                TextInput::make('gas_station')
                    ->required()
                    ->maxLength(255),
                TextInput::make('litres_filled')
                    ->label('Litres filled')
                    ->numeric()
                    ->minValue(0.001)
                    ->step(0.001)
                    ->required(),
                TextInput::make('amount')
                    ->label('Amount (RSD)')
                    ->numeric()
                    ->minValue(0.01)
                    ->step(0.01)
                    ->required(),
                TextInput::make('odometer_at_fill')
                    ->label('Odometer (km)')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                DateTimePicker::make('date_of_filling')
                    ->required()
                    ->default(now()),
                Checkbox::make('is_partial_fill')
                    ->label('Partial fill (tank not filled to full)')
                    ->live(),
                TextInput::make('trip_km')
                    ->label('Trip kilometres since last fill (optional)')
                    ->helperText('From the car\'s trip computer, if reset at the last fill-up — lets us estimate L/100km for this partial fill alone.')
                    ->numeric()
                    ->minValue(0.1)
                    ->step(0.1)
                    ->visible(fn (Get $get): bool => (bool) $get('is_partial_fill')),
            ]);
    }
}
```

- [ ] **Step 2: Update the Filament table**

Edit `app/Filament/Resources/FuelEntries/Tables/FuelEntriesTable.php` — insert two columns after `car.name`:

```php
<?php

namespace App\Filament\Resources\FuelEntries\Tables;

use App\Models\FuelEntry;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FuelEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date_of_filling')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('car.name')
                    ->label('Car')
                    ->sortable(),
                TextColumn::make('fuel_type')
                    ->label('Fuel')
                    ->badge(),
                TextColumn::make('fuel_variant')
                    ->label('Variant')
                    ->placeholder('—'),
                TextColumn::make('gas_station')
                    ->searchable(),
                TextColumn::make('litres_filled')
                    ->numeric(3)
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Amount (RSD)')
                    ->numeric(2)
                    ->sortable(),
                TextColumn::make('price_per_litre')
                    ->label('Price/L')
                    ->state(fn (FuelEntry $record): string => number_format(
                        $record->litres_filled > 0 ? $record->amount / $record->litres_filled : 0,
                        2
                    )),
                TextColumn::make('odometer_at_fill')
                    ->label('Odometer')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_partial_fill')
                    ->label('Partial')
                    ->boolean(),
                TextColumn::make('user.name')
                    ->label('Logged by'),
            ])
            ->defaultSort('date_of_filling', 'desc')
            ->recordActions([
                EditAction::make()
                    ->visible(fn (FuelEntry $record) => auth()->user()->can('update', $record)),
                DeleteAction::make()
                    ->visible(fn (FuelEntry $record) => auth()->user()->can('delete', $record)),
            ]);
    }
}
```

- [ ] **Step 3: Update existing form tests to send the now-required fields**

Edit `tests/Feature/FuelEntryResourceTest.php` — add `fuel_type`/`fuel_variant` to the two `fillForm` calls that create a new entry (`test_any_authenticated_user_can_log_a_fuel_entry_for_any_car` and `test_partial_fill_shows_trip_km_field_and_it_persists`). The two edit-only tests (`test_owner_can_edit_their_own_entry`, etc.) don't need changes since factory-created records already carry valid `fuel_type`/`fuel_variant`, and the edit form only patches the fields listed in `fillForm`.

```php
    public function test_any_authenticated_user_can_log_a_fuel_entry_for_any_car(): void
    {
        $car = Car::factory()->create();
        $user = $this->user();

        $this->actingAs($user);

        Livewire::test(CreateFuelEntry::class)
            ->fillForm([
                'car_id' => $car->id,
                'fuel_type' => 'gasoline',
                'fuel_variant' => '95',
                'gas_station' => 'Test Petrol Station',
                'litres_filled' => 42.5,
                'amount' => 5000,
                'odometer_at_fill' => 12345,
                'date_of_filling' => now(),
                'is_partial_fill' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $entry = FuelEntry::firstOrFail();
        $this->assertSame($user->id, $entry->user_id);
        $this->assertSame($car->id, $entry->car_id);
        $this->assertSame('RSD', $entry->currency);
    }

    public function test_partial_fill_shows_trip_km_field_and_it_persists(): void
    {
        $car = Car::factory()->create();
        $this->actingAs($this->user());

        Livewire::test(CreateFuelEntry::class)
            ->fillForm([
                'car_id' => $car->id,
                'fuel_type' => 'gasoline',
                'fuel_variant' => '95',
                'gas_station' => 'Test Petrol Station',
                'litres_filled' => 20,
                'amount' => 2500,
                'odometer_at_fill' => 10000,
                'date_of_filling' => now(),
                'is_partial_fill' => true,
                'trip_km' => 150.5,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $entry = FuelEntry::firstOrFail();
        $this->assertTrue($entry->is_partial_fill);
        $this->assertEquals(150.5, $entry->trip_km);
    }
```

- [ ] **Step 4: Add new tests for fuel type/variant requiredness in the form**

Append to `tests/Feature/FuelEntryResourceTest.php` (add `use App\Enums\FuelType;` to the imports):

```php
    public function test_creating_an_entry_without_fuel_type_fails_validation(): void
    {
        $car = Car::factory()->create();
        $this->actingAs($this->user());

        Livewire::test(CreateFuelEntry::class)
            ->fillForm([
                'car_id' => $car->id,
                'fuel_variant' => '95',
                'gas_station' => 'Test Petrol Station',
                'litres_filled' => 42.5,
                'amount' => 5000,
                'odometer_at_fill' => 12345,
                'date_of_filling' => now(),
            ])
            ->call('create')
            ->assertHasFormErrors(['fuel_type']);
    }

    public function test_creating_a_non_electric_entry_without_fuel_variant_fails_validation(): void
    {
        $car = Car::factory()->create();
        $this->actingAs($this->user());

        Livewire::test(CreateFuelEntry::class)
            ->fillForm([
                'car_id' => $car->id,
                'fuel_type' => 'diesel',
                'gas_station' => 'Test Petrol Station',
                'litres_filled' => 42.5,
                'amount' => 5000,
                'odometer_at_fill' => 12345,
                'date_of_filling' => now(),
            ])
            ->call('create')
            ->assertHasFormErrors(['fuel_variant']);
    }

    public function test_creating_an_electric_entry_without_fuel_variant_succeeds(): void
    {
        $car = Car::factory()->create();
        $this->actingAs($this->user());

        Livewire::test(CreateFuelEntry::class)
            ->fillForm([
                'car_id' => $car->id,
                'fuel_type' => FuelType::Electric->value,
                'gas_station' => 'Charging Station',
                'litres_filled' => 42.5,
                'amount' => 5000,
                'odometer_at_fill' => 12345,
                'date_of_filling' => now(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $entry = FuelEntry::firstOrFail();
        $this->assertSame(FuelType::Electric, $entry->fuel_type);
        $this->assertNull($entry->fuel_variant);
    }
```

- [ ] **Step 5: Run the tests**

Run: `php artisan test --filter=FuelEntryResourceTest`
Expected: all tests pass (existing tests updated in Step 3, new ones added in Step 4).

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/FuelEntries/Schemas/FuelEntryForm.php \
  app/Filament/Resources/FuelEntries/Tables/FuelEntriesTable.php \
  tests/Feature/FuelEntryResourceTest.php
git commit -m "feat: add fuel type/variant fields to the Filament form and table"
```

---

### Task 3: MCP tools

**Files:**
- Modify: `app/Mcp/Tools/AddFuelEntryTool.php`
- Modify: `app/Mcp/Tools/EditFuelEntryTool.php`
- Modify: `app/Mcp/Tools/ListFuelEntriesTool.php`
- Modify: `tests/Feature/McpFuelHistoryServerTest.php`

**Interfaces:**
- Consumes: `FuelEntry::validationRules()` (Task 1) — both `AddFuelEntryTool` and `EditFuelEntryTool` already call this; no change to how it's invoked, only to what it returns.
- Consumes: `App\Enums\FuelType` (Task 1) — for the description text and `ListFuelEntriesTool` output serialization.

- [ ] **Step 1: Add `fuel_type`/`fuel_variant` to `AddFuelEntryTool`'s schema**

Edit `app/Mcp/Tools/AddFuelEntryTool.php`:

```php
<?php

namespace App\Mcp\Tools;

use App\Models\FuelEntry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('add-fuel-entry')]
#[Description('Logs a fuel fill-up for a car. Use list-cars first to find the car_id. Currency is always RSD. fuel_variant is required unless fuel_type is electric. Set is_partial_fill=true and optionally trip_km (kilometres since the last fill, from the car\'s trip computer) when the tank was not filled to full.')]
class AddFuelEntryTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(FuelEntry::validationRules());

        $entry = FuelEntry::create([
            ...$validated,
            // The token owner is always the entry's owner -- never a tool
            // input, same discipline as the Filament form.
            'user_id' => $request->user()->id,
        ]);

        return Response::text(json_encode([
            'id' => $entry->id,
            'message' => "Fuel entry #{$entry->id} logged for car #{$entry->car_id}.",
        ], JSON_PRETTY_PRINT));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'car_id' => $schema->integer()
                ->description('The car to log this fill-up against. Use list-cars to look this up.')
                ->required(),
            'fuel_type' => $schema->string()
                ->description('One of: gasoline, diesel, lpg, electric.')
                ->required(),
            'fuel_variant' => $schema->string()
                ->description('The specific grade or product, e.g. "95", "98", "B7", "Shell V-Power". Required unless fuel_type is electric.'),
            'gas_station' => $schema->string()
                ->description('Name of the gas station.')
                ->required(),
            'litres_filled' => $schema->number()
                ->description('Litres of fuel put in.')
                ->required(),
            'amount' => $schema->number()
                ->description('Amount paid, in RSD.')
                ->required(),
            'odometer_at_fill' => $schema->integer()
                ->description('Odometer reading (km) at the time of this fill-up.')
                ->required(),
            'date_of_filling' => $schema->string()
                ->description('Date/time of the fill-up, e.g. 2026-08-25 14:30.')
                ->required(),
            'is_partial_fill' => $schema->boolean()
                ->description('True if the tank was not filled to full.')
                ->default(false),
            'trip_km' => $schema->number()
                ->description('Kilometres since the last fill, from the trip computer -- only meaningful when is_partial_fill is true.'),
        ];
    }
}
```

- [ ] **Step 2: Add `fuel_type`/`fuel_variant` to `EditFuelEntryTool`'s schema**

Edit `app/Mcp/Tools/EditFuelEntryTool.php` — add the two fields to `schema()` only (handler already forwards all validated fields via `$entry->update($validated)`):

```php
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'entry_id' => $schema->integer()
                ->description('The fuel entry to edit. Use list-fuel-entries to find it.')
                ->required(),
            'car_id' => $schema->integer()
                ->description('Move this entry to a different car.'),
            'fuel_type' => $schema->string()
                ->description('One of: gasoline, diesel, lpg, electric.'),
            'fuel_variant' => $schema->string()
                ->description('The specific grade or product, e.g. "95", "98", "B7", "Shell V-Power".'),
            'gas_station' => $schema->string()
                ->description('Name of the gas station.'),
            'litres_filled' => $schema->number()
                ->description('Litres of fuel put in.'),
            'amount' => $schema->number()
                ->description('Amount paid, in RSD.'),
            'odometer_at_fill' => $schema->integer()
                ->description('Odometer reading (km) at the time of this fill-up.'),
            'date_of_filling' => $schema->string()
                ->description('Date/time of the fill-up, e.g. 2026-08-25 14:30.'),
            'is_partial_fill' => $schema->boolean()
                ->description('True if the tank was not filled to full.'),
            'trip_km' => $schema->number()
                ->description('Kilometres since the last fill, from the trip computer.'),
        ];
    }
```

Update the class's `#[Description]` attribute (just above `class EditFuelEntryTool`) to mention the new fields:

```php
#[Description('Edits a fuel entry. Only the entry\'s owner or an admin may edit it -- same rule as the app itself. Only include the fields you want to change; entry_id is the only required field. If you set fuel_type to a non-electric value, also set fuel_variant.')]
```

- [ ] **Step 3: Include `fuel_type`/`fuel_variant` in `ListFuelEntriesTool`'s output**

Edit `app/Mcp/Tools/ListFuelEntriesTool.php` — add the two fields to the mapped output array, right after `car_id`:

```php
        $entries = $query->get()->map(fn (FuelEntry $entry): array => [
            'id' => $entry->id,
            'car_id' => $entry->car_id,
            'fuel_type' => $entry->fuel_type?->value,
            'fuel_variant' => $entry->fuel_variant,
            'date_of_filling' => $entry->date_of_filling->toDateTimeString(),
            'gas_station' => $entry->gas_station,
            'litres_filled' => (float) $entry->litres_filled,
            'amount' => (float) $entry->amount,
            'odometer_at_fill' => $entry->odometer_at_fill,
            'is_partial_fill' => $entry->is_partial_fill,
            'trip_km' => $entry->trip_km === null ? null : (float) $entry->trip_km,
        ])->all();
```

- [ ] **Step 4: Update the existing `test_add_fuel_entry_creates_an_entry_owned_by_the_token_holder` test**

Edit `tests/Feature/McpFuelHistoryServerTest.php`:

```php
    public function test_add_fuel_entry_creates_an_entry_owned_by_the_token_holder(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $car = Car::factory()->create();

        $response = $this->callTool($token, 'add-fuel-entry', [
            'car_id' => $car->id,
            'fuel_type' => 'gasoline',
            'fuel_variant' => '95',
            'gas_station' => 'Test Station',
            'litres_filled' => 40.5,
            'amount' => 5000,
            'odometer_at_fill' => 12345,
            'date_of_filling' => '2026-08-25 10:00:00',
        ]);

        $response->assertOk();
        $entry = FuelEntry::firstOrFail();
        $this->assertSame($user->id, $entry->user_id);
        $this->assertSame($car->id, $entry->car_id);
        $this->assertSame('RSD', $entry->currency);
    }
```

Also update `test_add_fuel_entry_rejects_invalid_data` to include valid `fuel_type`/`fuel_variant` alongside the deliberately-invalid `litres_filled`, so the test still isolates the one field it's checking:

```php
    public function test_add_fuel_entry_rejects_invalid_data(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $car = Car::factory()->create();

        $response = $this->callTool($token, 'add-fuel-entry', [
            'car_id' => $car->id,
            'fuel_type' => 'gasoline',
            'fuel_variant' => '95',
            'gas_station' => 'Test Station',
            'litres_filled' => -5, // invalid: must be positive
            'amount' => 5000,
            'odometer_at_fill' => 12345,
            'date_of_filling' => '2026-08-25 10:00:00',
        ]);

        $this->assertSame(0, FuelEntry::count());
        $this->assertTrue((bool) $response->json('result.isError'));
    }
```

- [ ] **Step 5: Add new tests for fuel type/variant validation via MCP**

Append to `tests/Feature/McpFuelHistoryServerTest.php`:

```php
    public function test_add_fuel_entry_requires_fuel_type(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $car = Car::factory()->create();

        $response = $this->callTool($token, 'add-fuel-entry', [
            'car_id' => $car->id,
            'fuel_variant' => '95',
            'gas_station' => 'Test Station',
            'litres_filled' => 40.5,
            'amount' => 5000,
            'odometer_at_fill' => 12345,
            'date_of_filling' => '2026-08-25 10:00:00',
        ]);

        $this->assertSame(0, FuelEntry::count());
        $this->assertTrue((bool) $response->json('result.isError'));
    }

    public function test_add_fuel_entry_requires_fuel_variant_when_not_electric(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $car = Car::factory()->create();

        $response = $this->callTool($token, 'add-fuel-entry', [
            'car_id' => $car->id,
            'fuel_type' => 'diesel',
            'gas_station' => 'Test Station',
            'litres_filled' => 40.5,
            'amount' => 5000,
            'odometer_at_fill' => 12345,
            'date_of_filling' => '2026-08-25 10:00:00',
        ]);

        $this->assertSame(0, FuelEntry::count());
        $this->assertTrue((bool) $response->json('result.isError'));
    }

    public function test_add_fuel_entry_allows_missing_fuel_variant_when_electric(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $car = Car::factory()->create();

        $response = $this->callTool($token, 'add-fuel-entry', [
            'car_id' => $car->id,
            'fuel_type' => 'electric',
            'gas_station' => 'Charging Station',
            'litres_filled' => 40.5,
            'amount' => 5000,
            'odometer_at_fill' => 12345,
            'date_of_filling' => '2026-08-25 10:00:00',
        ]);

        $response->assertOk();
        $this->assertFalse((bool) $response->json('result.isError'));
        $entry = FuelEntry::firstOrFail();
        $this->assertNull($entry->fuel_variant);
    }

    public function test_edit_fuel_entry_can_change_just_fuel_type_without_resending_fuel_variant(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $entry = FuelEntry::factory()->create(['user_id' => $user->id, 'fuel_type' => \App\Enums\FuelType::Diesel, 'fuel_variant' => 'B7']);

        $response = $this->callTool($token, 'edit-fuel-entry', [
            'entry_id' => $entry->id,
            'fuel_type' => 'gasoline',
        ]);

        $response->assertOk();
        $this->assertFalse((bool) $response->json('result.isError'));
        $this->assertSame(\App\Enums\FuelType::Gasoline, $entry->refresh()->fuel_type);
        $this->assertSame('B7', $entry->fuel_variant);
    }

    public function test_list_fuel_entries_includes_fuel_type_and_variant(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $car = Car::factory()->create();
        FuelEntry::factory()->create([
            'car_id' => $car->id,
            'user_id' => $user->id,
            'fuel_type' => \App\Enums\FuelType::Lpg,
            'fuel_variant' => 'Autogas',
        ]);

        $response = $this->callTool($token, 'list-fuel-entries', ['car_id' => $car->id]);
        $text = $this->toolText($response);

        $this->assertStringContainsString('lpg', $text);
        $this->assertStringContainsString('Autogas', $text);
    }
```

- [ ] **Step 6: Run the tests**

Run: `php artisan test --filter=McpFuelHistoryServerTest`
Expected: all tests pass.

- [ ] **Step 7: Run the entire test suite**

Run: `php artisan test`
Expected: `OK` — all tests pass, including `FuelEntryValidationRulesTest` and `FuelEntryResourceTest` from earlier tasks.

- [ ] **Step 8: Commit**

```bash
git add app/Mcp/Tools/AddFuelEntryTool.php \
  app/Mcp/Tools/EditFuelEntryTool.php \
  app/Mcp/Tools/ListFuelEntriesTool.php \
  tests/Feature/McpFuelHistoryServerTest.php
git commit -m "feat: expose fuel type/variant through the MCP fuel entry tools"
```

---

## Final Verification

- [ ] Run `php artisan test` one more time and confirm the whole suite is green.
- [ ] Run `vendor/bin/pint --dirty` (or `vendor/bin/pint` on the changed files) to keep formatting consistent with the rest of the codebase, since this repo has Pint installed as a dev dependency.
