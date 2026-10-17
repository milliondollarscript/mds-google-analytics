(function ($) {
    'use strict';

    function renderResult($result, message, state) {
        $result
            .removeClass('mds-analytics-notice-success mds-analytics-notice-error mds-analytics-notice-neutral')
            .addClass('mds-analytics-notice mds-analytics-notice-' + state)
            .text(message || '');
    }

    $(document).on('click', '#mds-ga-test-tracking', function () {
        var $button = $(this);
        var $result = $('#mds-ga-test-result');

        $button.prop('disabled', true);
        renderResult($result, mdsAnalyticsAdmin.checking, 'neutral');

        $.ajax({
            url: mdsAnalyticsAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mds_ga_test_tracking',
                nonce: mdsAnalyticsAdmin.nonce
            }
        }).done(function (response) {
            if (response && response.success) {
                renderResult($result, response.data && response.data.message, 'success');
                return;
            }

            renderResult($result, response && response.data && response.data.message || mdsAnalyticsAdmin.error, 'error');
        }).fail(function (xhr) {
            var response = xhr && xhr.responseJSON;
            renderResult($result, response && response.data && response.data.message || mdsAnalyticsAdmin.error, 'error');
        }).always(function () {
            $button.prop('disabled', false);
        });
    });
}(jQuery));
