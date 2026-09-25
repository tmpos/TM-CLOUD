# Contact and location map deployment - 2026-09-25

Published to the VPS service `tmpos-system-api-73trsf` as
`tmpos-system-api-73trsf:contact-map-20260925`, based on the running image
`tmpos-system-api-73trsf:cart-fix-20260924`.

Includes a shared Contact link on the twelve customer-facing store/portal
views, the WhatsApp SVG, and a validated Google Maps embed setting stored in
existing page_content JSON. Empty map removes it; omitted map preserves it.
The system web build includes cover uploads and map configuration/preview.
No actual store location was configured as part of deployment.

The preceding source synchronization commit records dependencies already
running on the VPS (website settings/pages/orders, SPA and CRM). Existing
GitHub realtime-license changes are preserved in source. This incremental
VPS image retains its original LicenseService and App wiring; those files
were not part of this release payload. The deployed category cart script
was preserved instead of replacing it with the local variant.

Validation: isolated Linux website-settings smoke test (map persistence,
partial update, clearing, isolation, safe rendering and contact forms), PHP
syntax, successful web build and public HTTP checks of home, contact,
categories, cart and customer login for barbaroja. Swarm update completed.

Rollback:
`docker service update --image tmpos-system-api-73trsf:cart-fix-20260924 tmpos-system-api-73trsf`

Release files and previous application snapshot are under
`/opt/tmpos-contact-map-20260925` on the VPS. Production storage was not
modified by the release tests. Map configuration is optional and no new
schema migration is required.
