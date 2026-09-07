# DaData INN Suggestions

WordPress plugin for DaData company suggestions by INN or company name.

The plugin adds autocomplete to a configured form field, sends requests through a
WordPress REST endpoint, and keeps the DaData token on the server. It can also
forward saved UTM parameters and `pageref` to external links.

## Requirements

- WordPress 5.8 or newer
- PHP 7.4 or newer
- DaData account with a Suggestions API token

Tested on WordPress Multisite 6.9 with PHP 8.1.

## Installation

### WordPress admin

1. Create a ZIP that contains the plugin directory:
   `dadata-suggestions/dadata-suggestions.php`, `assets/`, and `uninstall.php`.
2. In WordPress admin, open Plugins, Add New, Upload Plugin.
3. Upload the ZIP and activate it.

On Multisite, network activation is supported. Settings are stored per site, so
each site can enable DaData only where it is needed.

### Manual

Copy this repository to:

```text
wp-content/plugins/dadata-suggestions/
```

Then activate **DaData INN Suggestions** in WordPress admin.

## Configuration

Open Settings, DaData INN.

Required settings:

- Enable **DaData INN suggestions**.
- Paste the DaData API token.
- Set the INN/company field selector. The default is `#company`.

Optional selectors fill extra fields after a company is selected:

- company name
- KPP
- OGRN
- address

Selectors can be any valid CSS selector, including comma-separated selectors.
If a selector does not match anything on the page, the script skips it.

## How It Works

When DaData is disabled, the DaData script is not loaded and no DaData requests
are sent.

When DaData is enabled, the frontend script waits until the configured field has
at least 3 characters, debounces input for 300 ms, and calls:

```text
/wp-json/dadata-suggestions/v1/party
```

The REST endpoint verifies the WordPress REST nonce, calls DaData's Suggestions
API endpoint for organizations, and returns only the fields the frontend needs.
API errors return an empty UI state instead of breaking the page.

DaData token storage:

- stored in the WordPress option `dadata_suggestions_options`
- sent only from the server to DaData as `Authorization: Token ...`
- never localized into frontend JavaScript

## Link Tracking

If link tracking is enabled, `assets/tracking.js` stores incoming UTM parameters
in `sessionStorage` and appends missing UTM values plus `pageref` to external
HTTP/HTTPS links on click.

`pageref` is derived from the current page path. For example:

```text
/events/company-form/ -> events-company-form
```

Add `data-no-track` or `data-dadata-no-track` to a link to skip tracking.

## Security

Do not commit real DaData tokens. Use the WordPress settings page on each site.

The public REST endpoint requires a WordPress REST nonce and proxies requests
server-side so the browser never receives the DaData token.

## Troubleshooting

- Suggestions do not appear: check that DaData is enabled, the token is saved,
  and the INN selector matches an input on the page.
- Token rejected: confirm the token in the DaData profile and check server
  outbound HTTPS access to `suggestions.dadata.ru`.
- Field selector not found: inspect the form field in the browser and update
  the selector in Settings, DaData INN.
- API unavailable: the field remains usable as a normal input; check WordPress
  PHP logs and the browser Network tab for the REST request.
- Multisite: activate the plugin network-wide if needed, then configure each
  site separately under its own Settings, DaData INN screen.

## Files

```text
dadata-suggestions.php   Plugin bootstrap, settings, REST proxy.
assets/dadata-inn.js     Frontend autocomplete.
assets/tracking.js       UTM and pageref forwarding.
uninstall.php            Option cleanup.
REVIEW.md                Review checklist and smoke-test steps.
```
