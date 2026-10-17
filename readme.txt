=== Million Dollar Script - Analytics ===
Contributors: milliondollarscript
Tags: analytics, google analytics, ga4, event tracking, consent
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add GA4 or trusted custom analytics snippets, Million Dollar Script events, consent gating, and setup verification.

== Description ==

Analytics connects a Million Dollar Script site to Google Analytics 4, trusted custom tracking snippets, or both. It can report relevant grid, checkout, placement, download, outbound-link, form, scroll, and engagement events while respecting the configured administrator exclusions and supported consent signals.

Tracking is disabled until an administrator explicitly configures and enables a provider. Review the extension's privacy and consent guidance before collecting visitor analytics.

== Installation ==

1. Install and activate Million Dollar Script.
2. Install and activate this extension.
3. Open Million Dollar Script > Extensions > Analytics.
4. Configure GA4, custom snippets, consent categories, exclusions, and event settings.
5. Verify the public tag with provider test tools before relying on reports.

== Privacy ==

Analytics providers and custom snippets may process visitor data outside WordPress. Obtain any consent required for the site's audience and jurisdiction, disclose each enabled provider and purpose, and keep custom code limited to trusted analytics integrations.

== Changelog ==

= 1.1.1 =

* Organized provider, consent, setup, event, and verification settings.
* Added supported consent-signal gating and clearer GA4 verification guidance.
* Reduced outbound-link and generic-form event data and improved admin accessibility.
