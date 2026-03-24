# Model List

The Model List is the home screen of Laravel Model Explorer. It shows every Eloquent model discovered in your configured `model_paths`.

## What You See

Each model card displays:

- **Class name** — the short model name (e.g. `Post`, not the fully-qualified class)
- **Table** — the underlying database table
- **Row count** — a live count of records in the table
- **Key badges** — at a glance indicators such as whether the model uses soft deletes, has a registered policy, or other notable traits

## Search

A search box at the top of the page filters models in real time by class name or table name. You can also focus it from anywhere on the page with the `/` key or `Cmd+K` (`Ctrl+K` on Windows/Linux).

## Navigating to a Model

Click any card to open the [Model Detail](/guide/model-detail) view for that model.
