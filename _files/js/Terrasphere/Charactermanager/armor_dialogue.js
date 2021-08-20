$(document).ready(function(){
    let armorPopup = $('#armor-popup').hide();
    let armor = $('.armor')[0];
    let armorButtons = $('.armor-button')
    armor.addEventListener('click', showArmorChooseDialog);

    $.each(armorButtons,(i,v) => {
        v.addEventListener('click', hideArmorChooseDialog);
    })
    document.addEventListener('click', (e) => {
        if(!e.target.closest('.armor')){
            armorPopup.hide();
        }
    })
    function showArmorChooseDialog(){
        armorPopup.show();
    }

    function hideArmorChooseDialog(e){
        e.stopPropagation()
        armorPopup.hide();
    }
})
