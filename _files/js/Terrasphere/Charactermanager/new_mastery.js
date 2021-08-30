!function($, window, document, _undefined)
{
    "use strict";

    XF.TS_CM_NewMastery = XF.Element.newHandler({

        options: {},

        init: function()
        {
            var $form = this.$target;

            $form.on('ajax-submit:response', $.proxy(this, 'ajaxResponse'));
        },

        ajaxResponse: function(e, data)
        {
            // Are these necessary when we're using them immediately in this function? I say no. --Luma

            //$("input[name='page_masterySlotIndex']").val(data.masterySlotIndex);
            //$("input[name='page_newMasteryName']").val(data.newMasteryName);
            //$("input[name='page_newMasteryIconURL']").val(data.newMasteryIconURL);

            // Hide the 'select new' element which represents the empty slot.
            $("#mastery-container-" + data.masterySlotIndex + " .character-sheet-mastery-empty").hide();

            // Set up the new mastery's display...
            $("#mastery-container-" + data.masterySlotIndex + " .character-sheet-mastery-icon-container").css('background-color', data.newMasteryColor);
            $("#mastery-container-" + data.masterySlotIndex + " .character-sheet-mastery-icon").attr('src', data.newMasteryIconURL);
            $("#mastery-container-" + data.masterySlotIndex + " .character-sheet-mastery-title").html(data.newMasteryName +'<br><span class="m-title-rank">'+data.rankTitle+'</span>');
            $("#mastery-container-" + data.masterySlotIndex + " .rank-").removeClass('rank-').addClass("rank-1");
            $("#mastery-container-" + data.masterySlotIndex + " .rank-stars").removeClass('rank-stars-').addClass("rank-stars-1");

            // ...and show it.
            $("#mastery-container-" + data.masterySlotIndex + " .character-sheet-mastery").show();

            // Reset flashy animation element by clone+delete (necessary if it already flashed for slot unlock).
            var elem = $("#equip-container-" + data.equipId + " .mastery-change-flash");
            var copy = elem.clone(true);
            elem.before(copy);
            $("#equip-container-" + data.equipId + " .mastery-change-flash:last-child").remove();

            // ...and play it.
            $("#mastery-container-" + data.masterySlotIndex + " .mastery-change-flash").css("animation-play-state", "running");
        }
    });

    // First element same as "data-xf-init" attribute
    XF.Element.register('new-mastery', 'XF.TS_CM_NewMastery');
}
(jQuery, window, document);