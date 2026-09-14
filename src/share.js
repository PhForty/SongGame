/*
 * Share dialog: renders the join URL as a QR code the host can hold up, plus a
 * copy-to-clipboard fallback. Requires qrcode.js.
 */
(function () {
    'use strict';

    var modal = document.getElementById('shareModal');
    var openBtn = document.getElementById('shareOpen');
    var closeBtn = document.getElementById('shareClose');
    var copyBtn = document.getElementById('shareCopy');
    var holder = document.getElementById('shareQr');
    if (!modal || !holder) return;

    var url = holder.getAttribute('data-url') || window.location.href;
    var rendered = false;
    var lastFocus = null;

    function render() {
        if (rendered) return;
        try {
            holder.innerHTML = QRCode.toSvg(url, { size: 260, margin: 4 });
            rendered = true;
        } catch (e) {
            holder.innerHTML = '<p style="color:#000;line-height:1.4">' + url + '</p>';
        }
    }

    function open() {
        render();
        lastFocus = document.activeElement;
        modal.hidden = false;
        if (closeBtn) closeBtn.focus();
    }

    function close() {
        modal.hidden = true;
        if (lastFocus && lastFocus.focus) lastFocus.focus();
    }

    if (openBtn) openBtn.addEventListener('click', open);
    if (closeBtn) closeBtn.addEventListener('click', close);

    modal.addEventListener('click', function (ev) {
        if (ev.target === modal) close();
    });

    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape' && !modal.hidden) close();
    });

    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            var done = function () {
                var original = copyBtn.textContent;
                copyBtn.textContent = copyBtn.getAttribute('data-copied') || 'OK';
                setTimeout(function () { copyBtn.textContent = original; }, 1500);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(done, function () {});
            } else {
                // Older browsers (and any non-secure origin) have no clipboard API.
                var ta = document.createElement('textarea');
                ta.value = url;
                document.body.appendChild(ta);
                ta.select();
                try { document.execCommand('copy'); done(); } catch (e) {}
                document.body.removeChild(ta);
            }
        });
    }

    window.sgOpenShare = open;
})();
