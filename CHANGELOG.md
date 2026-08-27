# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

## [1.4.0] - 2026-08-27

### Added
- **Snag priority (Urgent / Normal / Not urgent).** Three coloured squares (red / orange / green) bottom-left of the front-end note popover — set on creation and changeable later from an existing pin's popover (persists immediately via the new `site_snags_update_priority` AJAX action). Stored as `_snag_priority` post meta, default `normal`
  - [site-snags.php] `site_snags_get_priorities()` (slug → label + colour, filterable via `site_snags_priorities`), `site_snags_get_priority()` and `site_snags_get_priority_label()` helpers
  - [includes/class-site-snags-admin-list.php] Priority column and an "All priorities" filter dropdown on the wp-admin list; `filter_by_status()` replaced by `filter_admin_query()`, which combines the Open/Done and priority meta clauses so both filters work together. Legacy snags with no `_snag_priority` row match the "Normal" filter via `NOT EXISTS`. Shared stylesheet now enqueued on the list screen (also fixes the previously-unstyled status pill)
  - [includes/class-site-snags-cpt.php] Read-only Note meta box now shows the current priority; `_snag_priority` registered with a `sanitize_priority` callback
  - [includes/class-site-snags-notifications.php] Notification emails now include a `Priority:` line
- [includes/class-site-snags-notifications.php] Email notification when a comment is added to a snag. New `commented` event: `on_comment()` listens on `wp_insert_comment`, fires only for approved, real comments on `site_snag` posts, excludes the commenter, and includes the comment text in the email body. `dispatch()` gained an optional `$extra_lines` parameter for per-event body content
- [includes/class-site-snags-settings.php, site-snags.php] "A comment is added to a snag" toggle on the notification settings screen; `commented => 1` added to the notification defaults, so it is on once the plugin updates
- [includes/class-site-snags-cpt.php] Read-only **Note** meta box on the snag edit screen, showing the full `_snag_note_raw` text (the post title only holds the first few words)

### Changed
- [includes/class-site-snags-cpt.php] Removed `editor` from the `site_snag` CPT `supports` — the snag note is stored in post meta, not `post_content`, so the WYSIWYG box was always empty

## [1.3.0] - 2026-08-27

### Added
- [includes/class-site-snags-notifications.php] Email notifications for snag activity. New `Site_Snags_Notifications` class listens for three action hooks and emails the allow-list (or everyone with the capability, if the allow-list is unconfigured), always excluding whoever performed the action. Covers snag added, snag note edited, and snag marked done
- [includes/class-site-snags-ajax.php] Fires `site_snags_snag_created`, `site_snags_snag_note_updated`, and `site_snags_snag_completed` (`$post_id`, `$actor_id`) from the create / update_note / update_status handlers so notifications (and any third-party code) can hook snag lifecycle events
- [includes/class-site-snags-settings.php] Notification controls on the **Site Snags → Settings** screen: master on/off plus per-event toggles (added / note edited / marked done), stored as a single `site_snags_notification_settings` option. Defaults to fully on, so the feature is zero-config once the plugin updates
- [site-snags.php] `site_snags_get_notification_settings()` (defaults merge) and `site_snags_get_notification_recipients( $exclude_user_id )` helpers; recipient list filterable via `site_snags_notification_recipients`, and the outgoing email filterable via `site_snags_notification_email`

### Fixed
- [includes/class-site-snags-cpt.php] **Root cause of the wp-admin menu corruption and `map_meta_cap() was called incorrectly` notices.** The `site_snag` CPT mapped the singular meta-cap keys (`edit_post`, `read_post`, `delete_post`) directly to `SITE_SNAGS_CAP` (`manage_options` by default). WordPress core (`$post_type_meta_caps` in `wp-includes/capabilities.php`) registers any such override as a **global alias**: once `manage_options` is mapped as a custom post type's `delete_post` equivalent, *every unrelated* `current_user_can( 'manage_options' )` check anywhere on the site — Settings menu, UpdraftPlus, ACF, Yoast, anything — gets silently rerouted through `map_meta_cap( 'delete_post', $user_id )` with no post object, triggering the notice site-wide and, with debug output visible, corrupting the rendered `<ul id="adminmenu">` enough to hide unrelated menu items (Settings, ACF, WP Puller, UpdraftPlus). Reproduced with this plugin as the *only* active plugin, confirming it wasn't a third-party conflict. Fixed by no longer overriding the singular meta-cap keys — they're left at their WordPress defaults, so no alias is registered — while the plural/primitive keys (`edit_posts`, `delete_posts`, `edit_others_posts`, etc.) stay gated on `SITE_SNAGS_CAP` exactly as before, which is what actually controls per-post edit/read/delete access
- [includes/class-site-snags-cpt.php] Also completed the plural primitive caps (`delete_posts`, `delete_others_posts`, `delete_private_posts`, `delete_published_posts`, `edit_private_posts`, `edit_published_posts`) that were previously missing — correct regardless of the above
- [site-snags.php] `Site_Snags_Settings` was `require_once`'d but never instantiated in `Site_Snags::__construct()`, so its `admin_menu`/`admin_post` hooks never registered and the Site Snags → Settings submenu never appeared

## [1.2.0] - 2026-08-26

### Changed
- [site-snags.php] Renamed plugin from "Site Snags" to "Bonsai Site Snags"; rebranded `Plugin URI`/`Author`/`Author URI` from Gak Design to The Bonsai Digital Collective
- [readme.txt → README.md] Converted to Markdown README matching the `bonsai-code-injector` project structure

### Added
- [composer.json, vendor/] Wired up `YahnisElsts/plugin-update-checker` (^5.6) so the plugin can self-update from GitHub releases via the wp-admin Plugins screen, matching `bonsai-code-injector` and `bonsai-maintenance`. Points at `github.com/Bonsai-Systems/bonsai_site_snags`, `main` branch, release-assets mode enabled
- [CLAUDE.md] Project-level Claude Code context, linking to the global Bonsai instructions

## [1.1.0] - previous

### Added
- Initial internal build: `site_snag` CPT, front-end click-to-pin toggle, AJAX create/update/delete/fetch handlers, admin list table with Open/Done filtering, per-user allow-list settings screen
