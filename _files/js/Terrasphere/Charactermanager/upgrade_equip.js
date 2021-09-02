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

    XF.TS_CM_UpgradeEquip = XF.Element.newHandler({

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

            $("#equip-container-" + data.equipGroup + " ." + lastRankClass).removeClass(lastRankClass).addClass(newRankClass);
            $("#equip-container-" + data.equipGroup + " .rank-stars").removeClass("rank-stars-" + (data.newRankTier-1)).addClass("rank-stars-" + data.newRankTier);
            $("#equip-container-" + data.equipGroup + " .m-title-rank").text("" + data.newRankTitle);

            // Reset flashy animation element by clone+delete.
            var elem = $("#equip-container-" + data.equipGroup + " .mastery-change-flash");
            var copy = elem.clone(true);
            elem.before(copy);
            $("#equip-container-" + data.equipGroup + " .mastery-change-flash:last-child").remove();

            // Play reset animation.
            copy.css("animation-play-state", "running");

            // If rank is now maxed out, make it so we can't click on it anymore.
            if(data.isMaxRank)
            {
                //removal for armor needs to be a tad different than weapon
                if(data.equipType == "Armor"){
                    console.log($('.armor-upgrade-form'));
                    $('.armor-upgrade-form').remove();
                }else {
                    $("#equip-container-" + data.equipGroup + " .character-sheet-mastery-icon-container")
                        .removeAttr('href')
                        .removeAttr('data-xf-click')
                        .removeAttr('data-cache')
                        .changeElementType("div");
                }
            }
        }
    });

    // First element same as "data-xf-init" attribute
    XF.Element.register('upgrade-equip', 'XF.TS_CM_UpgradeEquip');
}
(jQuery, window, document);