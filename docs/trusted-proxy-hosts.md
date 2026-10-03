# Trusted proxy and host handling

Laravel accepts only `novelions.ro` and `www.novelions.ro` outside local and
test environments. The standard TrustHosts middleware exempts local development
and tests; production behavior is explicitly exercised by the security tests.
The server's canonical HTTPS redirect continues to handle www before Laravel.

The provider no longer reads forwarded headers or forces a root URL from them.
In production, generated URLs use HTTPS independently of forwarded protocol.
`APP_URL=https://novelions.ro` remains necessary for console/email URLs and
storage URLs configured through APP_URL.

Only X-Forwarded-Proto is enabled in Laravel's trusted proxy header mask.
X-Forwarded-Host, RFC Forwarded (including host=), X-Forwarded-Port and
X-Forwarded-Prefix are not trusted, even from an allowed proxy. No proxy IPs
are trusted by default. If a verified reverse proxy terminates TLS, configure
its exact IPs/CIDRs using a comma-separated TRUSTED_PROXIES environment value.
Never use *, **, REMOTE_ADDR or catch-all CIDRs. Do not guess proxy addresses.

## Hosting verification before deployment

- Verify whether LiteSpeed terminates TLS directly or receives HTTP from a proxy.
- If there is a proxy, obtain its actual source IPs/CIDRs and ensure it replaces
  client X-Forwarded-Proto with its own protocol value.
- Confirm the origin cannot be reached through an unintended trusted path.
- Confirm Host reaching Laravel remains novelions.ro or www.novelions.ro.
- Rebuild cached Laravel configuration after authorized environment changes.
- The previous .htaccess HTTPS redirect assumes TLS terminates at LiteSpeed;
  use the trusted edge redirect instead if that prerequisite is not met.

After a separately authorized deployment, repeat the harmless forwarded-host
GET/HEAD checks, inspect login redirects and cookies, and verify Stripe callback
URL generation. Configure Stripe's webhook directly to the canonical HTTPS URL.
No live deployment or hosting changes are part of this repository intervention.

Unknown Host requests answered by LiteSpeed's default virtual host never reach
Laravel and remain a separate server configuration issue. TrustHosts cannot
disable that server's directory listing or reject those responses.
