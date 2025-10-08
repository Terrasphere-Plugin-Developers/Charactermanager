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

    XF.TS_CM_UpgradeExpertise = XF.Element.newHandler({

        options: {},

        init: function()
        {
            var $form = this.$target;

            $form.on('ajax-submit:response', $.proxy(this, 'ajaxResponse'));
        },

        ajaxResponse: function(e, data)
        {
            // Set up the new expertise's display...
            var lastRankClass = "rank-"+(data.newRankTier-1);
            var newRankClass = "rank-"+(data.newRankTier);
            $("#expertise-container-" + data.expertiseSlotIndex + " ." + lastRankClass).removeClass(lastRankClass).addClass(newRankClass);
            $("#expertise-container-" + data.expertiseSlotIndex + " .rank-stars").removeClass("rank-stars-" + (data.newRankTier-1)).addClass("rank-stars-" + data.newRankTier);
            $("#expertise-container-" + data.expertiseSlotIndex + " .m-title-rank").text("" + data.newRankTitle);

            // Reset flashy animation element by clone+delete.
            var elem = $("#expertise-container-" + data.expertiseSlotIndex + " .expertise-change-flash");
            var copy = elem.clone(true);
            elem.before(copy);
            $("#expertise-container-" + data.expertiseSlotIndex + " .expertise-change-flash:last-child").remove();

            // Play reset animation.
            copy.css("animation-play-state", "running");

            // Check if any slots were unlocked by this upgrade
            if(data.previousUnlockedExpertiseSlots && data.unlockedExpertiseSlots)
            {
                for(var i = 0; i < data.unlockedExpertiseSlots.length; i++)
                {
                    // If this slot was just unlocked (was 0, now 1)
                    if(data.previousUnlockedExpertiseSlots[i] === 0 && data.unlockedExpertiseSlots[i] === 1)
                    {
                        // Hide the locked element of the previously locked slot.
                        $("#expertise-container-" + i + " .character-sheet-expertise-lock").hide();

                        // Show the empty 'select new' portion of the slot.
                        $("#expertise-container-" + i + " .character-sheet-expertise-empty").show();

                        // ...also play the flashy animation thingy.
                        $("#expertise-container-" + i + " .expertise-change-flash").css("animation-play-state", "running");
                    }
                }
            }


            // If rank is now maxed out, make it so we can't click on it anymore.
            if(data.max)
            {
                $("#expertise-container-" + data.expertiseSlotIndex + " .character-sheet-expertise-icon-container")
                    .removeAttr('href')
                    .removeAttr('data-xf-click')
                    .removeAttr('data-cache')
                    .changeElementType("div");
            }
        }
    });

    // First element same as "data-xf-init" attribute
    XF.Element.register('upgrade-expertise', 'XF.TS_CM_UpgradeExpertise');
}
(jQuery, window, document);
