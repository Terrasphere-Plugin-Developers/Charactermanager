$(document).ready(function(){
    let accessoryPopup = $('#accessory-popup').hide();
    let accessory = $('.accessory')[0];
    let accessoryButtons = $('.accessory-button')
    accessory.addEventListener('click', showAccessoryChooseDialog);

    $.each(accessoryButtons,(i,v) => {
        v.addEventListener('click', hideAccessoryChooseDialog);
    })
    document.addEventListener('click', (e) => {
        if(!e.target.closest('.accessory')){
            accessoryPopup.hide();
        }
    })

    function showAccessoryChooseDialog(e){
        //this check is so when you click the button and it hides the dialog again that it doesn't show again asap
        //can't use propogationStop in hideAccessoryChooseDialog because it screws with the overlay ajax xenforo shenanigans
        if(e.target.classList.contains('accessory')){
            accessoryPopup.show();
        }
    }

    function hideAccessoryChooseDialog(){
        accessoryPopup.hide();
    }
})
