# Bonsai Site Snags

Internal Bonsai Digital Collective tool. Not distributed publicly (yet).

A lightweight front-end QA/snagging layer for admins — no settings page dependency, no ACF requirement. Toggle it on, click anywhere on the page to drop a note, tick it off when fixed.

## What it does

Drops a "Snags" toggle button (bottom right) on the front end for logged-in admins only. Turn it on, click anywhere on the page, drop a note and set a priority (Urgent / Normal / Not urgent). Notes appear as pins on the page and as a QA punch-list under **wp-admin → Site Snags**, filterable by Open/Done and by priority. Click a pin to edit the note, change priority, mark done, or delete.

Nobody without the `manage_options` capability (or whatever you filter it to — see below) ever sees the toggle, the pins, or loads any of the JS/CSS. Zero front-end footprint for real visitors.

## Requirements

- WordPress 6.0+
- PHP 7.4+
- No ACF dependency — runs on any WP install with core APIs only

## Installation

1. Upload the `bonsai-site-snags` folder to `/wp-content/plugins/`.
2. Activate via Plugins in wp-admin.
3. That's it — no settings page, no ACF dependency, no config needed.

## Changing who can use it

Two layers of control:

1. **Capability floor** — by default `manage_options` (admins). To open the pool of eligible users up to editors or a custom role, add this to the theme's `functions.php`:

   ```php
   add_filter( 'site_snags_capability', function() {
       return 'edit_pages'; // or a custom capability
   } );
   ```

2. **Per-user allow-list** — under **wp-admin → Site Snags → Settings**, tick exactly which of those eligible users can actually see the toggle and log snags. Leave it unconfigured (never saved) and everyone with the capability gets access, same as before. Once saved, only ticked users see it — including on sites where multiple people have `manage_options` but you only want specific people using it on a given build.

## Email notifications

Under **wp-admin → Site Snags → Settings** there's an "Email notifications" section. When enabled, everyone who can use snagging (the allow-list, or everyone with the capability if it's unconfigured) gets a plain-text email when:

- a snag is added
- a snag's note is edited
- a snag is marked done
- a comment is added to a snag

Each event has its own toggle. Whoever performed the action is never emailed about their own change. The email carries the note, current status, the front-end URL, and a link to the snag's edit screen (comment notifications also include the comment text).

Defaults to on for all four events once the plugin updates — turn the whole thing off, or drop individual events, on the Settings screen. Stored as a single `site_snags_notification_settings` option.

Two filters for customisation:

- `site_snags_notification_recipients` — `( WP_User[] $recipients, int $exclude_user_id )` — change who gets mailed
- `site_snags_notification_email` — `( array $email, string $event, int $post_id, int $actor_id )` — override `subject` / `body` / `headers` (e.g. send HTML, add a Cc, route to a shared inbox)

There are also three action hooks if you want to bolt on Slack/Make.com delivery instead: `site_snags_snag_created`, `site_snags_snag_note_updated`, `site_snags_snag_completed`, each passing `( int $post_id, int $actor_id )`.

## Priority

Every snag carries a priority, set from the three coloured squares bottom-left of the note popover:

- **Urgent** — red
- **Normal** — orange (default)
- **Not urgent** — green

Change it later from the same swatches on an existing pin's popover (saves immediately). The **wp-admin → Site Snags** list shows a Priority column and a "All priorities" filter dropdown above the table, which combines with the Open/Done filter. Priority is also included in the notification emails.

Relabel or recolour the levels with the `site_snags_priorities` filter:

```php
add_filter( 'site_snags_priorities', function ( $priorities ) {
    $priorities['urgent']['label'] = 'Blocker';
    return $priorities;
} );
```

Snags logged before this feature existed read as **Normal**.

## How pin positioning survives page changes

Clicks are stored as a CSS selector path + percentage offset within that element's box — not raw pixel coordinates. On reload, the selector is re-resolved and the pixel position recalculated against the element's current size/position. This means pins hold up across most responsive breakpoints and minor content edits. If the target element is deleted entirely, the pin won't render on the page, but the note is never lost — it's still visible (with a direct link back to the page) in the **wp-admin → Site Snags** list.

## Data storage

Custom post type `site_snag`, plain post meta (no ACF dependency so it works standalone). Fields: URL, page title, CSS selector, offset_x, offset_y, status (open/done), note, author, date — all via `register_post_meta` with sanitisation + capability-gated auth callbacks.

Allow-list is a single option (`site_snags_allowed_users`, an array of user IDs). `false` (its default, never-saved state) means "everyone with the capability" for backwards compatibility on existing installs.

## Admin list page

**wp-admin → Site Snags** is the standard CPT list table with three additions:

- Page column — links straight to the live URL the snag was logged on
- Status column — Open/Done pill
- Open/Done view links above the table (with counts), same pattern as All/Published/Trash
- A "View on page" row action alongside Edit/Trash

The Title column is the note itself (trimmed to the first few words) and links to the snag's edit screen, where a read-only **Note** meta box shows the full text and current priority. From this one list you can jump to either the live page or the snag's own record.

## Updates

Ships with [YahnisElsts/plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker) (installed via Composer, `vendor/` committed) pointed at `github.com/Bonsai-Systems/bonsai_site_snags`. Sites with the plugin installed will see updates in **Plugins** in wp-admin, same as `bonsai-code-injector` and `bonsai-maintenance`.

To ship a new version:

1. Bump the `Version:` header **and** the `SITE_SNAGS_VERSION` constant in `site-snags.php`, and add a `CHANGELOG.md` entry.
2. Commit and push to `main`.
3. Build the release zip (folder named `bonsai-site-snags/` at the root, dev-only files stripped via `.gitattributes` `export-ignore`):

   ```bash
   git archive --format=zip --prefix=bonsai-site-snags/ -o bonsai-site-snags-1.4.0.zip HEAD
   ```

4. Publish a GitHub Release tagged with the version (e.g. `1.4.0`) and attach that zip. Release-assets mode is enabled, so a plain source-archive tag won't be picked up — the zip must be attached.

Sites check for updates every 6 hours (`$checkPeriod` argument to `buildUpdateChecker()`), or immediately if an admin clicks "Check again" on the Plugins screen.

## Ideas for v2 (not built yet)

- Screenshot capture on pin creation (canvas/html2canvas) so notes carry visual context even if the element later changes
- Per-snag assign-to-user (route a snag to one person rather than notifying the whole allow-list) — email notifications themselves shipped in 1.3.0
- Digest mode — one daily roundup email instead of one per event, for busy snagging sessions
- Slack notification via Make.com webhook when a snag is logged or resolved — the `site_snags_snag_*` action hooks added in 1.3.0 make this a small glue script
- CSV/PDF export of the punch-list for client handoff docs
- Auto-push new snags into a client's ClickUp list — task per snag, status sync back to Open/Done — via the ClickUp API or a Make.com scenario hung off the `site_snags_snag_*` action hooks added in 1.3.0
- Per-client-site enable/disable if this ever gets bundled into Drift App Suite rather than shipped as its own plugin
- Optional "resolved but keep visible, faded" mode — already partly there via the done pin styling, could add a toggle to hide fully

## Notes on selling this later

Kept deliberately decoupled from Bonsai's child theme architecture and ACF — runs on any WP install with just core APIs. If this proves useful internally across the wider client base, it's in good shape to become a standalone plugin.dev listing later with minimal rework: mainly adding a settings screen for the capability filter and maybe white-labelling the toggle button.
