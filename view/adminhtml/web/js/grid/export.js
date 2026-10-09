define(['jquery', 'Magento_Ui/js/grid/export'], function ($, Export) {
    'use strict';
    return Export.extend({
        postRequest: function (option) {
            var params = this.getParams(), $form;
            if (!params || !window.FORM_KEY) {
                return;
            }
            params.form_key = window.FORM_KEY;
            params.namespace = 'coresh_quote_request_listing';
            if (params.selected === false && params.excluded === undefined) {
                delete params.selected;
                params.excluded = false;
            }
            $form = $('<form>').attr({method: 'post', action: option.url}).prop('hidden', true);
            function append(name, value) {
                if (value !== null && typeof value === 'object') {
                    $.each(value, function (key, item) {append(name + '[' + key + ']', item);});
                } else if (value !== undefined && value !== null) {
                    $('<input>').attr({type: 'hidden', name: name}).val(String(value)).appendTo($form);
                }
            }
            $.each(params, append);
            $form.appendTo(document.body);
            $form[0].submit();
            $form.remove();
        }
    });
});
