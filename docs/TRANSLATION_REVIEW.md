# English translation review — 1.1.1

Date: 2026-10-09, UTC.

The supplied 1.1.0 archive contained Russian text in twelve documentation files,
one localization dictionary, and three unit-test files. All documentation has
been translated into professional English, preserving configuration semantics,
migration restrictions, code examples, historical evidence, and unexecuted-test
disclosures.

`REQUIREMENTS_RU.md` has been renamed to `REQUIREMENTS.md`. Its original 1.0.0
requirements remain explicitly identified as a historical brief.

The module's source interface messages were already English. The bundled
`i18n/ru_RU.csv` dictionary has been removed; standard Magento translation hooks
remain available. Existing installations must remove that obsolete file during
an upgrade, then clear translation caches and deploy their configured locales.
See `i18n/README.md` and the installation instructions.

Test names and customer samples are English. Multibyte and invalid UTF-8
coverage remains in place; non-Cyrillic Unicode fixtures retain the relevant
validation coverage. Language-scanner tests use Unicode escape sequences to
create Cyrillic data at runtime without including Russian text in the package.

`tools/check_english_content.py` scans all file and directory names and all UTF-8
file contents. It fails on residual Cyrillic or unscanned content. Its selected
generated output file is the only explicitly reported content omission. The
scanner does not establish translation quality; the documentation was also
reviewed for technical meaning and Markdown structure.

Store-owned labels, customer messages, catalog records, custom email templates,
and external language packs are not rewritten by this package update. A store
may still display merchant content or externally supplied translations in a
different language.

Executed checks and remaining runtime limits are recorded in `TEST_REPORT.md`.
