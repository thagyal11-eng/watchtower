# Changelog

All notable changes to `watchtower/watchtower` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-06-18

### Added

- **Schedule monitoring** — tracks every scheduled task, records run history, and
  detects missed runs. Includes a "run now" action to trigger a scheduled command
  on demand from the dashboard.
- **Queue monitoring** — driver-agnostic monitoring of queues, jobs, and failures
  with native single and bulk retry of failed jobs.
- **Exception tracker** — built-in capture of application exceptions with fingerprint
  grouping of similar errors plus resolve and reopen workflows.
- **Production safety** — after-response writes to stay off the request hot path,
  configurable sampling, payload truncation, an optional separate database
  connection, and retention pruning of old records.
- **Alerts** — optional notifications for missed schedules, queue failures, and new
  or reopened exceptions.
- **Dashboard** — a compiled Vue single-page dashboard shipped with the package.

[Unreleased]: https://github.com/watchtower/watchtower/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/watchtower/watchtower/releases/tag/v1.0.0
