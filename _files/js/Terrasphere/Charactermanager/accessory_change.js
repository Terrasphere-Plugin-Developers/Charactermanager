!function($, window, document, _undefined)
{
    "use strict";

    XF.TS_CM_ChangeAccessory = XF.Element.newHandler({

        options: {},

        init: function()
        {
            var $form = this.$target;

            $form.on('ajax-submit:response', $.proxy(this, 'ajaxResponse'));
        },

        ajaxResponse: function(e, data)
        {
            // Update accessory icon.
            $(".accessory-icon").attr('src', data.newIconURL);

            // Update accessory name.
            $(".accessory-name").html(function(i, oldhtml) {
                return data.newName + oldhtml.substr(oldhtml.indexOf("<br>"));
            });

            // Reset flashing animation element by clone+delete (may not be necessary, but could be).
            var elem = $(".accessory-flash");
            var copy = elem.clone(true);
            elem.before(copy);
            $(".accessory-flash:last-child").remove();


            // Play reset animation.
            copy.css("animation-play-state", "running");
        }
    });

    // First element same as "data-xf-init" attribute
    XF.Element.register('change-accessory', 'XF.TS_CM_ChangeAccessory');
}
(jQuery, window, document);