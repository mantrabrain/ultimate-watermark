// @var ultimateWatermarkListTable
(function ($) {
    var UltimateWatermarkListTable = {
        init: function () {
            this.settings = ultimateWatermarkListTable;
            this.bindEvents();

        },
        bindEvents: function () {

            var _that = this;
            $('body').on('change', 'input[name="ultimate_watermark_enable_this_watermark"]', function () {
                _that.save($(this));
            });

        },
        save: function (el) {
            var data = {
                action: this.settings.status_change_action,
                nonce: this.settings.status_change_nonce,
                status: el.is(':checked') ? 1 : 0,
                watermark_id: el.closest('.ultimate-watermark-column').data('watermark-id'),
            };
            $.ajax({
                url: this.settings.ajax_url,
                type: 'POST',
                data: data,
                beforeSend: function () {
                },
            });
        }
    };

    $(document).ready(function () {
        UltimateWatermarkListTable.init();
    });
}(jQuery));
