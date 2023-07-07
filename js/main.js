jQuery(document).ready(function($) {
    var dateElement = $('p.form-field.expiration_datetime_field');
    
    // Show/hide expiration_datetime based on expiration_checkbox state    
    $('#expiration_checkbox').on('change', function() {
        if ($(this).is(':checked')) {
            dateElement.show();
        } else {
            dateElement.hide();
        }
    });

    // Initialize visibility on page load
    if ($('#expiration_checkbox').is(':checked')) {
        dateElement.show();
    } else {
        dateElement.hide();
    }
});
