# literec-admin-php

The LiteRec Admin PHP Server. A parks and recreation management solution.

## Real TLS on local dev (Cloudflare DNS-01)

The shipped FrankenPHP image is rebuilt with the
[`caddy-dns/cloudflare`](https://github.com/caddy-dns/cloudflare)
provider so Caddy can solve the Let's Encrypt **DNS-01 challenge**
against a Cloudflare-managed zone. This gives every developer a real,
publicly-signed certificate on a chosen internal subdomain — without
opening port 80 or 443 to the internet.

Contributors without a Cloudflare token are unaffected: the build still
succeeds and Caddy falls back to its internal CA.

### Prerequisites

- A DNS zone you control inside a Cloudflare account.
- A **scoped** Cloudflare API token with **`Zone — DNS — Edit`** on the
  dev zone **only** (do not use a Global API Key, do not grant
  account-wide permissions).
- A hostname under that zone you want this container to answer on
  (e.g. `litrec.dev.example.com`).
- A DNS record in the Cloudflare zone for that hostname pointing to
  the machine running the container — an `A` / `AAAA` record to the
  host's public IPv4 / IPv6, or a `CNAME` to a host that already
  resolves to it. Without this record the browser cannot reach the
  container even after the certificate is issued.

### Configuration

1. Copy the token into `.env.local` (already in `.gitignore`, never
   commit it):

   ```bash
   CLOUDFLARE_API_TOKEN=cf_pat_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
   SERVER_NAME=litrec.dev.example.com
   CADDY_SERVER_EXTRA_DIRECTIVES="tls { dns cloudflare {env.CLOUDFLARE_API_TOKEN} }"
   ```

2. Rebuild the image so the xcaddy builder runs and the
   `caddy-dns/cloudflare` provider lands in the binary:

   ```bash
   docker compose build php
   ```

3. Start the stack and request the host:

   ```bash
   docker compose up -d
   curl -v https://litrec.dev.example.com/health
   ```

4. Verify the certificate issuer in your browser address bar (or via
   `openssl s_client -connect litrec.dev.example.com:443 -servername litrec.dev.example.com | grep issuer`).
   The issuer should be **Let's Encrypt**, not Caddy Local Authority.

### Verifying the module is compiled in

```bash
docker compose run --rm php frankenphp list-modules | grep dns.providers.cloudflare
```

This command must print `dns.providers.cloudflare`. If it does not, the
image build has regressed — the Dockerfile contains a build-time guard
that fails the image build in that case, so this should never reach
runtime.
