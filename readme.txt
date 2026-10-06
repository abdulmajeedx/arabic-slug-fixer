=== Arabic Slug Fixer ===
Contributors: abdulmajeedx
Tags: arabic, slug, permalink, seo, transliteration
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn long percent-encoded Arabic URLs into short, readable Latin slugs, with 301 redirects for old links.

== Description ==

Arabic slugs become unreadable URLs like `/%d8%a7%d9%84%d8%b1%d9%8a%d8%a7%d8%b6/` when shared. This plugin:

* Transliterates Arabic (and Persian) titles into Latin slugs for new posts, pages, products and terms.
* Removes harakat and optional Arabic stop words, and limits slug length.
* Converts existing Arabic slugs in batches, with a preview first.
* Keeps old links working with 301 redirects (posts via WordPress core, pages via the plugin).

Existing slugs are never changed unless you run the bulk converter.

== Installation ==

1. Upload the folder to `/wp-content/plugins/` and activate.
2. Go to Tools → Arabic Slugs.

== Changelog ==

= 1.0.0 =
* Initial release.
