# CEP Local Development Configuration

## IMPORTANT — why config.local.php is NOT present

The CEP fork was created from the personal project. The personal project's
`config.local.php` (which contained the **personal** InfinityFree database
credentials) was **deliberately removed** from this CEP copy so that:

1. Personal credentials are never shipped, shared, or reused in the CEP.
2. The CEP and personal deployments remain completely independent.

## How this fork resolves its database (unchanged logic)

`db_connect.php` reads credentials in this priority order:

1. Environment variables: `NLF_DB_HOST`, `NLF_DB_USER`, `NLF_DB_PASS`, `NLF_DB_NAME`
2. `config.local.php` (if you create one — see `config.example.php`)
3. XAMPP defaults (localhost / root / empty / `newlife_fitness`)

## Local development (this sandbox / your XAMPP)

The local CEP database is `newlife_cep` (imported from `cep_install.sql` +
`cep_seed.sql`). To run the CEP locally with PHP's built-in server:

```bash
export NLF_DB_HOST=127.0.0.1
export NLF_DB_USER=cep_user
export NLF_DB_PASS='CepLocal_2026!'
export NLF_DB_NAME=newlife_cep
php -S 127.0.0.1:8080
```

(If you prefer XAMPP root access, you can instead create a
`config.local.php` pointing at your local database — that file is blocked
from web access by `.htaccess` and should never be committed or zipped.)

## Deploying the CEP to its own InfinityFree account (summary)

Full instructions: see `DEPLOY-CEP.md`. In short:

1. Create a brand-new InfinityFree account (different email).
2. Create the database; note host / username / password / name.
3. Upload the CEP zip; import `cep_install.sql` (+ `cep_seed.sql` only for demo).
4. Create `config.local.php` on the server with **that account's** credentials.
5. Login as admin (admin / admin123) and **change the password immediately**.
