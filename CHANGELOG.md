# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

### Fixed
- [includes/class-site-snags-cpt.php] `site_snag` CPT's `capabilities` array only mapped singular meta caps (`edit_post`, `delete_post`, etc.), missing the plural primitive caps (`delete_posts`, `delete_others_posts`, `delete_private_posts`, `delete_published_posts`, `edit_private_posts`, `edit_published_posts`). Anything auditing all registered post types' capabilities without a specific post ID (e.g. User Role Editor Pro) triggered WordPress's `map_meta_cap()` "called incorrectly" notice on every check. With `WP_DEBUG_DISPLAY` on, those notices were echoed raw into the page mid-render — including inside the `<ul id="adminmenu">` markup — visually truncating the wp-admin sidebar (Settings, ACF, WP Puller, UpdraftPlus, etc. all disappearing) and breaking UpdraftPlus's own localized admin JS in the process
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
