# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

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
