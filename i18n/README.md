# Interface language

The module's source interface messages are in English. Release 1.1.1 removes the
bundled Russian translation dictionary so that it cannot replace these messages
with Russian text. Magento's standard translation functions remain in use.

Store labels, saved requests, catalog data, customized email templates, and
translations supplied by the theme or another language pack are store-owned
content. This release does not rewrite them.

When upgrading an existing installation, remove the previous module's
`i18n/ru_RU.csv` if it is still present in the deployment. An overlay copy alone
does not remove obsolete files. Clear Magento's translation and configuration
caches and redeploy static assets for the store's configured locales using the
normal deployment process. Existing cached or externally translated phrases can
otherwise remain in Russian.
