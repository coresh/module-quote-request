define([
    'jquery',
    'Magento_Ui/js/modal/modal',
    'Magento_Customer/js/customer-data',
    'mage/translate',
    'mage/cookies'
], function ($, modal, customerData, $t) {
    'use strict';

    function randomToken() {
        var bytes = new Uint8Array(32);
        if (!window.crypto || typeof window.crypto.getRandomValues !== 'function') {
            throw new Error('Secure random unavailable');
        }
        window.crypto.getRandomValues(bytes);
        return Array.prototype.map.call(bytes, function (value) {
            return ('0' + value.toString(16)).slice(-2);
        }).join('');
    }

    return function (config, element) {
        var $modal = $(element), $form = $modal.find('[data-role="quote-form"]'),
            $response = $modal.find('[data-role="response"]'), $submit = $modal.find('[data-role="submit"]'),
            isInline = Boolean(config.inline), current = config.inline ? config.productContext : null,
            lastFingerprint = null, token = null, busy = false, fromPdp = Boolean(config.inline),
            profileReloadGeneration = 0,
            autoFilled = {customer_name: null, customer_email: null};

        if ($modal.data('coreshInitialized')) {
            return;
        }
        $modal.data('coreshInitialized', true);
        if (!isInline) {
            modal({
                type: 'popup', responsive: true, innerScroll: true,
                title: $t('Quote Request'), buttons: [],
                modalClass: 'coresh-quote-dialog'
            }, $modal);
        }

        function notify(message, isError) {
            $response.prop('hidden', false).toggleClass('error', isError)
                .toggleClass('success', !isError).text(message);
        }

        function suppressButtons() {
            if (config.disablePurchasing) {
                $('.action.tocart, #product-addtocart-button, .box-tocart').addClass('coresh-quote-purchase-suppressed');
            }
            $('[data-coresh-quote], [data-coresh-display]').each(function () {
                var $button = $(this), context;
                try {
                    context = JSON.parse($button.attr('data-coresh-quote') || $button.attr('data-coresh-display'));
                } catch (error) {
                    return;
                }
                if (context && typeof context === 'object' && context.suppressPurchase) {
                    if ($button.attr('data-coresh-pdp')) {
                        $('.product-info-main .box-tocart').addClass('coresh-quote-purchase-suppressed');
                    } else {
                        $button.closest('.product-item').find('.action.tocart, .actions-primary')
                            .addClass('coresh-quote-purchase-suppressed');
                    }
                }
            });
        }

        function populatePrivateProfile() {
            var generation = ++profileReloadGeneration;
            function fill(data) {
                if (!data) {
                    return;
                }
                $.each({customer_name: data.name || '', customer_email: data.email || ''}, function (field, value) {
                    var $input = $form.find('[name="' + field + '"]');
                    if (!$input.val() || $input.val() === autoFilled[field]) {
                        $input.val(value);
                        autoFilled[field] = value;
                    }
                });
            }
            // Do not render cached PII from a previous account; fetch the current private session.
            $.each(autoFilled, function (field, value) {
                var $input = $form.find('[name="' + field + '"]');
                if (value !== null && $input.val() === value) {
                    $input.val('');
                }
                autoFilled[field] = null;
            });
            customerData.reload(['coresh-quote-request-customer'], false).done(function (sections) {
                // An older response must not overwrite a newer product/session profile reload.
                if (generation === profileReloadGeneration && !busy && current) {
                    fill(sections['coresh-quote-request-customer']);
                }
            });
        }

        if (!isInline) {
            $(document).on('click.coreshQuote', '[data-coresh-quote]', function (event) {
            var context;
            event.preventDefault();
            if (busy) {
                return;
            }
            try {
                context = JSON.parse($(this).attr('data-coresh-quote'));
            } catch (error) {
                return;
            }
            if (!context || typeof context !== 'object' || !context.productId) {
                return;
            }
            if (!current || current.productId !== context.productId) {
                $form[0].reset();
                token = null;
                lastFingerprint = null;
            }
            current = context;
            fromPdp = Boolean($(this).attr('data-coresh-pdp'));
            $form.find('[name="product_id"]').val(context.productId);
            $modal.find('[data-role="product-name"]').text(context.productName || '');
            $response.prop('hidden', true).text('');
            $form.prop('hidden', false);
            $submit.prop('disabled', false);
            if (fromPdp) {
                var productQuantity = $('#product_addtocart_form [name="qty"]').val();
                if (productQuantity) {
                    $form.find('[name="quantity"]').val(productQuantity);
                }
            }
            $modal.removeAttr('hidden').modal('openModal');
            populatePrivateProfile();
            });
        } else if (current && current.productId) {
            $form.find('[name="product_id"]').val(current.productId);
            $modal.find('[data-role="product-name"]').text(current.productName || '');
            populatePrivateProfile();
        }

        $form.on('submit', function (event) {
            var payload, fingerprint, formKey;
            event.preventDefault();
            if (busy || !current || !$form[0].reportValidity()) {
                return;
            }
            payload = {
                product_id: String(current.productId),
                quantity: $form.find('[name="quantity"]').val(),
                customer_name: $form.find('[name="customer_name"]').val(),
                customer_email: $form.find('[name="customer_email"]').val(),
                customer_telephone: $form.find('[name="customer_telephone"]').val(),
                message: $form.find('[name="message"]').val(),
                honeypot: $form.find('[name="honeypot"]').val(),
                super_attribute: {}
            };
            if (fromPdp) {
                $('#product_addtocart_form [name^="super_attribute["]').each(function () {
                    var match = this.name.match(/^super_attribute\[(\d+)\]$/);
                    if (match && $(this).val()) {
                        payload.super_attribute[match[1]] = String($(this).val());
                    }
                });
            }
            fingerprint = JSON.stringify(payload);
            try {
                if (!token || fingerprint !== lastFingerprint) {
                    token = randomToken();
                    lastFingerprint = fingerprint;
                }
            } catch (error) {
                notify($t('A secure connection is required. Please reload this page using HTTPS.'), true);
                return;
            }
            formKey = $.mage.cookies.get('form_key');
            if (!formKey) {
                notify($t('Your session has expired. Please reload this page and try again.'), true);
                return;
            }
            payload.form_key = formKey;
            payload.request_token = token;
            $form.find('[name="form_key"]').val(formKey);
            $form.find('[name="request_token"]').val(token);
            busy = true;
            $submit.prop('disabled', true);
            $response.prop('hidden', true).text('');
            $.ajax({url: config.submitUrl, method: 'POST', dataType: 'json', data: payload, timeout: 30000})
                .done(function (result) {
                    if (result && result.success) {
                        notify(result.message || $t('Your quote request has been accepted.'), false);
                        $form.prop('hidden', !isInline);
                        token = null;
                        lastFingerprint = null;
                        $form[0].reset();
                    } else {
                        notify(result && result.message ? result.message : $t('The request could not be accepted.'), true);
                    }
                })
                .fail(function (xhr) {
                    // Keep the same token for the same payload: the server may already have accepted it.
                    if (xhr.status >= 400 && xhr.status < 500 && xhr.responseJSON && xhr.responseJSON.message) {
                        notify(xhr.responseJSON.message, true);
                    } else {
                        notify($t('The connection failed. Please try again; your request will not be saved twice.'), true);
                    }
                })
                .always(function () {
                    busy = false;
                    $submit.prop('disabled', false);
                    if (isInline && !token) {
                        populatePrivateProfile();
                    }
                });
        });

        if (!isInline) {
            suppressButtons();
            $(document).on('contentUpdated.coreshQuote', suppressButtons);
        }
    };
});
