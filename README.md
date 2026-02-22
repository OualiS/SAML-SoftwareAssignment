# Salaheddine OUALI ASML Test

## Dynamic TXT Parser

The import flow is:
1. Scan the import folder for files matching `EQUIPMENTS_*.txt`.
2. Pick the latest file (by filename order).
3. Dispatch a queue job that parses and replace into `equipments`.

Import folder is configured by:
- `EQUIPMENTS_FILEPATH` in `.env` (default: `./data/`)

Queue connection:
- `QUEUE_CONNECTION=database`

## Corrupt Files Handling (extra)

To handle corrupt files safely, the parser performs these checks in `ImportEquipmentsFile`:

1. File existence check:
- Throws an error if the target file does not exist.

2. Readability/content check:
- Throws an error if the file cannot be read or is empty.

3. Header parsing check:
- Throws an error if the parser cannot detect/parse the TXT header columns.

4. Safe write strategy:
- Data is fully parsed in memory first.
- Database writes happen only at the end, inside a single transaction (`upsert` in chunks).
- If an exception is thrown before/during write, transaction rollback prevents partial updates.

5. No-op safety:
- If parsing produces no usable rows, import is aborted and no database write is performed.
- The `last_imported_file` cache key is updated only after a successful import.

## Run The Job Manually

Using `compose.dev.yaml`:

1. Start containers:
```bash
docker compose -f compose.dev.yaml up -d --build
```

2. Run migrations:
```bash
docker compose -f compose.dev.yaml exec workspace php artisan migrate
```

3. Dispatch the import job:
```bash
docker compose -f compose.dev.yaml exec workspace php artisan app:scan-equipments-imports
```

4. Process queued jobs:
```bash
docker compose -f compose.dev.yaml exec workspace php artisan queue:work --stop-when-empty
```

One-shot command:
```bash
docker compose -f compose.dev.yaml run --rm workspace bash -lc "php artisan app:scan-equipments-imports && php artisan queue:work --stop-when-empty"
```

## Scheduler

Docker:
```bash
docker compose -f compose.dev.yaml exec workspace php artisan schedule:run
docker compose -f compose.dev.yaml exec workspace php artisan schedule:work
```

## Notes

- `routes/console.php` currently schedules `equipments:scan-imports` at 03:00 AM.
