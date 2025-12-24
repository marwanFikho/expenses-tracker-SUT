# Expense Tracker (PHP + SQLite)

Backend: single `api.php` using SQLite (`data.sqlite` auto-created). Frontend stays in `index.html`/`app.js`/`style.css`.

## Run locally
1) Install PHP with SQLite support (PHP 8+ recommended).
2) From this folder, start the built-in server:
```bash
php -S localhost:8000
```
3) Open http://localhost:8000 in the browser.

## AI insights (optional)

To enable server-side AI insights you must set an environment variable with your OpenAI-compatible API key before starting the PHP server:

On Windows (PowerShell):

```powershell
$env:OPENAI_API_KEY = "sk-..."
php -S localhost:8000
```

On macOS / Linux:

```bash
export OPENAI_API_KEY="sk-..."
php -S localhost:8000
```

Endpoint: `POST /api.php?path=insights` — server will read recent expenses and return AI-generated insights. The response attempts to return a JSON object under the `insights` key; if parsing fails the raw text from the model is returned under `text`.

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