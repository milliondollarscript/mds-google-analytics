(function () {
    'use strict';

    if (typeof window.gtag !== 'function') {
        return;
    }

    function sendEvent(name, payload) {
        window.gtag('event', name, payload || {});
    }

    function closest(element, selector) {
        return element && element.closest ? element.closest(selector) : null;
    }

    document.addEventListener('click', function (event) {
        var target = event.target;
        var link = closest(target, 'a[href]');

        if (link) {
            var href = link.getAttribute('href') || '';
            var fileMatch = href.match(/\.([a-z0-9]{2,5})(?:[?#].*)?$/i);
            if (fileMatch && ['pdf', 'zip', 'doc', 'docx', 'xls', 'xlsx'].indexOf(fileMatch[1].toLowerCase()) !== -1) {
                sendEvent('file_download', {
                    event_category: 'Downloads',
                    event_label: href.split('/').pop().split('?')[0].split('#')[0],
                    file_extension: fileMatch[1].toLowerCase()
                });
            }

            try {
                var url = new URL(href, window.location.href);
                if (url.hostname && url.hostname !== window.location.hostname) {
                    sendEvent('click', {
                        event_category: 'External Links',
                        event_label: (url.origin + url.pathname).slice(0, 500),
                        transport_type: 'beacon'
                    });
                }
            } catch (error) {
                // Ignore non-URL link schemes.
            }
        }

        if (closest(target, '.mds3-reserve')) {
            sendEvent('mds3_reserve_click', {
                event_category: 'Million Dollar Script Grid',
                event_label: 'Reserve Selection'
            });
        }

        if (closest(target, '.mds3-checkout-link')) {
            sendEvent('mds3_checkout_click', {
                event_category: 'Million Dollar Script Checkout',
                event_label: 'Continue to Payment'
            });
        }

        if (closest(target, '.mds3-grid-canvas, .mds3-grid-map')) {
            sendEvent('mds3_grid_interaction', {
                event_category: 'Million Dollar Script Grid',
                event_label: 'Grid Click'
            });
        }
    }, true);

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form || !form.tagName || form.tagName.toLowerCase() !== 'form') {
            return;
        }

        if (form.classList.contains('mds3-placement-form') || form.classList.contains('mds3-order-upload-form')) {
            sendEvent('mds3_placement_submit', {
                event_category: 'Million Dollar Script Placement',
                event_label: 'Placement Form'
            });
            return;
        }

        sendEvent('form_submit', {
            event_category: 'Forms',
            event_label: 'Form Submission'
        });
    }, true);

    var scrollDepths = [25, 50, 75, 90];
    var scrolled = [];
    var scrollPending = false;

    window.addEventListener('scroll', function () {
        if (scrollPending) {
            return;
        }

        scrollPending = true;
        window.requestAnimationFrame(function () {
            var scrollTop = window.pageYOffset || document.documentElement.scrollTop || 0;
            var docHeight = Math.max(document.body.scrollHeight, document.documentElement.scrollHeight);
            var winHeight = window.innerHeight || document.documentElement.clientHeight || 1;
            var denominator = Math.max(1, docHeight - winHeight);
            var scrollPercent = Math.round(scrollTop / denominator * 100);

            scrollDepths.forEach(function (depth) {
                if (scrollPercent >= depth && scrolled.indexOf(depth) === -1) {
                    scrolled.push(depth);
                    sendEvent('scroll', {
                        event_category: 'Scroll Tracking',
                        event_label: depth + '%',
                        value: depth
                    });
                }
            });
            scrollPending = false;
        });
    }, {passive: true});

    window.setTimeout(function () {
        sendEvent('timing_complete', {
            event_category: 'Engagement',
            event_label: '30_seconds',
            value: 30
        });
    }, 30000);
}());
