# Record Browser

The Record Browser lets you look up individual records in your database and inspect their attributes, accessor values, and related models — all without writing a single Tinker command.

## Looking Up a Record

From the [Model Detail](/guide/model-detail) view, click **Browse Records**. You can search by:

- **Primary key** — enter the ID directly
- **Unique field** — select any unique column from the dropdown and enter a value

## What You See

Once a record is loaded, the browser shows:

- **Raw attributes** — every column value from `$model->getAttributes()`, displayed as a key/value table
- **Accessor values** — virtual and computed attributes are resolved lazily (on demand) to avoid side effects from expensive accessors; click the **Resolve** button next to any accessor to fetch its value
- **Relations** — each detected relation is listed; click **Load** to fetch the related record(s) and drill in

## Drilling Into Relations

Loading a relation opens the related record(s) inline. From there you can continue loading further relations, building a breadcrumb trail of the navigation chain so you can trace back to where you started.

## Safety

All database reads in the Record Browser are wrapped in a rolled-back transaction and run with model events disabled. This means:

- Observers and listeners are never triggered
- Accessors that accidentally write to the database cannot cause permanent changes
- The database is always left in its original state after a lookup
