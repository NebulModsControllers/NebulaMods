# Copilot / Agent Instructions for wc-image-radio-groups ✅

## Quick summary
- This is a small WordPress plugin that adds “image radio groups” for WooCommerce products.
- Major patterns: product <-> taxonomy assignment, group options saved as term meta, product-specific overrides saved as post meta, frontend uses radio inputs + JS price-summary, server-side price injection when adding to cart.

## Key files (start here) 🔧
- `wc-image-radio-groups.php` — single-file plugin, contains almost all logic: taxonomy registration, admin UI, frontend rendering, cart & price hooks, Astra compatibility fixes.
- `admin-media.js` — WP Media-based uploader used in the product overrides UI (root-level file; enqueued from plugin PHP).
- `assets/js/frontend.js` — frontend interactions: price calculation, fragment refresh helper, add-to-cart hidden field insertion.
- `assets/js/script.js` and `assets/css/style.css` — currently empty placeholders.
- `assets/css/frontend.css` — visual styles for options and stock states.

## Architecture & Data flow (concise) 💡
1. Admin: create taxonomy `iro_group_assignment` (term = group) and add options per group → saved to term meta `_iro_options`.
2. Product: assign one or more groups to the product (product term relationship). Product overrides are saved in post meta `_wc_iro_option_overrides` using a UID pattern: `<term_id>_<option_index>`.
3. Frontend: `wc_iro_display_image_options()` renders groups & options as labeled `<label>` elements with radio inputs named `iro_option_<term_id>`.
   - Radio value mapping: disabled options use `-1`. Active options use an `activeIndex` that counts only non-disabled options.
   - The plugin performs mapping back to the original options array server-side by iterating and counting active options (see `wc_iro_add_cart_item_data`).
4. Add to cart: frontend JS appends a hidden field `wc_iro_price_adjustment` (note: code inconsistency — server code checks for `wc_iro_calculated_price` in a separate filter). The real price change persists by adding `iro_options` to the cart item and adjusting price in `wc_iro_add_cart_item_price`.

## Important, project-specific conventions & gotchas ⚠️
- Meta keys: term meta `_iro_options` (group options), post meta `_wc_iro_option_overrides` (per-product overrides).
- Option UID format: `<term_id>_<index>` (useful for cross-referencing overrides in code).
- Value mapping: frontend uses an `activeIndex` (0..N) that does NOT equal the option’s original index when disabled options exist — the PHP correctly maps activeIndex -> actual index by iterating and skipping disabled entries.
- Currency & formatting: plugin assumes Euro formatting (comma as decimal separator in JS formatter). Keep this in mind when changing formatting.
<!-- Astra/header-specific integration notes removed from this guide -->
- Security: save handlers (term and product meta) do not use nonces or capability checks in code as-is — treat as a discovered fact and be deliberate if you modify it.

## How to validate & debug (developer workflow) 🧪
1. Reproduce locally with a WordPress + WooCommerce (and Astra if you want to test theme-specific fixes).
2. Create an `Afbeeldingsgroep` (Products > Afbeeldingsgroepen), add multiple options (label, price, proxy product ID, image, disabled flag).
3. Assign group(s) to a product. Optionally add per-product overrides in the product edit screen ("Optie Overrides").
4. On the product page:
   - Check that radio inputs are present: `name="iro_option_<term_id>"`.
   - Confirm `_wc-iro-summary` updates and currency formatting.
   - Inspect the POST payload on add-to-cart: ensure `iro_option_<term_id>` values and the hidden price field are sent.
   - Verify mini-cart reflects adjusted prices (watch `wc_fragment_refresh` activity).
5. Debug tips:
   - Enable `WP_DEBUG` and check `wp-content/debug.log`.
   - Use browser console & network tab to observe AJAX add-to-cart and fragment refresh triggers.
   - Check the mapping logic in `wc_iro_add_cart_item_data` if selected option appears wrong.

## Suggested immediate fixes & attention items (for human reviewers) 🔍
- There is a naming mismatch: frontend appends `wc_iro_price_adjustment` but server-side `wc_iro_add_custom_price_data` checks `wc_iro_calculated_price`. Confirm and unify the field name to ensure intended behavior across add-to-cart flows.
- `assets/js/script.js` and `assets/css/style.css` are empty placeholders — consider removing or populating them.
- Save handlers don't use nonces/capability checks — if you add write operations or change UI, add appropriate permission checks.

## Where to make common changes (code pointers) 🧭
- Change rendering/markup and option logic: `wc-image-radio-groups.php` — `wc_iro_display_image_options()` and the surrounding loops.
- Media uploader behavior: `admin-media.js` (root) is used for per-product overrides selection UI.
- Price logic: `wc_iro_add_cart_item_data`, `wc_iro_add_cart_item_price`, and `wc_iro_final_cart_item_price_fix` for cart/mini-cart rendering.
- CSS tweaks: `assets/css/frontend.css` for visual adjustments and CLS (min-height) fixes.

---
If anything is unclear or you want me to expand a specific section (e.g., add example unit/behavior tests or a checklist to enforce nonces and capability checks), tell me which part and I’ll iterate. 💬