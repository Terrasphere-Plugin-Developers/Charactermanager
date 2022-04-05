$(document).ready(function(){

    currentVal = Number($('#ineedthisforthejavascript')[0].innerText);
    const offset = Math.max(Math.min(currentVal * 0.5, 2500), 0);
    $("#prestige-progression-line").css('transform', 'TranslateX(-'+offset+"px)");

    $("#prestige-val").bind("change", function(evt) {
        currentVal = Number($('#ineedthisforthejavascript')[0].innerText);
        const offset = Math.max(Math.min((Number(evt.target.value) + currentVal) * 0.5, 2500), 0);
        $("#prestige-progression-line").css('transform', 'TranslateX(-'+offset+"px)");
    });

    $("#prestige-val").bind("keyup", function(evt) {
        currentVal = Number($('#ineedthisforthejavascript')[0].innerText);
        const offset = Math.max(Math.min((Number(evt.target.value) + currentVal) * 0.5, 2500), 0);
        $("#prestige-progression-line").css('transform', 'TranslateX(-'+offset+"px)");
    });
});