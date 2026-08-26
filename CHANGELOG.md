# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

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
