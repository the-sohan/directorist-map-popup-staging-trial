# Directorist Map Popups on Demand: staging trial

[Download the installable plugin ZIP](https://github.com/the-sohan/directorist-map-popup-staging-trial/releases/download/v0.1.0/directorist-map-on-demand-0.1.0-staging-trial.zip)

Version 0.1.0 is a support trial for Directorist 8.10.5, not an official Directorist release. It has been tested locally. Installation and interactive Google Maps verification on the target staging site remain outstanding.

## What changes

- Retains the existing listing query, marker IDs/order, icons and clustering.
- Builds each full popup only when a visitor opens its marker.
- Uses an existing `directorist_preview` image, with medium/thumbnail fallback, instead of requesting the large image for every popup.
- Keeps native card fields and provides responsive sizing, scrolling and a retry/listing-link fallback.
- Does not edit Directorist Core files, listing data, Elementor content or plugin settings.

The plugin is restricted to `staging.bbqtravelclub.com`, localhost and `.local` hosts, on the `global-bbq-directory` page (plus a local QA page). It deliberately does not run on the live hostname.

## Install on staging only

1. Back up staging.
2. Download the ZIP using the link above. Use the installable plugin ZIP, rather than GitHub's automatically generated source archive.
3. In staging WordPress, go to Plugins > Add Plugin > Upload Plugin, upload the ZIP, and activate **Directorist Map Popups on Demand (Staging)**.
4. Purge only the staging directory URL from the staging page cache.
5. Check marker counts, directory types, filters/sorting, map popups, favorites/login, directions, grid/list/map switching and mobile layout. Compare cache-miss and cache-hit results.

The target staging plugin installer returned HTTP 403 during the installation attempt. Normal staging upload access must be restored, or the site owner must install the ZIP through their existing staging deployment process, before target-site testing can proceed.

## Local verification

With the same 67 local test listings, Google map markup fell from 341,188 to 54,090 bytes. Median renderer time fell from 73.153 to 2.994 ms over five measured runs after warm-up. These measure the map renderer only and do not predict target-site page load time.

Marker identities/order, public/protected popup endpoint behavior, 640x360 preview selection, and real OpenStreetMap popup/view-switch/mobile behavior were checked locally. The target site's interactive Google map still needs staging verification.

## Rollback

Deactivate this plugin and purge only the staging directory URL from cache. Native templates resume. No data migration is required.

This trial addresses map popup rendering and image loading. It does not change Social Login, AI Search, Universal Search or LiteSpeed settings.

## Package integrity

See [SHA256SUMS.txt](SHA256SUMS.txt). The release asset is the exact locally verified trial ZIP.
