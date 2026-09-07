(function () {
  'use strict';

  var config = window.DadataSuggestions || {};
  var selectors = config.selectors || {};
  var activeRequest = null;
  var timer = 0;
  var box = null;
  var currentInput = null;

  function all(selector) {
    try {
      return selector ? Array.prototype.slice.call(document.querySelectorAll(selector)) : [];
    } catch (e) {
      return [];
    }
  }

  function setValue(selector, value) {
    all(selector).forEach(function (field) {
      field.value = value || '';
      field.dispatchEvent(new Event('input', { bubbles: true }));
      field.dispatchEvent(new Event('change', { bubbles: true }));
    });
  }

  function hideBox() {
    if (box) box.hidden = true;
  }

  function ensureBox(input) {
    if (!box) {
      box = document.createElement('div');
      box.className = 'dadata-inn-suggestions';
      box.hidden = true;
      box.style.cssText = [
        'position:absolute',
        'z-index:99999',
        'background:#fff',
        'border:1px solid #ccd0d4',
        'box-shadow:0 2px 8px rgba(0,0,0,.12)',
        'font:14px/1.4 sans-serif',
        'max-height:220px',
        'overflow:auto'
      ].join(';');
      document.body.appendChild(box);
    }

    var rect = input.getBoundingClientRect();
    box.style.left = (rect.left + window.scrollX) + 'px';
    box.style.top = (rect.bottom + window.scrollY + 2) + 'px';
    box.style.width = rect.width + 'px';
    return box;
  }

  function applySuggestion(item) {
    setValue(selectors.inn, item.inn || '');
    setValue(selectors.name, item.name || item.value || '');
    setValue(selectors.kpp, item.kpp || '');
    setValue(selectors.ogrn, item.ogrn || '');
    setValue(selectors.address, item.address || '');
    hideBox();
  }

  function render(input, suggestions) {
    var menu = ensureBox(input);
    menu.innerHTML = '';

    if (!suggestions.length) {
      hideBox();
      return;
    }

    suggestions.forEach(function (item) {
      var option = document.createElement('button');
      option.type = 'button';
      option.style.cssText = [
        'display:block',
        'width:100%',
        'padding:8px 10px',
        'border:0',
        'background:#fff',
        'text-align:left',
        'cursor:pointer'
      ].join(';');
      option.textContent = item.value || item.name || item.inn || '';
      option.addEventListener('mouseenter', function () { option.style.background = '#f0f0f1'; });
      option.addEventListener('mouseleave', function () { option.style.background = '#fff'; });
      option.addEventListener('mousedown', function (event) {
        event.preventDefault();
        applySuggestion(item);
      });
      menu.appendChild(option);
    });

    menu.hidden = false;
  }

  function lookup(input) {
    var query = input.value.trim();
    if (query.length < 3) {
      hideBox();
      return;
    }

    if (activeRequest) activeRequest.abort();
    activeRequest = new AbortController();

    fetch(config.restUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': config.nonce
      },
      body: JSON.stringify({ query: query }),
      signal: activeRequest.signal
    })
      .then(function (response) {
        if (!response.ok) throw new Error('bad response');
        return response.json();
      })
      .then(function (data) {
        if (input !== currentInput) return;
        render(input, Array.isArray(data.suggestions) ? data.suggestions : []);
      })
      .catch(function (error) {
        if ('AbortError' !== error.name) hideBox();
      });
  }

  function bind(input) {
    input.setAttribute('autocomplete', 'off');
    input.addEventListener('input', function () {
      currentInput = input;
      clearTimeout(timer);
      timer = setTimeout(function () { lookup(input); }, 300);
    });
    input.addEventListener('blur', function () {
      setTimeout(hideBox, 150);
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (!config.restUrl || !config.nonce || !selectors.inn) return;
    all(selectors.inn).forEach(bind);
  });
})();
