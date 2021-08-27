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

    function showArmorChooseDialog(e){
        //this check is so when you click the button and it hides the dialog again that it doesn't show again asap
        //can't use propogationStop in hideArmorChooseDialog because it screws with the overlay ajax xenforo shenanigans
        if(e.target.classList.contains('armor')){
            armorPopup.show();
        }
    }

    function hideArmorChooseDialog(){
        armorPopup.hide();
    }
})
