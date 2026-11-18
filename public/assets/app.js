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
                    li.textContent = f.name + ' (' + (f.size >= 1048576 ? (f.size / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(f.size / 1024)) + ' KB') + ')';
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
    document.querySelectorAll('details.user-menu[open], details.nav-menu[open]').forEach(function (d) {
        if (!d.contains(ev.target)) {
            d.removeAttribute('open');
        }
    });
});
document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') {
        document.querySelectorAll('details.user-menu[open], details.nav-menu[open]').forEach(function (d) {
            d.removeAttribute('open');
            d.querySelector('summary').focus();
        });
    }
});

// Customer autocomplete: <input data-customer-lookup="datalist-id" data-name-field="input-id">
// Suggests existing customers (any part of name, company or email) in a menu
// under the field; picking one fills the email and the name field.
// (A custom menu instead of <datalist>: Safari filters a datalist by prefix
// and does not refresh it while typing.)
function bhdCustomerLookup(input) {
    var list = document.getElementById(input.getAttribute('data-customer-lookup'));
    var nameField = document.getElementById(input.getAttribute('data-name-field'));
    var timer = null, rows = [], active = -1, seq = 0;
    input.setAttribute('type', 'text'); // type=email does not allow searching by name
    input.removeAttribute('list');
    if (list) {
        list.remove();
    }
    var menu = document.createElement('div');
    menu.className = 'lookup-menu';
    menu.hidden = true;
    input.parentNode.classList.add('lookup-wrap');
    input.after(menu);

    var close = function () { menu.hidden = true; active = -1; };
    var pick = function (c) {
        input.value = c.email;
        if (nameField) {
            nameField.value = c.name;
        }
        close();
        input.focus();
    };
    var highlight = function () {
        Array.prototype.forEach.call(menu.children, function (el, i) { el.classList.toggle('active', i === active); });
    };
    var render = function () {
        menu.textContent = '';
        rows.forEach(function (c) {
            var item = document.createElement('button');
            item.type = 'button';
            item.className = 'lookup-item';
            var n = document.createElement('strong');
            n.textContent = c.name + (c.company ? ' · ' + c.company : '');
            var em = document.createElement('span');
            em.textContent = c.email;
            item.appendChild(n);
            item.appendChild(em);
            // mousedown: before the field loses the focus
            item.addEventListener('mousedown', function (ev) { ev.preventDefault(); pick(c); });
            menu.appendChild(item);
        });
        active = -1;
        menu.hidden = rows.length === 0;
    };
    input.addEventListener('input', function () {
        var q = input.value.trim();
        clearTimeout(timer);
        if (q.length < 2) {
            rows = [];
            close();
            return;
        }
        timer = setTimeout(function () {
            var mine = ++seq;
            fetch('/op/customers/lookup?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : []; })
                .then(function (res) {
                    if (mine !== seq) {
                        return; // an older answer arriving late
                    }
                    rows = res;
                    if (rows.length === 1 && rows[0].email.toLowerCase() === q.toLowerCase()) {
                        if (nameField && !nameField.value) {
                            nameField.value = rows[0].name;
                        }
                        rows = [];
                    }
                    render();
                })
                .catch(function () {});
        }, 200);
    });
    input.addEventListener('keydown', function (ev) {
        if (menu.hidden) {
            return;
        }
        if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
            ev.preventDefault();
            active = (active + (ev.key === 'ArrowDown' ? 1 : -1) + rows.length) % rows.length;
            highlight();
        } else if (ev.key === 'Enter' && active >= 0) {
            ev.preventDefault();
            pick(rows[active]);
        } else if (ev.key === 'Escape') {
            close();
        }
    });
    input.addEventListener('blur', function () { setTimeout(close, 150); });
}
document.querySelectorAll('input[data-customer-lookup]').forEach(bhdCustomerLookup);

// New ticket: "+ add" more CC rows (each with its own autocomplete)
document.querySelectorAll('[data-add-cc]').forEach(function (btn) {
    var box = document.getElementById(btn.getAttribute('data-add-cc'));
    if (!box) {
        return;
    }
    btn.hidden = false;
    var n = box.querySelectorAll('.cc-row').length;
    btn.addEventListener('click', function () {
        var first = box.querySelector('.cc-row');
        var row = first.cloneNode(true);
        var email = row.querySelector('input[name="cc_email[]"]');
        var name = row.querySelector('input[name="cc_name[]"]');
        var menu = row.querySelector('.lookup-menu');
        if (menu) {
            menu.remove();
        }
        email.value = '';
        name.value = '';
        name.id = 'nt-cc-name-' + n;
        email.setAttribute('data-customer-lookup', 'nt-cc-list-' + n);
        email.setAttribute('data-name-field', name.id);
        n += 1;
        box.appendChild(row);
        bhdCustomerLookup(email);
        email.focus();
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
        if (text && area._quill) {
            // rich editor: paste the HTML at the cursor
            var q = area._quill;
            var range = q.getSelection(true) || { index: q.getLength() };
            q.clipboard.dangerouslyPasteHTML(range.index, text, 'user');
            q.focus();
        } else if (text) {
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

// Operator form: department checkboxes do not matter for administrators
document.querySelectorAll('select[name="role"]').forEach(function (sel) {
    var pick = sel.closest('form') && sel.closest('form').querySelector('[data-role-field]');
    if (!pick) {
        return;
    }
    // billing operators see no tickets: no departments; "all departments" makes the list irrelevant
    var all = pick.querySelector('[data-all-deps]');
    var sync = function () {
        pick.hidden = sel.value === 'billing';
        pick.classList.toggle('is-all', !!(all && all.checked));
    };
    sel.addEventListener('change', sync);
    if (all) {
        all.addEventListener('change', sync);
    }
    sync();
});

// Ticket list bulk actions: selection, action bar and confirmation dialogs
(function () {
    var form = document.getElementById('bulk-form');
    if (!form) {
        return;
    }
    var bar = document.getElementById('bulk-bar');
    var all = form.querySelector('[data-bulk-all]');
    var items = Array.prototype.slice.call(form.querySelectorAll('[data-bulk-item]'));
    var selected = function () { return items.filter(function (i) { return i.checked; }); };

    function refresh() {
        var n = selected().length;
        bar.hidden = n === 0;
        form.querySelector('[data-bulk-count]').textContent = n;
        form.querySelectorAll('[data-open-dialog]').forEach(function (b) {
            b.disabled = n < parseInt(b.getAttribute('data-min') || '1', 10);
        });
        if (all) {
            all.checked = n > 0 && n === items.length;
            all.indeterminate = n > 0 && n < items.length;
        }
        items.forEach(function (i) { i.closest('tr').classList.toggle('row-selected', i.checked); });
    }
    items.forEach(function (i) { i.addEventListener('change', refresh); });
    if (all) {
        all.addEventListener('change', function () { items.forEach(function (i) { i.checked = all.checked; }); refresh(); });
    }
    form.querySelector('[data-bulk-clear]').addEventListener('click', function () { items.forEach(function (i) { i.checked = false; }); refresh(); });

    function label(i) {
        return '#' + i.getAttribute('data-number') + ' · ' + i.getAttribute('data-subject') + ' (' + i.getAttribute('data-customer') + ')';
    }

    form.querySelectorAll('[data-open-dialog]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var dlg = document.getElementById(btn.getAttribute('data-open-dialog'));
            var sel = selected();
            var targets = dlg.querySelector('[data-merge-targets]');
            if (targets) {
                // radio list: which ticket to keep; the oldest one (lowest id) is preselected
                targets.textContent = '';
                var oldest = Math.min.apply(null, sel.map(function (i) { return parseInt(i.value, 10); }));
                sel.forEach(function (i) {
                    var l = document.createElement('label');
                    l.className = 'check';
                    var r = document.createElement('input');
                    r.type = 'radio';
                    r.name = 'target';
                    r.value = i.value;
                    r.required = true;
                    r.checked = parseInt(i.value, 10) === oldest;
                    l.appendChild(r);
                    l.appendChild(document.createTextNode(' ' + label(i)));
                    targets.appendChild(l);
                });
            }
            var list = dlg.querySelector('[data-delete-list]');
            if (list) {
                list.textContent = '';
                sel.forEach(function (i) { var li = document.createElement('li'); li.textContent = label(i); list.appendChild(li); });
                var chk = dlg.querySelector('[data-delete-confirm]');
                var sub = dlg.querySelector('[data-delete-submit]');
                chk.checked = false;
                sub.disabled = true;
                chk.onchange = function () { sub.disabled = !chk.checked; };
            }
            dlg.showModal();
        });
    });
    form.querySelectorAll('[data-close-dialog]').forEach(function (b) {
        b.addEventListener('click', function () { b.closest('dialog').close(); });
    });
    refresh();
})();

// Email identity form: default port when the security changes, POP3 fields only when enabled
document.querySelectorAll('select[data-port-for]').forEach(function (sel) {
    var port = sel.form.querySelector('input[name="' + sel.getAttribute('data-port-for') + '"]');
    var ports = sel.getAttribute('data-ports').split(','); // ssl, starttls, none
    var keys = ['ssl', 'starttls', 'none'];
    sel.addEventListener('change', function () {
        var known = ports.indexOf(String(port.value)) !== -1;
        if (port && (known || port.value === '')) {
            port.value = ports[keys.indexOf(sel.value)];
        }
    });
});
document.querySelectorAll('input[data-toggle]').forEach(function (chk) {
    var box = document.getElementById(chk.getAttribute('data-toggle'));
    var sync = function () { box.hidden = !chk.checked; };
    chk.addEventListener('change', sync);
    sync();
});

// <select data-autosubmit>: submit the form when the choice changes (no inline handlers: strict CSP)
document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
    el.addEventListener('change', function () {
        if (el.form) {
            el.form.submit();
        }
    });
});

// Checkbox with confirmation: data-confirm-on (when checking) / data-confirm-off (when unchecking)
document.querySelectorAll('input[type=checkbox][data-confirm-on]').forEach(function (box) {
    box.addEventListener('change', function () {
        var msg = box.checked ? box.getAttribute('data-confirm-on') : box.getAttribute('data-confirm-off');
        if (msg && !window.confirm(msg)) {
            box.checked = !box.checked;
        }
    });
});

// 2FA setup: QR code of the otpauth:// URI, drawn in the browser (vendor/qrcode-generator, MIT)
document.querySelectorAll('[data-otpauth]').forEach(function (box) {
    if (typeof window.qrcode !== 'function') {
        return;
    }
    var qr = window.qrcode(0, 'M');
    qr.addData(box.getAttribute('data-otpauth'));
    qr.make();
    var img = document.createElement('img');
    img.src = qr.createDataURL(5, 4);
    img.alt = 'QR code';
    img.width = img.height = qr.getModuleCount() * 5 + 8 * 5;
    box.appendChild(img);
});

// "Print" buttons (recovery codes)
document.querySelectorAll('[data-print]').forEach(function (btn) {
    btn.addEventListener('click', function () { window.print(); });
});

// Today's on-call operator: the box is printed at the top of <main> and moved
// next to the first page title, centred in the free space of the title row.
(function () {
    var box = document.querySelector('[data-oncall-today]');
    var h1 = box && document.querySelector('main h1');
    if (!h1) {
        return;
    }
    var parent = h1.parentElement;
    if (parent.classList.contains('page-head')) {
        parent.classList.add('has-oncall');
        h1.after(box);
    } else if (parent.parentElement && parent.parentElement.classList.contains('ticket-head')) {
        parent.after(box);           // ticket view: between title block and badges
    } else {
        var row = document.createElement('div');
        row.className = 'page-head has-oncall';
        parent.insertBefore(row, h1);
        row.appendChild(h1);
        row.appendChild(box);
    }
}());

// Ticket list auto refresh (OFF / 5s / 10s / 30s), remembered in this browser.
// The reload waits while the operator is working on the page (typing,
// tickets selected for a bulk action, a menu open).
document.querySelectorAll('select[data-autorefresh]').forEach(function (sel) {
    var KEY = 'bhd.autorefresh', timer = null;
    try { sel.value = window.localStorage.getItem(KEY) || '0'; } catch (e) { /* storage unavailable */ }
    if (!sel.value) {
        sel.value = '0';
    }
    var busy = function () {
        var a = document.activeElement;
        var typing = a && a !== sel && (/^(TEXTAREA|SELECT)$/.test(a.tagName)
            || (a.tagName === 'INPUT' && !/^(checkbox|radio|button|submit)$/.test(a.type)));
        return typing
            || document.querySelector('main input[type=checkbox]:checked')
            || document.querySelector('details[open]');
    };
    var schedule = function () {
        clearTimeout(timer);
        var s = parseInt(sel.value, 10) || 0;
        if (s > 0) {
            timer = setTimeout(function () {
                if (busy() || document.hidden) {
                    schedule();
                } else {
                    window.location.reload();
                }
            }, s * 1000);
        }
    };
    sel.addEventListener('change', function () {
        try { window.localStorage.setItem(KEY, sel.value); } catch (e) { /* storage unavailable */ }
        sel.blur();
        schedule();
    });
    schedule();
});

// Tools: disable the button while the command runs (mtr takes a while)
document.querySelectorAll('form[data-tools-form]').forEach(function (f) {
    f.addEventListener('submit', function () {
        var b = f.querySelector('button[type=submit]');
        b.disabled = true;
        b.textContent = b.getAttribute('data-busy-label');
    });
});

// Top menu on phones: the ☰ button opens and closes the menu
document.querySelectorAll('[data-nav-toggle]').forEach(function (btn) {
    var bar = btn.closest('.topbar');
    btn.addEventListener('click', function () {
        var open = bar.classList.toggle('nav-open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
});

// Department links: ✕ empties the row (saved as removed)
document.querySelectorAll('[data-clear-row]').forEach(function (b) {
    b.addEventListener('click', function () {
        var row = b.closest('.dlinks-row');
        row.querySelectorAll('input').forEach(function (i) { i.value = ''; });
        row.classList.add('is-cleared');
    });
});
