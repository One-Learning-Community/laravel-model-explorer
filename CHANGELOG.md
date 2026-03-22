# Changelog

All notable changes to `laravel-model-explorer` will be documented in this file.

## v1.0.0 - 2026-03-22

### What's new

- Model discovery — scans configured paths to find all Eloquent models
- Model detail view — DB columns, casts, fillable/hidden/guarded, relations with type badges and foreign keys, scopes with source snippets, traits, and accessor snippets
- Record lookup — find any record by primary key or unique field; browse raw attributes, lazy-loaded accessor values, and expandable relations with drill-down navigation
- Breadcrumb trail — tracks drill-down navigation history through related records
- Attribute filter — search attributes by name or formatted value
- Relationship graph — interactive force-directed SVG graph of all model relationships
- Authorization via `viewModelExplorer` gate (defaults to `local` environment only)
- All DB reads wrapped in rolled-back transactions with `Model::withoutEvents()` to prevent accidental writes
