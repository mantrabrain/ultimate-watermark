jQuery(document).ready(function ($) {

    var selectedElement;
    var watermarkFileUpload = {
        frame: function (el) {
            if (this._frameWatermark)
                return this._frameWatermark;

            this._frameWatermark = wp.media({
                title: ultimateWatermarkSettings.title,
                frame: ultimateWatermarkSettings.frame,
                button: ultimateWatermarkSettings.button,
                multiple: ultimateWatermarkSettings.multiple,
                library: {
                    type: 'image'
                }
            });

            this._frameWatermark.on('open', this.updateFrame).state('library').on('select', this.select);
            return this._frameWatermark;
        },
        select: function () {
            var _that = this;
            var attachment = this.frame.state().get('selection').first();

            var elementTd = $(selectedElement).closest('td');
            selectedElement = null;
            if ($.inArray(attachment.attributes.mime, ['image/gif', 'image/jpg', 'image/jpeg', 'image/png']) !== -1) {

                elementTd.find('input.attachment_id').val(attachment.attributes.id);

                elementTd.find('.preview-image').find('img').attr('src', attachment.attributes.url);

                elementTd.find('.preview-image').show();

                elementTd.find('.ultimate_watermark_remove_image_button').removeAttr('disabled');
                var img = new Image();
                img.src = attachment.attributes.url;
                img.onload = function () {
                    elementTd.find('.preview-image').find('p').html(ultimateWatermarkSettings.originalSize + ': ' + this.width + ' ' + ultimateWatermarkSettings.px + ' / ' + this.height + ' ' + ultimateWatermarkSettings.px);
                }

            } else {

                elementTd.find('.ultimate_watermark_remove_image_button').attr('disabled', 'true');
                elementTd.find('input.attachment_id').val(0);
                elementTd.find('.preview-image').hide();
                elementTd.find('.preview-image').find('p').html('<strong>' + ultimateWatermarkSettings.notAllowedImg + '</strong>');

            }
        },
        init: function () {
            var _that = this;
            $('body').on('click', '.ultimate_watermark_upload_image_button', function (e) {
                e.preventDefault();
                selectedElement = $(this);
                _that.frame().open();
            });
            _that.initSlider();
            _that.displayConditions();
        },
        initSlider: function () {
            var slider = $('.ultimate-watermark-range-slider');

            slider.each(function () {
                var slider_item = $(this);
                var handle = slider_item.find('.handle');
                var max = slider_item.data("max");
                var min = slider_item.data('min');
                var value = slider_item.data('value');
                var step = slider_item.data('step');
                slider_item.slider({
                    min: min,
                    max: max,
                    value: value,
                    step: step,
                    range: "min",
                    create: function () {
                        slider_item.closest('.slider-wrap').find('input').val($(this).slider("value"));
                        handle.text($(this).slider("value"));
                    },
                    slide: function (event, ui) {
                        handle.text(ui.value);
                        slider_item.closest('.slider-wrap').find('input').val(ui.value);
                    }
                });
            });


        },
        displayConditions: function () {
            $('body').on('change', '#ultimate_watermark_watermark_on', function () {
                var value = $(this).val();
                var el = $('[id^="ultimate_watermark_watermark_on_custom_post_type"]');
                var tr = el.closest('tr');
                if (value === 'selected_custom_post_types') {
                    tr.removeClass('ultimate-watermark-hide');
                } else {
                    tr.addClass('ultimate-watermark-hide');
                }
            });

            $('body').on('change', '#ultimate_watermark_watermark_size_type', function () {
                var value = $(this).val();
                var absWidthTr = $('#ultimate_watermark_absolute_width').closest('tr');
                var absHeightTr = $('#ultimate_watermark_absolute_height').closest('tr');
                var scaledTr = $('#ultimate_watermark_scaled_image_width').closest('tr');
                if (value === 'custom') {
                    absWidthTr.removeClass('ultimate-watermark-hide');
                    absHeightTr.removeClass('ultimate-watermark-hide');
                    scaledTr.addClass('ultimate-watermark-hide');
                } else if (value === 'scaled') {
                    absWidthTr.addClass('ultimate-watermark-hide');
                    absHeightTr.addClass('ultimate-watermark-hide');
                    scaledTr.removeClass('ultimate-watermark-hide');
                } else {
                    absWidthTr.addClass('ultimate-watermark-hide');
                    absHeightTr.addClass('ultimate-watermark-hide');
                    scaledTr.addClass('ultimate-watermark-hide');
                }
            });
        }
    };

    watermarkFileUpload.init();

    $(document).on('click', '.ultimate_watermark_remove_image_button', function (event) {
        $(this).attr('disabled', 'true');
        $(this).closest('td').find('input.attachment_id').val(0);
        $(this).closest('td').find('.preview-image').hide();
    });

});