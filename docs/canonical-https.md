# Canonical HTTPS storefront

The production storefront is `https://novelions.ro`. The rules in
`public/.htaccess` redirect HTTP and the www hostname using 308, preserving
the path, query string, and request method. They do not redirect local hosts.
Existing Laravel routing and trailing-slash rules remain in place.

## Hosting prerequisite

The rules use the server's `HTTPS` variable and assume TLS terminates at
LiteSpeed, with `.htaccess` rewrite rules enabled. If TLS terminates at an
upstream reverse proxy and LiteSpeed sees HTTP, do not deploy these rules
unchanged: enforce the canonical redirect at that trusted edge instead.
Do not bypass the redirect based on a client-supplied forwarded header.

The rules were checked on an isolated Apache HTTP/TLS server. This does not
replace verification on LiteSpeed after a separately authorized deployment.

## Production environment

Set these values through the normal production configuration process:

```dotenv
APP_ENV=production
APP_URL=https://novelions.ro
SESSION_SECURE_COOKIE=true
```

Rebuild Laravel's configuration cache during that deployment. An explicit
`SESSION_SECURE_COOKIE=false` overrides the production default and must not
remain in production. Session HttpOnly and SameSite=Lax are unchanged.
XSRF-TOKEN remains readable by JavaScript and shares the Secure setting.
Local HTTP development uses APP_ENV=local and defaults to non-Secure cookies.

## Verification after deployment

Check both HTTP hostnames and HTTPS www: each must redirect to HTTPS non-www
with the original path and query. HTTPS non-www must not loop. Check a static
asset as well as login and a product page. Inspect session and XSRF cookies
for Secure. Verify Stripe success/cancel URL generation and the configured
webhook endpoint without creating a real payment. Stripe should call the
canonical HTTPS webhook URL directly rather than rely on redirects.

The existing X-Forwarded-Host handling is intentionally unchanged and remains
a separate security issue; it can still influence generated URLs when supplied.
