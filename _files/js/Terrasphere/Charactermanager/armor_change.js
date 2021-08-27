!function($, window, document, _undefined)
{
    "use strict";

    XF.TS_CM_ChangeArmor = XF.Element.newHandler({

        options: {},

        init: function()
        {
            var $form = this.$target;

            $form.on('ajax-submit:response', $.proxy(this, 'ajaxResponse'));
        },

        ajaxResponse: function(e, data)
        {
            // Update armor icon.
            $(".armor .character-sheet-mastery-icon").attr('src', data.newIconURL);

            // Update armor name.
            $(".armor .character-sheet-mastery-title").html(function(i, oldhtml) {
                return data.newName + oldhtml.substr(oldhtml.indexOf("<br>"));
            });

            // Reset flashing animation element by clone+delete (may not be necessary, but could be).
            var elem = $(".armor .mastery-change-flash");
            var copy = elem.clone(true);
            elem.before(copy);
            $(".armor .mastery-change-flash:last-child").remove();

            // Play reset animation.
            copy.css("animation-play-state", "running");
        }
    });

    // First element same as "data-xf-init" attribute
    XF.Element.register('change-armor', 'XF.TS_CM_ChangeArmor');
}
(jQuery, window, document);