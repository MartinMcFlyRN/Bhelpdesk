// Bhelpdesk - small progressive enhancements (everything works without JS too)
document.addEventListener('DOMContentLoaded', function () {
    // Captcha: "new code" button reloads the image only
    document.querySelectorAll('[data-captcha-refresh]').forEach(function (btn) {
        btn.hidden = false;
        btn.addEventListener('click', function () {
            var img = document.getElementById(btn.getAttribute('data-captcha-refresh'));
            if (img) {
                img.src = img.src.split('?')[0] + '?' + Date.now();
            }
            var input = btn.closest('form').querySelector('input[name="captcha"]');
            if (input) {
                input.value = '';
                input.focus();
            }
        });
    });

    // Attachments: show selected file names and warn about limits before upload
    document.querySelectorAll('input[type="file"][data-max-files]').forEach(function (input) {
        var list = document.getElementById(input.getAttribute('data-list'));
        input.addEventListener('change', function () {
            var maxFiles = parseInt(input.getAttribute('data-max-files'), 10);
            var maxBytes = parseInt(input.getAttribute('data-max-bytes'), 10);
            var msgs = [];
            if (input.files.length > maxFiles) {
                msgs.push(input.getAttribute('data-msg-count'));
            }
            if (list) {
                list.textContent = '';
                Array.prototype.forEach.call(input.files, function (f) {
                    var li = document.createElement('li');
                    li.textContent = f.name + ' (' + (f.size / 1048576).toFixed(1) + ' MB)';
                    if (f.size > maxBytes) {
                        li.className = 'too-big';
                    }
                    list.appendChild(li);
                });
            }
            input.setCustomValidity(msgs.join(' '));
        });
    });
});

// Confirmation for destructive buttons: <button data-confirm="Are you sure?">
document.addEventListener('submit', function (ev) {
    var btn = ev.submitter;
    var msg = (btn && btn.getAttribute('data-confirm')) || ev.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) {
        ev.preventDefault();
    }
});

// User menu (<details>): close on outside click and on Escape
document.addEventListener('click', function (ev) {
    document.querySelectorAll('details.user-menu[open]').forEach(function (d) {
        if (!d.contains(ev.target)) {
            d.removeAttribute('open');
        }
    });
});
document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') {
        document.querySelectorAll('details.user-menu[open]').forEach(function (d) {
            d.removeAttribute('open');
            d.querySelector('summary').focus();
        });
    }
});

// Customer autocomplete: <input data-customer-lookup="datalist-id" data-name-field="input-id">
// Suggests existing customers (name, company, email); picking one fills the name field.
document.querySelectorAll('input[data-customer-lookup]').forEach(function (input) {
    var list = document.getElementById(input.getAttribute('data-customer-lookup'));
    var nameField = document.getElementById(input.getAttribute('data-name-field'));
    var known = {};
    var timer = null;
    input.setAttribute('type', 'text'); // type=email does not allow searching by name
    input.addEventListener('input', function () {
        var q = input.value.trim();
        if (known[q.toLowerCase()] && nameField) {
            nameField.value = known[q.toLowerCase()];
        }
        clearTimeout(timer);
        if (q.length < 2) {
            return;
        }
        timer = setTimeout(function () {
            fetch('/op/customers/lookup?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : []; })
                .then(function (rows) {
                    list.textContent = '';
                    rows.forEach(function (c) {
                        known[c.email.toLowerCase()] = c.name;
                        var opt = document.createElement('option');
                        opt.value = c.email;
                        opt.label = c.name + (c.company ? ' · ' + c.company : '');
                        list.appendChild(opt);
                    });
                })
                .catch(function () {});
        }, 200);
    });
});

// Canned responses: <select data-canned-target="textarea-id"> with <option data-body="...">.
// Inserts the text at the cursor (or appends it), then resets the select.
document.querySelectorAll('select[data-canned-target]').forEach(function (sel) {
    var area = document.getElementById(sel.getAttribute('data-canned-target'));
    if (!area) {
        return;
    }
    sel.hidden = false;
    sel.addEventListener('change', function () {
        var opt = sel.options[sel.selectedIndex];
        var text = opt ? opt.getAttribute('data-body') : null;
        if (text) {
            var start = area.selectionStart != null ? area.selectionStart : area.value.length;
            var end = area.selectionEnd != null ? area.selectionEnd : area.value.length;
            var before = area.value.slice(0, start);
            var after = area.value.slice(end);
            var sep = before && !/\n$/.test(before) ? '\n' : '';
            area.value = before + sep + text + after;
            var pos = (before + sep + text).length;
            area.focus();
            area.setSelectionRange(pos, pos);
            area.dispatchEvent(new Event('input'));
        }
        sel.selectedIndex = 0;
    });
});
