# Changelog

## Unreleased

- Added opt-in uninstall cleanup through the core extension policy for extension-owned analytics and consent settings and transients.

## 1.1.1 - 2026-07-13
- Organized provider and consent configuration under Settings and moved setup, event, and verification guidance to a directly addressable Setup and events tab.
- Prevented duplicate GA4 page views by adding Million Dollar Script dimensions to the single Google tag configuration event.
- Added basic gating for compatible WordPress Consent API statistics and marketing signals, with a separate consent category for trusted custom snippets.
- Replaced the obsolete GA4 IP-anonymization toggle with accurate GA4 privacy guidance while preserving the legacy option value.
- Replaced the misleading admin test-event control with a bounded Google tag availability check and accurate Realtime/DebugView guidance.
- Moved tracking, event, dashboard, and admin behavior into versioned assets and added explicit light/dark semantic status styles.
- Removed query strings and fragments from outbound-link event labels, reduced generic form event data, and throttled scroll processing with animation frames.
- Added a WordPress workflow fixture covering settings, consent gating, tag initialization, custom snippet placement, admin UI, and the fixed-host availability request.

## 1.1.0 - 2026-06-05
- Renamed the extension to Million Dollar Script - Analytics while keeping the existing plugin slug, text domain, and option names for backward compatibility.
- Added custom tracking snippet settings for head, top-of-body, and footer output locations.
- Allowed Google Analytics 4 and trusted custom tracking snippets to run together.
- Restricted raw snippet editing to administrators with unfiltered HTML permission.
- Updated Analytics admin and dashboard copy to reflect Google and custom tracking states.

## 1.0.1 - 2026-04-25
- Added MDS 3.0 admin menu compatibility.
- Validates GA4 Measurement IDs before enabling tracking.
- Hardened settings save and AJAX test endpoints with nonce/capability checks.
- Escaped tracking output and added Million Dollar Script grid, checkout, and placement event tracking.

## 0.1.0 - 2025-01-01
- Initial development version.
