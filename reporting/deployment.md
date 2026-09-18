# Deployment Notes

Deployment gotchas that aren't obvious from the code. Add to this file as new
ones come up.

---

## PDF export (Browsershot / headless Chrome)

The journey heatmap's "Download PDF" button
(`app/Http/Controllers/Reports/JourneyHeatmapPdfController.php`) renders the
real `/journey-heatmap` page in headless Chrome via
[spatie/browsershot](https://github.com/spatie/browsershot), so the PDF
matches the on-screen Tailwind styling exactly instead of being a separate,
simplified template.

### What the server needs

- **Node.js**, already required for the Vite frontend build, so this doesn't
  add a new category of build dependency — just a heavier one.
- **`npm install` must complete successfully at deploy time.** The `puppeteer`
  package's postinstall step downloads a matching Chromium build (~200MB) into
  the deploy user's cache directory. This needs outbound network access during
  the build/deploy step, not just at runtime.
- **System libraries for headless Chrome**, on a minimal Linux image (most
  Docker base images, e.g. `php:8.4-fpm-alpine` or a slim Debian image) these
  are usually *not* present out of the box and Chrome will fail to launch
  without them. On Debian/Ubuntu:

  ```bash
  apt-get install -y \
    libnss3 libatk1.0-0 libatk-bridge2.0-0 libcups2 libdrm2 libxkbcommon0 \
    libxcomposite1 libxdamage1 libxfixes3 libxrandr2 libgbm1 libasound2 \
    libpangocairo-1.0-0 libpango-1.0-0 libx11-6 libxcb1 libxext6 libxss1
  ```

  If Chrome fails to launch, the error from `storage/logs/laravel.log` (a
  `Symfony\Component\Process\Exception\ProcessFailedException` with Chrome's
  stderr in the "Error Output" section) will usually name the missing
  `.so` file directly — install the package that provides it.

- No extra PHP dependency install step — `composer require spatie/browsershot`
  already pulled the PHP side in; it just shells out to Node at runtime.

### Known local-dev-only limitation

`php artisan serve` (and `composer run dev`, which wraps it) is a
**single-threaded** PHP built-in server — it can only handle one request at a
time. Downloading the PDF makes a request to the PDF route, which then asks
headless Chrome to navigate back to `/journey-heatmap` on that same server —
but the server is still busy handling the PDF request, so the nested
navigation hangs until Chrome's own timeout kills it (`ProtocolError:
Page.navigate timed out`).

This is **not a bug** and does not affect real deployments: production runs
behind PHP-FPM (or Octane), which has multiple worker processes, so the nested
request is picked up by a different worker while the first one waits on
Chrome. It only bites when testing this specific route against `artisan
serve` locally. If you need to reproduce/debug it locally, render outside the
request cycle instead (e.g. a one-off script that calls
`Browsershot::url(...)->pdf()` directly) rather than curling the PDF route
against `artisan serve`.

### Cookie forwarding gotcha

The controller authenticates headless Chrome's request by forwarding the
current user's session cookie. It reads `$_COOKIE` directly rather than
`$request->cookies` — `EncryptCookies` middleware decrypts cookies into
`$request->cookies` before the controller runs, so that bag holds *plaintext*
values. Forwarding those to Chrome means Chrome sends plaintext where Laravel
expects ciphertext, the decrypt fails silently, and the "authenticated" PDF
silently renders the login page instead. `$_COOKIE` still holds the raw,
still-encrypted value a browser actually sent, which is what needs to be
replayed. If this route is ever refactored, keep using `$_COOKIE` here.
