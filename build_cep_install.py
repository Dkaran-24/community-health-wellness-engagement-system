#!/usr/bin/env python3
"""Build cep_install.sql — the single import file for the CEP database.

Combines:
  1. The original gym schema (database.sql) with CREATE DATABASE / USE removed
     (the CEP DB is selected by the user in phpMyAdmin, never hardcoded)
  2. The CEP community extension schema (cep_schema.sql)

Output: NewLifeFitness-CEP/cep_install.sql
This is a BUILD-TIME tool only; it does not touch any live database.
"""
import re, pathlib

root = pathlib.Path(__file__).parent

base = (root / "database.sql").read_text(encoding="utf-8")
cep  = (root / "cep_schema.sql").read_text(encoding="utf-8")

# Remove CREATE DATABASE ... ; (may span multiple lines) and USE ...;
base = re.sub(r"CREATE\s+DATABASE\s+IF\s+NOT\s+EXISTS.*?;\s*", "", base, flags=re.I | re.S)
base = re.sub(r"^USE\s+[^;]+;\s*", "", base, flags=re.I | re.M)

header = """-- =====================================================================
-- NEW LIFE FITNESS — COMMUNITY ENGAGEMENT PROJECT (CEP)
-- MASTER INSTALLER — import this ONE file into the CEP database
-- ---------------------------------------------------------------------
-- HOW TO USE (on the CEP hosting ONLY — e.g. the SEPARATE InfinityFree
-- account created for the college CEP):
--   1. Create a MySQL database + user in the CEP hosting control panel
--      (e.g.  if0_XXXXXXXX_newlife_cep )
--   2. Open phpMyAdmin and SELECT that CEP database in the left sidebar
--   3. Import this file (Import tab -> Choose File -> cep_install.sql)
--
-- ⚠ CEP ISOLATION RULES:
--   * This file is for the CEP database ONLY.
--   * NEVER import it into the personal project's production database.
--   * The personal project keeps using its own database.sql, untouched.
--
-- CONTENTS:
--   PART 1 — Original gym tables (settings, admins, membership_plans,
--            trainers, members, attendance, fees, offers, email_*)
--            with demo seed rows, exactly as the personal project uses.
--   PART 2 — CEP community tables (19 new tables) with relationships.
-- =====================================================================

"""

out = (header
       + "/* ===================== PART 1 — GYM BASE ===================== */\n\n"
       + base.strip() + "\n\n"
       + "/* ===================== PART 2 — CEP COMMUNITY ================= */\n\n"
       + cep.strip() + "\n")

(root / "cep_install.sql").write_text(out, encoding="utf-8")
print("Wrote cep_install.sql:", len(out), "bytes")
