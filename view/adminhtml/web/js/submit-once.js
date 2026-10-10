define(['jquery'], function ($) {
    'use strict';
    return function (config, element) {
        var submitted = false;
        $(element).on('submit.coreshQuoteAdmin', function (event) {
            // Another form validator can cancel this event before a POST is started.
            if (event.defaultPrevented || (event.isDefaultPrevented && event.isDefaultPrevented())) {
                return;
            }
            if (submitted) {
                event.preventDefault();
                return;
            }
            submitted = true;
            $(element).find('button[type="submit"]').prop('disabled', true);
        });
    };
});
