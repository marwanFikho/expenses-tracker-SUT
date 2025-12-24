# Expense Tracker - Project Structure

## DIR Architecture Overview

```
expenses-tracker-SUT/
├── frontend/
│   ├── index.html          # Main app
│   ├── login.html          # Auth page
│   ├── app.js              # Frontend logic
│   ├── style.css           # Styles
│   └── README.md           # Frontend docs (this file)
│
├── backend/
│   ├── handlers/           # Route handlers
│   │   ├── auth.php        # Register, login, logout
│   │   ├── protected.php   # Expense, income, caps, prefs
│   │   └── ai.php          # AI advice & chatbot
│   │
│   └── utils/              # Reusable utilities
│       ├── http.php        # Request/response helpers
│       ├── jwt.php         # Token creation & verification
│       ├── db.php          # Database queries
│       └── llm.php         # LLM integration
│
├── api.php                 # Main entry point (router)
├── schema.sql              # Database schema
├── README.md               # This file
└── ai.php (deprecated)     # Old monolithic file
```

## Key Features

### Modular Design
- **Handlers** contain business logic for each endpoint group
- **Utils** contain reusable, dependency-free functions
- **api.php** is a lightweight router that ties everything together

### Clean Separation
- **Authentication** (handlers/auth.php): User registration & login
- **Protected endpoints** (handlers/protected.php): Expenses, income, caps, preferences
- **AI Features** (handlers/ai.php): Financial advice & chatbot
- **Infrastructure** (utils/*): JWT, HTTP, DB, LLM operations

### Small Codebase
- Main file reduced from 600+ lines to ~100 lines
- Each module has a single responsibility
- Easy to locate and modify features
- Representative of production patterns (modular, scalable)

## Database

MySQL with the following tables:
- `users` - User accounts (email, password_hash)
- `wallet` - User balance
- `expenses` - Spending records
- `incomes` - Income records
- `caps` - Daily/weekly/monthly spending limits
- `prefs` - User preferences (AI enabled/disabled)

## API Endpoints

### Public (No Auth)
- `POST /api.php?path=auth/register` - Create account
- `POST /api.php?path=auth/login` - Get JWT token
- `POST /api.php?path=auth/logout` - Clear session

### Protected (Requires Bearer Token)
- `GET /api.php?path=state` - Get user state
- `POST /api.php?path=expense` - Add expense
- `PUT /api.php?path=expense?id=X` - Update expense
- `DELETE /api.php?path=expense?id=X` - Delete expense
- `POST /api.php?path=income` - Add income
- `POST /api.php?path=caps` - Set spending caps
- `POST /api.php?path=prefs` - Update preferences
- `POST /api.php?path=ai` - Get AI financial advice
- `POST /api.php?path=chatbot` - Chat with spending advisor

## How to Add a New Feature

1. **Create handler** in `backend/handlers/[feature].php`
2. **Add route** in `api.php` switch statement
3. **Use existing utils** (http, jwt, db, llm)
4. **Require** the handler in api.php top section

Example:
```php
// In api.php, add:
require_once __DIR__ . '/backend/handlers/budget.php';

// In router:
case 'budget':
    handle_budget($db, $user_id, $method);
    break;
```

## Development Tips

- All database queries are prepared statements (SQL injection safe)
- JWT tokens expire after 7 days
- AI integration has curl + file_get_contents fallback
- Token passed via `Authorization: Bearer [token]` header
