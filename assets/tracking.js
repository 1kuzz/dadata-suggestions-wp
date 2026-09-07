(function () {
  'use strict';

  if (window.__dadata_suggestions_tracking) return;
  window.__dadata_suggestions_tracking = true;

  var STORE_KEY = '__dadata_suggestions_utm';
  var UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];
  var LOCALE = /^[a-z]{2}(?:-[a-z]{2})?$/i;

  function pickUtm(search) {
    var source = new URLSearchParams(search);
    var out = new URLSearchParams();
    UTM_KEYS.forEach(function (key) {
      if (source.has(key)) out.set(key, source.get(key));
    });
    return out.toString();
  }

  function rememberUtm() {
    var value = pickUtm(location.search);
    if (!value) return;
    try {
      sessionStorage.setItem(STORE_KEY, value);
    } catch (e) {}
  }

  function getUtm() {
    var value = pickUtm(location.search);
    if (value) return value;
    try {
      return sessionStorage.getItem(STORE_KEY) || '';
    } catch (e) {
      return '';
    }
  }

  function pageRef() {
    var parts = decodeURIComponent(location.pathname || '').split('/').filter(Boolean);
    if (parts.length > 1 && LOCALE.test(parts[0])) parts.shift();
    if (parts.length) {
      parts[parts.length - 1] = parts[parts.length - 1].replace(/\.[a-z0-9]+$/i, '');
    }
    return parts.join('-').toLowerCase();
  }

  function trackedUrl(href) {
    try {
      var url = new URL(href, location.origin);
      if (url.origin === location.origin) return href;

      new URLSearchParams(getUtm()).forEach(function (value, key) {
        if (!url.searchParams.has(key)) url.searchParams.set(key, value);
      });

      if (!url.searchParams.has('pageref')) {
        var ref = pageRef();
        if (ref) url.searchParams.set('pageref', ref);
      }

      return url.toString();
    } catch (e) {
      return href;
    }
  }

  rememberUtm();
  window.dadataSuggestionsTrackedUrl = trackedUrl;

  document.addEventListener('click', function (event) {
    var link = event.target.closest && event.target.closest('a[href]');
    if (
      !link ||
      link.hasAttribute('data-no-track') ||
      link.hasAttribute('data-dadata-no-track')
    ) {
      return;
    }

    var href = link.getAttribute('href');
    if (!href || !/^https?:\/\//i.test(href)) return;

    link.setAttribute('href', trackedUrl(href));
  }, true);
})();
