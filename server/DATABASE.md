# ShipSmart Database Reference

## Apply schema

**Automatic (recommended for XAMPP):**  
PHP creates missing tables on every request via `server/includes/db_install.php`.  
Open once in the browser: `http://localhost/YOUR_FOLDER/server/setup.php`

**New database (manual):**
```bash
mysql -u root < server/schema.sql
```

**Existing database (add missing tables):**
```bash
mysql -u root shipsmart_db < server/migrations/002_complete_schema.sql
```

## Tables (12)

| Table | Purpose |
|-------|---------|
| `users` | Accounts with `admin` / `user` roles |
| `user_sessions` | Login session tokens |
| `password_reset_tokens` | Forgot-password flow |
| `login_attempts` | Failed/successful login audit |
| `shipments` | Shipment records (search API) |
| `shipment_status_history` | Status timeline per shipment |
| `user_shipments` | User ↔ shipment (owner / watcher) |
| `tracking_queries` | Log of tracking lookups |
| `feedback` | User feedback form data |
| `shipment_documents` | Uploaded files metadata |
| `email_log` | Sent email audit trail |
| `audit_log` | Admin action log |

## Demo accounts

| Email | Password | Role |
|-------|----------|------|
| admin@shipsmart.com | password | admin |
| user@shipsmart.com | password | user |

## Entity diagram (simplified)

```
users ──┬── user_sessions
        ├── password_reset_tokens
        ├── login_attempts
        ├── user_shipments ── shipments ── shipment_status_history
        ├── tracking_queries
        ├── feedback
        ├── shipment_documents ── shipments
        ├── email_log
        └── audit_log
```
