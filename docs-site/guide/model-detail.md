# Model Detail

The Model Detail view gives you the full structure of a single Eloquent model across four sections.

## Columns

A table of every column in the model's database table, showing:

- **Name** — column name; foreign key columns are badged to signal the relationship
- **Type** — the raw database type (e.g. `bigint`, `varchar`, `json`)
- **Cast** — the Eloquent cast applied, if any (e.g. `datetime`, `array`, `encrypted`)
- **Nullable / Default** — whether the column accepts nulls and its default value

## Relations

Every relationship method detected on the model, showing:

- **Method name** — the PHP method that defines the relation
- **Type badge** — colour-coded by relation kind (HasOne, BelongsTo, BelongsToMany, Morph variants, etc.)
- **Related model** — the target model class
- **Source** — whether the method is defined directly on the model or inherited via a trait or parent class

## Scopes

Local query scopes defined on the model. Each scope shows:

- **Method name** — the scope method (without the `scope` prefix)
- **Parameters** — the method signature, excluding the implicit `$query` argument
- **Source snippet** — the scope body, syntax-highlighted, expandable in a modal

## Traits

All traits used by the model, filtered according to `excluded_trait_prefixes` in the config. Each entry shows the fully-qualified trait name and whether it originates from the model itself or a parent class.
