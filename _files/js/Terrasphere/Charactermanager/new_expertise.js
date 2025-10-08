!function($, window, document, _undefined)
{
    "use strict";

    XF.TS_CM_NewExpertise = XF.Element.newHandler({

        options: {},

        init: function()
        {
            var $form = this.$target;

            $form.on('ajax-submit:response', $.proxy(this, 'ajaxResponse'));
        },

        ajaxResponse: function(e, data)
        {
            // Hide the 'select new' element which represents the empty slot.
            $("#expertise-container-" + data.expertiseSlotIndex + " .character-sheet-expertise-empty").hide();

            // Set up the new expertise's display...
            $("#expertise-container-" + data.expertiseSlotIndex + " .character-sheet-expertise-icon-container").css('background-color', data.newExpertiseColor);
            $("#expertise-container-" + data.expertiseSlotIndex + " .character-sheet-expertise-icon").attr('src', data.newExpertiseIconURL);
            $("#expertise-container-" + data.expertiseSlotIndex + " .character-sheet-expertise-title").html(data.newExpertiseName +'<br><span class="m-title-rank">'+data.rankTitle+'</span>');
            $("#expertise-container-" + data.expertiseSlotIndex + " .rank-").removeClass('rank-').addClass("rank-1");
            $("#expertise-container-" + data.expertiseSlotIndex + " .rank-stars").removeClass('rank-stars-').addClass("rank-stars-1");

            // ...and show it.
            $("#expertise-container-" + data.expertiseSlotIndex + " .character-sheet-expertise").show();

            // Reset flashy animation element by clone+delete (necessary if it already flashed for slot unlock).
            var elem = $("#equip-container-" + data.equipId + " .expertise-change-flash");
            var copy = elem.clone(true);
            elem.before(copy);
            $("#equip-container-" + data.equipId + " .expertise-change-flash:last-child").remove();

            // ...and play it.
            $("#expertise-container-" + data.expertiseSlotIndex + " .expertise-change-flash").css("animation-play-state", "running");
        }
    });

    // First element same as "data-xf-init" attribute
    XF.Element.register('new-expertise', 'XF.TS_CM_NewExpertise');
}
(jQuery, window, document);