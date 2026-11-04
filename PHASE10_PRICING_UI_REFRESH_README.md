PHASE 10 – PRICING UI REFRESH

Included:
- resources/views/pricing/index.blade.php

This is a presentation-only refresh of the existing pricing index page.
It preserves the existing controller variables, pricing calculations, filters,
pagination, product detail links, and recommendation/approval link.
No database migration or route changes are required.

Install:
1. Back up resources/views/pricing/index.blade.php.
2. Extract this ZIP into the Laravel project root and replace the included file.
3. Run:
   php artisan view:clear
   php artisan optimize:clear
4. Refresh /pricing.

The KPI values are calculated from the existing $counts collection; no sample
or fabricated business figures are used.
