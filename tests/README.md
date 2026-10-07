# CEP test scripts (dev tooling)

These scripts exercise the running CEP against a local dev server. They are NOT
needed on hosting — they exist so the demo workflow claims in README-CEP.md can
be reproduced and verified.

## Prerequisites
- PHP dev server serving the project root:  `php -S 127.0.0.1:8081` (from the NewLifeFitness-CEP folder, with the CEP DB env vars exported)
- MariaDB/MySQL running locally with `newlife_cep` imported (`cep_install.sql` + `cep_seed.sql`)
- The scripts default to: DB user `cep_user`, password `CepLocal_2026!`, database `newlife_cep` — edit the MYSQL= line at the top of `e2e_demo.sh` if yours differ. (These are local dev credentials only; the shipped package contains no real credentials.)

## Scripts
| Script | Purpose | Result (verified 2026-09-07) |
|---|---|---|
| `smoke_community.sh` | login as demo user + GET all 11 community pages, check for PHP errors | 11/11 |
| `smoke_admin_community.sh` | admin login + GET all 11 admin/community pages | 11/11 |
| `workflow_community.sh` | mid-level workflow checks (registration, feedback, surveys, requests) | green |
| `e2e_demo.sh` | the FULL 15-step demo journey with a fresh user (fresh email each run) — asserts HTTP redirects, flash messages, and DB rows after every step | **48/48** |

## Run
```bash
cd NewLifeFitness-CEP
export NLF_DB_HOST=127.0.0.1 NLF_DB_USER=cep_user NLF_DB_PASS='CepLocal_2026!' NLF_DB_NAME=newlife_cep
php -S 127.0.0.1:8081 &          # separate terminal or background
./tests/e2e_demo.sh               # expects a GREEN summary
```

`e2e_demo.sh` creates a brand-new community user every run (unique email via
timestamp), so it can be re-run any number of times.
