$(function() {
    $('.paginator-select-page').on('change', function() {
        document.location = $(this).val();
    });
});