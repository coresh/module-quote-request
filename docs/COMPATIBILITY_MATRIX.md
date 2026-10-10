# Compatibility and verification matrix

Platform requirements reviewed 2026-10-08; release validation updated 2026-10-09. Target platforms: Magento **Open Source** 2.4.8-p5 and 2.4.9. The module declares Composer `php: ^8.3` (PHP 8.3–8.x); this constraint excludes a future PHP 9. This specifies the module's minimum language level, rather than promising support for every future platform.

## PHP and platform

| Magento | PHP permitted by the tagged root composer.json | PHP in Adobe's published requirements table | Module verification |
| --- | --- | --- | --- |
| 2.4.8-p5 | `~8.2.0 \|\| ~8.3.0 \|\| ~8.4.0` | 8.3, 8.4 | Isolated tests and native declarations on PHP 8.3; installed Magento NOT RUN |
| 2.4.9 | `~8.3.0 \|\| ~8.4.0 \|\| ~8.5.0` | 8.5 | Native declarations on PHP 8.3; PHP 8.5 runtime and installed Magento NOT RUN |

Primary sources:

- [Adobe system requirements](https://experienceleague.adobe.com/en/docs/commerce-operations/installation-guide/system-requirements), updated 2026-09-18.
- [Magento 2.4.8-p5 composer.json](https://github.com/magento/magento2/blob/2.4.8-p5/composer.json).
- [Magento 2.4.9 composer.json](https://github.com/magento/magento2/blob/2.4.9/composer.json).

Adobe's table describes its tested Adobe Commerce stack; tagged Composer source describes the technical constraints of Magento source. A Composer-permitted version does not establish a vendor-supported combination or verification in installed Magento Open Source. Plan staging checks for 2.4.9 on PHP 8.5 according to the current official stack, and for 2.4.8-p5 on PHP 8.3 and 8.4.

## Types of evidence

| Check | What it verifies | What it does not verify |
| --- | --- | --- |
| PHP lint on 8.3 | Syntax of all PHP/PHTML files | DI, Magento interceptors, runtime on 8.4/8.5 |
| Isolated PHPUnit tests | Module policies, validation, and regressions using explicitly identified test doubles | Real Magento, MySQL transactions, EAV lifecycle |
| Native class declarations using actual upstream sources | PHP inheritance, property readonly/type/visibility rules, and method declaration compatibility | Object construction, DI compilation, generated interceptors, framework bootstrap, application behavior |
| Node tests and syntax checks | Client logic with isolated AMD dependencies | A real browser, Magento UI modal, theme behavior, CSP headers |
| Composer validate | Package metadata structure | Dependency installation for the target Magento version |
| XSD validation against two tags | XML compliance with locally obtained official schemas | Runtime layout/UI/DI configuration merges |
| Source-signature comparison | Presence of targeted methods and arguments in official source | End-to-end checkout/GraphQL/REST |

Counts, commands, PASS/FAIL, and NOT RUN results are recorded in `TEST_REPORT.md`. Full compatibility is not declared verified until the installation/compilation/integration matrix has been completed.

## Staging acceptance matrix

For each Magento/PHP combination above, run `setup:upgrade`, `setup:di:compile`, deployment for the store's configured locales, cache cleaning, unit/integration tests, and smoke tests. Every scenario not executed currently has status **NOT RUN**.

| Scenario | Expected result |
| --- | --- |
| Simple / virtual / downloadable | Form, quantity, persistence, and email with correct snapshots |
| Configurable with child / without selection | Correct selected child or explicit parent enquiry; an unrelated child is rejected |
| Bundle / grouped | Parent enquiry identifies that no configuration has been selected |
| Guest / logged-in and user switching | Group rules apply; private fields do not leak through FPC |
| Two store views | Configuration inheritance, website isolation, original-store sender/recipient |
| Product / category / global / date | Correct eligibility, ancestors within the store root, inclusive dates, FPC invalidation |
| Eight name / quantity / ID combinations | Correct fields in the initial notification and admin resend |
| Transport failure | Request remains saved; email status becomes failed; safe resend |
| GET, invalid/forged FormKey | No request, email, or status change |
| Same token, concurrent submission | One row, verified replay, no duplicate notification |
| XSS / header injection / forged SKU | Escaped text, safe headers, server-derived snapshots |
| Shared rate limit across nodes | Enforcement through one database rather than a process-local cache |
| Cart Hidden + Disable Purchasing | Standard UI is hidden; direct add/update/save/place operations are blocked |
| Existing cart and a different Store header | The original persisted store's restriction cannot be bypassed |
| REST / GraphQL guest + customer | Server-side restriction on standard cart mutations and order placement |
| PayPal / multishipping / third-party checkout | Standard paths are blocked; externally initiated payments are checked separately |
| View-only ACL | POST resend/save-status is unavailable without the corresponding resource |
| Luma/Blank, restrictive CSP, built-in FPC / Varnish | External JS initialization, private fields, tags, date TTL, grace policy |
| Legacy import dry-run / execution / repeat | Source rows remain unchanged; no emails; previously imported rows are preserved |

Hyvä/PWA, third-party price renderers, and direct custom order creators are outside this package's current scope. They require an adapter and a separate acceptance run.

## 1.1.3 configuration/button evidence

Actual native email XML conversion and field/template option methods pass on both named Magento tags. Actual inherited grid Export methods pass with explicit UI/DOM dependency doubles. All 74 production declarations were rechecked for each target. These results do not replace full configuration HTML rendering, object creation, Save Config persistence, native ACL dispatch, installed DI compilation, browser or email transport checks. The actual test runtime remains PHP 8.3.6; PHP 8.4/8.5 execution was not performed.
