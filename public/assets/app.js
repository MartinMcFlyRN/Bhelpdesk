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
