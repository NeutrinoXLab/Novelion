# Storefront indexing

Canonical origin: `https://novelions.ro`. `/sitemap.xml` and the canonical tags use the same route-based URL builder. Campaign parameters are omitted; listing pagination keeps `page` when greater than one.

The existing publication flag is `is_active` for products and categories. There are no separate draft/hidden/published-at/soft-delete fields. Inactive detail routes return 404. Active out-of-stock products remain eligible. Category inactivity does not implicitly deactivate its products, matching the existing catalog policy.

The sitemap includes an explicit public route allowlist and active catalog entries. It streams selected columns in chunks, has no application cache, and automatically switches to an index with parts of at most 10,000 URLs. There is no fabricated lastmod, priority or changefreq. Technical/private HTML responses and the JSON search endpoint get `X-Robots-Tag: noindex, follow`; robots.txt permits crawling so search engines can see that directive.

Existing product/category SEO titles and descriptions are rendered escaped. Empty descriptions fall back to existing short product/category descriptions, with markup removed. Existing page titles remain the fallback. No commercial content is edited.

Deployment: fast-forward the production repository, back up and copy only `public/robots.txt` to the separate public document root, then clear compiled views. No migration, build, .env, .htaccess or feature-flag change is needed. Preserve a private code archive and the old robots.txt for rollback. Verify live XML, all included URLs, representative canonical tags, private noindex, logs and F3 OFF.

Submit `https://novelions.ro/sitemap.xml` manually to Search Console. A sitemap supports discovery; Google decides crawling and indexing. Search Console access and submission are not automated by this implementation.
