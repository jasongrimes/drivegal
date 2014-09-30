$(function() {
    $('.paginator-select-page').on('change', function() {
        document.location = $(this).val();
    });
    $('.paginator-select-page')
        .on('focus', function() {
            if (/(iPad|iPhone|iPod)/g.test(navigator.userAgent)) {
                $(this).css('font-size', '16px');
            }
        })
        .on('blur', function() {
            if (/(iPad|iPhone|iPod)/g.test(navigator.userAgent)) {
                $(this).css('font-size', '');
            }
        })
    ;
});