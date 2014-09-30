$(function() {
    $('.paginator-select-page').on('change', function() {
        document.location = $(this).val();
    });
    $('.paginator-select-page')
        .on('focus', function() {
            $(this).css('font-size', '16px');
        })
        .on('blur', function() {
            $(this).css('font-size', '');
        })
    ;
});