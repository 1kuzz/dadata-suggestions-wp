jQuery(function ($) {
    var s = window.dadataSuggestionsSettings;
    if (!s || !s.token) return;

    var each = function (selectorList, fn) {
        if (!selectorList) return;
        selectorList.split(',').forEach(function (sel) {
            sel = sel.trim();
            if (sel) fn(sel);
        });
    };

    each(s.address, function (sel) {
        $(sel).suggestions({
            token: s.token,
            type: 'ADDRESS',
            onSelect: function (suggestion) {
                var d = suggestion.data;
                if (s.addressPostcode) $(s.addressPostcode).val(d.postal_code || '');
                if (s.addressCity) $(s.addressCity).val(d.city || d.settlement || '');
                if (s.addressRegion) $(s.addressRegion).val(d.region || '');
            }
        });
    });

    each(s.fio, function (sel) {
        $(sel).suggestions({ token: s.token, type: 'FIO' });
    });

    each(s.party, function (sel) {
        $(sel).suggestions({ token: s.token, type: 'PARTY' });
    });

    each(s.email, function (sel) {
        $(sel).suggestions({ token: s.token, type: 'EMAIL' });
    });

    each(s.bank, function (sel) {
        $(sel).suggestions({ token: s.token, type: 'BANK' });
    });
});
