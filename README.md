# Expense Tracker (PHP + SQLite)

Backend: single `api.php` using SQLite (`data.sqlite` auto-created). Frontend stays in `index.html`/`app.js`/`style.css`.

## Run locally
1) Install PHP with SQLite support (PHP 8+ recommended).
2) From this folder, start the built-in server:
```bash
php -S localhost:8000
```
3) Open http://localhost:8000 in the browser.

## API overview (all JSON)
- `GET /api.php?path=state` → `{ wallet, caps, expenses, incomes, aiEnabled }`
- `POST /api.php?path=expense` body `{ amount, merchant, beneficial, ts? }`
- `PUT /api.php?path=expense&id={id}` body `{ amount, merchant, beneficial, ts? }`
- `DELETE /api.php?path=expense&id={id}`
- `POST /api.php?path=income` body `{ amount, source, ts? }`
- `POST /api.php?path=caps` body `{ day, week, month }`
- `POST /api.php?path=prefs` body `{ aiEnabled }`

Each write updates the wallet balance server-side. Seed rows for wallet/caps/prefs are created automatically on first request.
# expenses-tracker-SUT