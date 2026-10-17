# Rollback / Recovery — v1.0.0

This is a fresh-install baseline, so there is no legacy City ePaper in-place rollback.

Before production onboarding, back up:
- the ePaper SaaS MariaDB database
- `config/config.php`
- `storage/`
- Cloudways application files

For a failed fresh installation, restore the fresh application backup/database or recreate the dedicated ePaper application and reinstall.

Never point an existing legacy City ePaper database at this schema installer.
