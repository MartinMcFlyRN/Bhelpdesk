// Bhelpdesk - WYSIWYG editor (Quill 2, vendored in /assets/vendor) for operators.
//
// <textarea data-editor="full|signature"> becomes a Quill editor. On submit the
// textarea receives the HTML and the form field "format" is set to "html".
// The server sanitizes everything again: the editor is only a convenience.
// Without JavaScript the plain textarea keeps working (format stays "text").
(function () {
    'use strict';
    if (typeof window.Quill === 'undefined') {
        return;
    }

    var TOOLBARS = {
        full: [
            [{ header: [2, 3, false] }],
            ['bold', 'italic', 'underline', 'strike'],
            [{ list: 'ordered' }, { list: 'bullet' }, { indent: '-1' }, { indent: '+1' }],
            [{ align: [] }],
            ['blockquote', 'code-block', 'link', 'image'],
            ['clean']
        ],
        signature: [
            ['bold', 'italic', 'underline'],
            [{ align: [] }],
            ['link', 'image'],
            ['clean']
        ]
    };

    function csrfOf(form) {
        var f = form && form.querySelector('input[name="_csrf"]');
        return f ? f.value : '';
    }

    // Upload an image to /op/media and return its URL
    function uploadImage(file, form) {
        var data = new FormData();
        data.append('image', file);
        data.append('_csrf', csrfOf(form));
        return fetch('/op/media', { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
            .then(function (res) {
                if (!res.ok || !res.body.url) {
                    throw new Error(res.body.error || 'Upload failed');
                }
                return res.body.url;
            });
    }

    function insertImages(quill, files, form, range) {
        var index = range ? range.index : quill.getLength();
        Array.prototype.forEach.call(files, function (file) {
            if (!/^image\/(png|jpe?g|gif|webp)$/.test(file.type)) {
                return;
            }
            uploadImage(file, form).then(function (url) {
                quill.insertEmbed(index, 'image', url, 'user');
                quill.setSelection(index + 1, 0, 'silent');
                index += 1;
            }).catch(function (err) {
                window.alert(err.message);
            });
        });
    }

    document.querySelectorAll('textarea[data-editor]').forEach(function (area) {
        var form = area.closest('form');
        var kind = area.getAttribute('data-editor') === 'signature' ? 'signature' : 'full';
        var holder = document.createElement('div');
        holder.className = 'editor editor-' + kind;
        area.parentNode.insertBefore(holder, area.nextSibling);
        area.hidden = true;
        area.required = false;

        var quill = new window.Quill(holder, {
            theme: 'snow',
            placeholder: area.getAttribute('placeholder') || '',
            modules: {
                toolbar: {
                    container: TOOLBARS[kind],
                    handlers: {
                        image: function () {
                            var input = document.createElement('input');
                            input.type = 'file';
                            input.accept = 'image/png,image/jpeg,image/gif,image/webp';
                            input.addEventListener('change', function () {
                                insertImages(quill, input.files, form, quill.getSelection(true));
                            });
                            input.click();
                        }
                    }
                },
                // dropped / pasted image files are uploaded instead of inlined as base64
                uploader: {
                    handler: function (range, files) {
                        insertImages(quill, files, form, range);
                    }
                }
            }
        });

        if (area.value.trim() !== '') {
            quill.clipboard.dangerouslyPasteHTML(0, area.value, 'silent');
        }
        area._quill = quill;

        if (form) {
            var format = form.querySelector('input[name="format"]');
            if (format) {
                format.value = 'html';
            }
            form.addEventListener('submit', function () {
                // Quill 2 turns every space into &nbsp;, which prevents line wrapping
                var html = quill.getSemanticHTML().replace(/&nbsp;/g, ' ');
                area.value = quill.getText().trim() === '' && html.indexOf('<img') === -1 ? '' : html;
            });
        }
    });
})();
