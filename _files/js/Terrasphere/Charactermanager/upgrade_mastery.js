(function($) {
    $.fn.changeElementType = function(newType) {
        var attrs = {};

        $.each(this[0].attributes, function(idx, attr) {
            attrs[attr.nodeName] = attr.nodeValue;
        });

        this.replaceWith(function() {
            return $("<" + newType + "/>", attrs).append($(this).contents());
        });
    }
})(jQuery);

!function($, window, document, _undefined)
{
    "use strict";

    XF.TS_CM_UpgradeMastery = XF.Element.newHandler({

        options: {},

        init: function()
        {
            var $form = this.$target;

            $form.on('ajax-submit:response', $.proxy(this, 'ajaxResponse'));
        },

        ajaxResponse: function(e, data)
        {
            // Set up the new mastery's display...
            var lastRankClass = "rank-"+(data.newRankTier-1);
            var newRankClass = "rank-"+(data.newRankTier);
            $("#mastery-container-" + data.masterySlotIndex + " ." + lastRankClass).removeClass(lastRankClass).addClass(newRankClass);
            $("#mastery-container-" + data.masterySlotIndex + " .rank-stars").removeClass("rank-stars-" + (data.newRankTier-1)).addClass("rank-stars-" + data.newRankTier);
            $("#mastery-container-" + data.masterySlotIndex + " .m-title-rank").text("Rank " + data.newRankTitle);

            // Reset flashy animation element by clone+delete.
            var elem = $("#mastery-container-" + data.masterySlotIndex + " .mastery-change-flash");
            var copy = elem.clone(true);
            elem.before(copy);
            $("#mastery-container-" + data.masterySlotIndex + " .mastery-change-flash:last-child").remove();

            // Play reset animation.
            copy.css("animation-play-state", "running");

            // If the fourth slot was unlocked by this upgrade...
            if(!data.fourthSlotWasUnlocked && data.fourthSlotUnlocked)
            {
                // Hide the locked element of the previously locked slot.
                $("#mastery-container-3" + " .character-sheet-mastery-lock").hide();

                // Show the empty 'select new' portion of the slot.
                $("#mastery-container-3" + " .character-sheet-mastery-empty").show();

                // ...also play the flashy animation thingy.
                $("#mastery-container-3" + " .mastery-change-flash").css("animation-play-state", "running");
            }

            // If the fifth slot was unlocked by this upgrade...
            if(!data.fifthSlotWasUnlocked && data.fifthSlotUnlocked)
            {
                // Hide the locked element of the previously locked slot.
                $("#mastery-container-4" + " .character-sheet-mastery-lock").hide();

                // Show the empty 'select new' portion of the slot.
                $("#mastery-container-4" + " .character-sheet-mastery-empty").show();

                // ...also play the flashy animation thingy.
                $("#mastery-container-4" + " .mastery-change-flash").css("animation-play-state", "running");
            }

            // If rank is now maxed out, make it so we can't click on it anymore.
            if(data.max)
            {
                $("#mastery-container-" + data.masterySlotIndex + " .character-sheet-mastery-icon-container")
                    .removeAttr('href')
                    .removeAttr('data-xf-click')
                    .removeAttr('data-cache')
                    .changeElementType("div");
            }
        }
    });

    // First element same as "data-xf-init" attribute
    XF.Element.register('upgrade-mastery', 'XF.TS_CM_UpgradeMastery');
}
(jQuery, window, document);