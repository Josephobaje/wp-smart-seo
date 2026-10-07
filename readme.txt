=== WP Smart SEO ===
Contributors: josephobaje
Tags: seo, open graph, schema, json-ld, xml sitemap
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Lightweight SEO essentials: meta titles and descriptions, Open Graph and Twitter Card tags, JSON-LD schema, an XML sitemap and canonical URLs.

== Description ==

WP Smart SEO adds the SEO features most sites need, and nothing else.

* Per-post SEO title, meta description, canonical URL and noindex switch, with a live search result preview.
* Title templates with %title%, %sitename%, %tagline%, %sep% and %page% variables.
* Open Graph and Twitter Card tags, using the featured image or a default share image.
* JSON-LD structured data: Organization or Person, WebSite with SearchAction, and BlogPosting.
* XML sitemap at /smart-sitemap.xml, listed in robots.txt automatically.
* Canonical URLs with pagination support.
* Noindex for search results and date archives.
* Translation ready.

== Installation ==

1. Upload the `wp-smart-seo` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins screen.
3. Configure it under Settings > Smart SEO.

== Frequently Asked Questions ==

= Where is my sitemap? =

At `https://your-site.com/smart-sitemap.xml`. If it returns a 404, re-save Settings > Permalinks.

= Does it conflict with the core WordPress sitemap? =

By default the core `wp-sitemap.xml` is disabled to avoid duplicates. You can re-enable it in the settings.

== Screenshots ==

1. Settings > Smart SEO.
2. The Smart SEO meta box with a search result preview.

== Changelog ==

= 1.0.0 =
* Initial release.
