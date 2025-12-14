How to apply migrations

This project stores SQL migration files in `migrations/`.

To apply migration manually via MySQL CLI:

```bash
mysql -u <user> -p <database> < migrations/001_create_guide_monthly_stats.sql
```

To apply migrations using a PHP script, run:

```bash
php scripts/apply_migrations.php
```

Make sure your `includes/database.php` credentials are set and usable by the CLI or chosen environment.
