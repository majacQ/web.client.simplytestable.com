$(document).ready(function() {
    $('.collapse-control-group .collapse-control').each(function () {
        var control = $(this);
        var detail = $(control.attr('data-target'));

        detail.on('show.bs.collapse', function (event) {
            var target = $(event.target);
            var control = $('[data-target=#' + target.attr('id') + ']');

            $('.collapse-control-group .collapse-control').each(function () {
                var currentControl = $(this);

                if (currentControl.attr('data-target') !== control.attr('data-target')) {
                    var currentDetail = $(currentControl.attr('data-target'));
                    if (currentDetail.is('.in')) {
                        currentDetail.collapse('hide');
                    }
                }
            });
        });

        detail.on('shown.bs.collapse', function (event) {
            var target = $(event.target);
            var control = $('[data-target=#' + target.attr('id') + ']');

            $('.fa', control).removeClass('fa-caret-up fa-caret-down').addClass('fa-caret-up');
        });

        detail.on('hidden.bs.collapse', function (event) {
            var target = $(event.target);
            var control = $('[data-target=#' + target.attr('id') + ']');

            $('.fa', control).removeClass('fa-caret-up fa-caret-down').addClass('fa-caret-down');
        });

        detail.on('hide.bs.collapse', function (event) {

        });
    });

    $('.buttons button[type=submit]').click(function () {
        var button = $(this);

        $('.fa', button).removeClass('fa-globe').removeClass('fa-circle-o').addClass('fa-spinner fa-spin');
        button.animate({
            'opacity':0.6
        });

        $('.buttons button').each(function () {
            if (button.val() !== $(this).val()) {
                $(this).animate({
                    'opacity':0.6
                }).attr('disabled', 'disabled');
            }
        });
    });

    if ($('body.no-test-types-selected').length === 0) {
        $('#website').stFormHelper().select();
    }

    $('body.no-test-types-selected .task-types').each(function () {
        var container = $(this);
        container.animate({ 'opacity':0.6}, function () {
            container.animate({'opacity': 1.0}, function () {
                container.animate({ 'opacity':0.6}, function () {
                    container.animate({'opacity': 1.0});
                });
            });
        });
    });

//    $('body.no-test-types-selected .task-type label').each(function () {
//        var label = $(this);
//        label.animate({
//            'opacity':0.6
//        }, function () {
//            label.animate({
//                'opacity':1
//            });
//        });
//    });
});