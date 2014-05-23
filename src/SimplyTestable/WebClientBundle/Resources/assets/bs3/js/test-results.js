$(document).ready(function() {
    $('.collapse-control').each(function () {
        var control = $(this);
        var detail = $(control.attr('data-target'));

        detail.on('shown.bs.collapse', function (event) {
            var target = $(event.target);
            var control = $('[data-target=#' + target.attr('id') + ']');

            $('.fa', control).remove();
            control.append(' <i class="fa fa-caret-up"></i>');

            event.preventDefault();
        });

        detail.on('hidden.bs.collapse', function (event) {
            var target = $(event.target);
            var control = $('[data-target=#' + target.attr('id') + ']');

            $('.fa', control).remove();
            control.append(' <i class="fa fa-caret-down"></i>');

            event.preventDefault();
        });
    });

    var maximumProseHeight = 0;

    $('.summary-prose-section').each(function () {
        var maximumBadgeWidth = 0;
        var section = $(this);

        if (section.height() > maximumProseHeight) {
            maximumProseHeight = section.height();
        }

        $('.badge', section).each(function () {
            var badge = $(this);
            if (badge.width() > maximumBadgeWidth) {
                maximumBadgeWidth = badge.width();
            }
        });

        $('.badge', section).each(function () {
            $(this).width(maximumBadgeWidth);
        });

    });

    $('.summary-prose-section').each(function () {
        $(this).height(maximumProseHeight);
    });


//    $('a[data-target=#test-list]').click(function () {
//        var target = $('#test-list');
//
//        $.scrollTo(target, {
//            'offset':-50
//        });
//
//        window.location.hash = target.attr('id');
//
//        return false;
//    });

    if ($(window.location.hash).length) {
        var target = $(window.location.hash);

        $.scrollTo(target, {
            'offset':-100
        });
    }
});