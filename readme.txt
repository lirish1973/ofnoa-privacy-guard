=== Ofnoa Privacy Guard ===
Contributors: ofnoacomps
Tags: privacy, consent, israel, amendment 13, cookies
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.5
License: GPLv2 or later

Israeli Privacy Protection Law (Amendment 13) compliance: scanner, consent checkboxes on every form, consent log, data-subject requests, document generator, cookie banner.

== Description ==

* Compliance scanner with 40+ checks and a 0-100 score, weekly email report.
* Privacy policy analysis (sections, freshness, third parties mentioned, footer link).
* Consent checkbox + collection notice injected and enforced on WooCommerce (classic + blocks), WP registration, comments, CF7, Elementor Pro, WPForms, Gravity Forms, Fluent Forms, plus a universal JS layer.
* Consent log as evidence (CSV export).
* Rights requests (access / correction / deletion / unsubscribe) with email verification and a 30-day deadline.
* Generates privacy policy (HE/EN), cookie policy, requests page, data map, database definitions document, security procedure, incident plan, vendor appendix, staff NDA and response letters.
* Cookie banner with Google Consent Mode v2.
* Security audit log.
* Auto-updates from GitHub Releases.

Not legal advice. Review every generated document before publishing.

== Changelog ==

= 1.0.5 =
* Email report once a month (1st of the month) instead of alerts on changes; weekly scan runs silently. "Send report now" button.
* Generated pages: the "review before publishing" note is hidden from visitors; links and tables readable on any theme.

= 1.0.4 =
* One click from the report: create + publish the privacy requests page, link it from the end of the privacy policy and from the footer bar.

= 1.0.3 =
* Automatic footer bar with links to every published page the plugin generated (policy HE/EN, cookie policy, privacy requests) + cookie settings. On by default.

= 1.0.2 =
* Consent box readable on dark themes: links no longer inherit white theme colors, comfortable line height.

= 1.0.1 =
* Universal form layer now covers JS-submitted forms with no method attribute (e.g. WhatsApp lead forms).
* Footer-link check counts only real <a href> links.
* Broader Hebrew terms for policy sections (retention etc.).
* Report lists custom HTML forms found on the home page.
* Cookie settings button moved to bottom-left (away from chat widgets).

= 1.0.0 =
* First release.
