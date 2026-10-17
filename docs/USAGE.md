# Analytics

Analytics adds Google Analytics 4 tracking, trusted custom tracking snippets, Million Dollar Script event tracking, and an admin dashboard widget.

## Practical Examples

- Measure which grid and sponsor pages lead visitors into the placement-ordering flow with Google Analytics 4 events.
- Add a privacy-friendly analytics provider or verification snippet at its required document location without editing the WordPress theme.
- Exclude administrators and respect compatible consent signals so routine site management does not distort visitor reports.

### Example workflow: measure a sponsor campaign funnel

1. Open **Million Dollar Script -> Analytics**, enable Google Analytics 4, and save the site's `G-` Measurement ID.
2. Enable the relevant Million Dollar Script events and exclude administrator traffic.
3. Visit the public campaign page as a signed-out visitor, interact with the grid, and begin an order.
4. Confirm the events in the analytics provider, then use them to compare campaign-page visits with ordering activity. Consent settings still determine whether tracking is emitted for each visitor.

## Setup

1. Activate Million Dollar Script and Analytics.
2. Open **Million Dollar Script -> Analytics**.
3. To use Google Analytics, create or open a GA4 property and copy the Measurement ID that starts with `G-`.
4. Paste the Measurement ID, enable Google Analytics 4, and save settings.
5. Review the privacy options:
   - Exclude administrators from tracking.
   - Respect compatible WordPress Consent API signals.
   - Disable Google advertising features and personalization.
6. If using another trusted analytics or verification provider, enable custom tracking snippets, choose its consent category, and paste code into the correct location.

Custom snippet fields require an administrator account that can save unfiltered HTML. This helps prevent lower-privileged users from adding scripts.

Google Analytics 4 discards IP addresses before logging them, so the Universal Analytics `anonymize_ip` setting is not required. Existing development installs keep their legacy option value, but Analytics no longer presents or sends that obsolete setting.

## Admin Workspace

Use **Settings** for Google Analytics, consent, custom snippets, and event options. Use **Setup and events** for placement guidance, tracked event names, troubleshooting, and verification steps. Both tabs are directly addressable so administrators can return to the same section after saving or sharing a support link.

## Consent Behavior

**Respect compatible WordPress Consent API signals** is enabled by default. Its behavior is intentionally conservative and backward compatible:

- When a compatible consent provider exposes WordPress consent signals, Google Analytics waits for `statistics` consent.
- Custom snippets assigned to **Statistics** or **Marketing** wait for the matching consent category.
- **Always load** bypasses consent gating. Reserve it for essential code or the consent manager itself.
- When no compatible consent provider is active, Analytics preserves the site's existing behavior and does not block configured tracking.

Consent is evaluated on the server for each page request. After a visitor changes consent, the new state applies on the next page load unless their consent manager performs its own dynamic tag loading.

## Custom Tracking Locations

Analytics supports three custom snippet locations:

- **Head code** outputs in `wp_head`.
- **Top of body code** outputs in `wp_body_open` when the active theme supports it.
- **Footer code** outputs in `wp_footer`.

Only paste code from providers you trust. Make sure the site's privacy and consent notices cover every provider you enable.

## Events

When Google Analytics is active, the extension adds enhanced event tracking for:

- One page view per page load with page type and user type data.
- File downloads for common document/archive formats.
- External link clicks.
- Form submissions.
- Scroll-depth milestones.
- Engagement time.

Use **Check tag availability** to confirm that the WordPress server can reach the configured Google tag library. This check does not claim to send an analytics event. Confirm real page views and events in Google Analytics Realtime or DebugView while visiting the frontend as a trackable visitor.

Outbound-link events include only the destination origin and path. Query strings and fragments are removed before the event is queued. Generic form events do not include field values, form IDs, or CSS classes.

## Legal And Privacy

Analytics can contribute an **Analytics and Visitor Tracking Privacy Notice** page through the Million Dollar Script extension setup flow. Review it before publishing.

Google Analytics and any custom snippets are third-party processors controlled by their own account settings, terms, retention controls, and privacy policies. Site owners are responsible for consent, disclosure, regional privacy requirements, and honoring visitor choices.

## Troubleshooting

If no tracking appears:

- Confirm the Measurement ID starts with `G-`.
- Confirm tracking is enabled.
- View the public site while logged out or with administrator exclusion disabled.
- Check whether a consent plugin, cache plugin, content security policy, ad blocker, or browser privacy setting blocks the request.
- Confirm custom code is pasted into the correct location.

## Developer Hooks

Use these filters to integrate another consent or snippet-management layer:

```php
add_filter('mds_analytics_should_track_current_user', function ($should_track, $consent_category) {
    return $should_track;
}, 10, 2);

add_filter('mds_analytics_should_output_generic_tracking', function ($should_output, $location) {
    return $should_output;
}, 10, 2);

add_filter('mds_analytics_generic_tracking_code', function ($code, $location) {
    return $code;
}, 10, 2);
```

## Local Workflow Verification

After syncing the extension into the local WordPress stack, run:

```bash
./scripts/wp eval-file wp-content/plugins/mds-google-analytics/tests/mds3-fixture.php
```

The fixture intercepts the Google tag availability request and does not send analytics traffic.
