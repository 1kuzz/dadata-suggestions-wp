# Review Notes

This repository contains a small WordPress plugin with two frontend behaviors:

- DaData INN/company suggestions, disabled by default.
- Optional UTM and `pageref` forwarding for external links, disabled by default.

There is no build step and no remote frontend library.

## Files to Review

```text
dadata-suggestions.php   Plugin bootstrap, settings page, REST endpoint.
assets/dadata-inn.js     Plain JavaScript autocomplete.
assets/tracking.js       Optional UTM and pageref forwarding.
uninstall.php            Option cleanup.
README.md                Installation and usage notes.
CHANGELOG.md             Release notes.
```

## DaData Behavior

The browser never receives the DaData token. When enabled, `assets/dadata-inn.js`
calls:

```text
/wp-json/dadata-suggestions/v1/party
```

The REST handler checks the WordPress REST nonce, validates the query length,
calls DaData's organization suggestions endpoint, and returns only:

```text
value, name, inn, kpp, ogrn, address
```

Queries shorter than 3 characters return an empty suggestion list.

## Local Checks

From the repository root:

```sh
docker run --rm -v "$PWD:/plugin:ro" wordpress:php8.1-apache php -l /plugin/dadata-suggestions.php
docker run --rm -v "$PWD:/plugin:ro" wordpress:php8.1-apache php -l /plugin/uninstall.php
node --check assets/dadata-inn.js
node --check assets/tracking.js
git diff --check
```

Optional package check:

```sh
mkdir -p /tmp/dadata-suggestions/dadata-suggestions/assets
cp dadata-suggestions.php README.md CHANGELOG.md LICENSE uninstall.php \
  /tmp/dadata-suggestions/dadata-suggestions/
cp assets/dadata-inn.js assets/tracking.js /tmp/dadata-suggestions/dadata-suggestions/assets/
cd /tmp/dadata-suggestions
zip -qr /tmp/dadata-suggestions.zip dadata-suggestions
unzip -t /tmp/dadata-suggestions.zip
```

## WordPress Smoke Test

1. Activate **DaData INN Suggestions**.
2. Open Settings, DaData INN.
3. Save with DaData disabled and link tracking disabled.
4. Open a page containing `<input id="company">`.
5. Confirm no `dadata-suggestions` scripts are printed.
6. Enable DaData, add a token, keep `#company` as the INN selector.
7. Open the page again and confirm `assets/dadata-inn.js` is printed.
8. Type at least 3 characters or an INN. Suggestions should appear.
9. Select a company and confirm configured target fields are populated.
10. Disable DaData again and confirm scripts stop loading.

For link tracking, enable the setting and open a page with UTM parameters. An
external link should receive missing UTM values and `pageref` on click.

## Security Review

Check that:

- no real token is committed;
- no remote frontend suggestion library is loaded;
- no frontend object contains the DaData token;
- REST requests without `X-WP-Nonce` fail;
- invalid DaData credentials fail without breaking the page.
