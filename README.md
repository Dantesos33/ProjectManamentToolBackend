# DeesOps API

Laravel REST API for **DeesOps**, a multi-tenant B2B project management platform. It handles authentication, tenant isolation, and role-based access control. The React frontend lives in a separate `frontend/` folder.

## Tech stack

| Layer | Technology |
|---|---|
| Framework | Laravel (PHP 8.2+) |
| Database | MySQL (InnoDB) |
| Authentication | Laravel Sanctum (bearer tokens) |
| Authorization | Laravel Policies + role middleware |
| Real-time (planned) | Laravel Reverb |
| Testing | Pest |

## Features

- **Multi-tenancy:** every company is a tenant. Users, projects, and tasks are isolated by `tenant_id` using Eloquent global scopes.
- **Workspace registration:** registering creates a new tenant and its first user as `admin`, in a single database transaction.
- **Workspace login:** users sign in with `domain` + `email` + `password`, so the same email can exist in different tenants.
- **Role-based access control:** four cumulative roles (`viewer`, `developer`, `manager`, `admin`).
- **Token auth:** hashed Sanctum tokens stored in `personal_access_tokens`, revoked on logout.

## Roles

Roles are cumulative: each role can do everything the one below it can.

| Capability | Viewer | Developer | Manager | Admin |
|---|:-:|:-:|:-:|:-:|
| View projects and tasks | ✓ | ✓ | ✓ | ✓ |
| Move tasks between columns | ✗ | ✓ | ✓ | ✓ |
| Create / edit / delete projects and tasks | ✗ | ✗ | ✓ | ✓ |
| Invite users, change roles | ✗ | ✗ | ✗ | ✓ |
| View audit logs | ✗ | ✗ | ✗ | ✓ |

The role and `tenant_id` are always set by the server. They are never accepted from a request body.

## Getting started

### Requirements

- PHP 8.2+
- Composer
- MySQL 8+
- Node.js 20+ (only for the frontend)

### Installation

```bash
git clone <your-repo-url>
cd <your-repo>/backend

composer install
cp .env.example .env
php artisan key:generate
```

Configure your database in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=deesops
DB_USERNAME=root
DB_PASSWORD=

FRONTEND_URL=http://localhost:5173
SESSION_DRIVER=array
```

Run migrations and start the server:

```bash
php artisan migrate
php artisan serve
```

The API is now available at `http://localhost:8000/api`.

### CORS

In `config/cors.php`, allow the frontend origin:

```php
'paths' => ['api/*'],
'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:5173')],
```

## API reference

Send `Accept: application/json` on every request, otherwise Laravel redirects on validation errors instead of returning JSON.

### Auth

| Method | Endpoint | Auth | Description |
|---|---|:-:|---|
| POST | `/api/auth/register` | No | Create a tenant and its first admin |
| POST | `/api/auth/login` | No | Log in with domain, email, password |
| POST | `/api/auth/logout` | Yes | Revoke the current token |
| POST | `/api/auth/logout-all` | Yes | Revoke all of the user's tokens |
| GET | `/api/me` | Yes | Current user |

Protected routes expect `Authorization: Bearer <token>`.

#### Register

```http
POST /api/auth/register
```

```json
{
  "company_name": "Acme Inc",
  "domain": "acme",
  "name": "Jane Doe",
  "email": "jane@acme.com",
  "password": "secret1234",
  "password_confirmation": "secret1234"
}
```

Response `201`:

```json
{
  "token": "1|abc123...",
  "user": { "id": 1, "tenant_id": 1, "name": "Jane Doe", "email": "jane@acme.com", "role": "admin" }
}
```

#### Login

```http
POST /api/auth/login
```

```json
{ "domain": "acme", "email": "jane@acme.com", "password": "secret1234" }
```

Returns the same shape as register with status `200`. Wrong domain, email, or password all return the same `422` message, so workspace and account existence is not revealed.

### Rate limits

| Endpoint | Limit |
|---|---|
| Register | 5 per minute per IP |
| Login | 10 per minute per IP |

## Project structure

```
app/
  Enums/UserRole.php
  Http/
    Controllers/Api/AuthController.php
    Requests/{RegisterRequest, LoginRequest}.php
  Models/{Tenant, User}.php
database/migrations/
routes/api.php
```

## Database

| Table | Purpose |
|---|---|
| `tenants` | Companies (`name`, unique `domain`) |
| `users` | Members of a tenant, unique on `(tenant_id, email)` |
| `personal_access_tokens` | Sanctum tokens (hashed) |

## Testing

```bash
php artisan test
```

## Roadmap

- [x] Tenants and users schema
- [x] Register (tenant + admin)
- [x] Login (domain + email + password)
- [x] Logout
- [ ] Refresh tokens (httpOnly cookie, rotation, reuse detection)
- [ ] Invitations and user management
- [ ] Projects and tasks API with policies
- [ ] Kanban status updates with ordering
- [ ] Real-time updates with Laravel Reverb
- [ ] Audit logging and admin activity feed

## Security notes

- Passwords are hashed through the model's `hashed` cast.
- Tokens are stored as SHA-256 hashes and shown only once.
- Tenant isolation is enforced at the query level with global scopes, not in the frontend.
- Frontend role checks are for UX only. Policies are the real enforcement.

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

Built on the [Laravel](https://laravel.com) framework.
