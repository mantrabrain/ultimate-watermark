// @var geoMapsAdminParams
(function ($) {
    var GeoMapsAdmin = {
        init: function () {
            this.settings = {};//(geoMapsAdminParams.options.settings);
            this.bindEvents();
            this.renderWatermarkPreview();
            this.image_upload_frame = '';
            this.initMediaUploader();
        },
        bindEvents: function () {
            var _that = this;
            $('body').on('change', '#ultimate_watermark_map_type', function () {
                _that.settings.map_type = _that.getMapType();
                _that.renderWatermarkPreview();
            });

            $('body').on('change', '#ultimate_watermark_osm_map_provider[name="ultimate_watermark_osm_map_provider"]', function () {
                _that.settings.osm_provider = $(this).val();
                if (_that.getMapType() === "open_street_map") {
                    _that.renderWatermarkPreview();
                }
            });
            $('body').on('click', '.ultimate-watermark-tab-nav-item', function (e) {
                e.preventDefault();
                var id = $(this).attr('id');
                $(this).closest('.ultimate-watermark-tabs').find('.ultimate-watermark-tab-nav .ultimate-watermark-tab-nav-item').removeClass('item-active');
                $(this).addClass('item-active');
                $(this).closest('.ultimate-watermark-tabs').find('.ultimate-watermark-tab-section').addClass('ultimate-watermark-hide');
                $(this).closest('.ultimate-watermark-tabs').find('.ultimate-watermark-tab-section.' + id + '_content').removeClass('ultimate-watermark-hide');
                var tab = $(this).attr('data-tab');
                $('[name="ultimate_watermark_meta_active_tab"]').val(tab);

            });
            $('body').on('click', '.ultimate-watermark-repeater-add', function (e) {
                e.preventDefault();
                var parent = $(this).closest('.ultimate-watermark-field-group');
                var id = parent.attr('id');
                var totalLength = parent.find('.ultimate-watermark-repeater-wrapper').find('.ultimate-watermark-repeater-item').length
                var item_id = ((totalLength + 1) - 1);
                var replace_to = '___' + id + '[0]';
                var replace_with = id + '[' + item_id + ']';
                var tmpl = parent.find('.ultimate-watermark-repeater-item.ultimate-watermark-repeater-hidden').html();
                var replacedTemplate = _that._replaceAll(tmpl, replace_to, replace_with);
                var newTemplate = $('<div class="ultimate-watermark-repeater-item" data-item-id="' + item_id + '">').append(replacedTemplate);
                parent.find('.ultimate-watermark-repeater-wrapper').append(newTemplate);
                _that.loadMapItem(item_id);
            })
            $('body').on('click', '.ultimate-watermark-repeater-title', function (e) {
                if (!$(e.target).hasClass('ultimate-watermark-repeater-remove')) {
                    var el = $(this).closest('.ultimate-watermark-repeater-item').find('.ultimate-watermark-repeater-content');
                    if (el.hasClass('ultimate-watermark-hide')) {
                        $(this).closest('.ultimate-watermark-repeater-item').find('.ultimate-watermark-repeater-header-icon').removeClass('dashicons dashicons-arrow-up-alt2').addClass('dashicons dashicons-arrow-down-alt2');

                        el.removeClass('ultimate-watermark-hide');
                        var marker_index = $(this).closest('.ultimate-watermark-repeater-item').attr('data-item-id');
                        _that.loadMapItem(marker_index);
                    } else {
                        $(this).closest('.ultimate-watermark-repeater-item').find('.ultimate-watermark-repeater-header-icon').removeClass('dashicons dashicons-arrow-down-alt2').addClass('dashicons dashicons-arrow-up-alt2');

                        el.addClass('ultimate-watermark-hide');

                    }
                }
            })
            $('body').on('keyup', 'input.ultimate-watermark-marker-title', function () {
                var val = $(this).val();
                $(this).closest('.ultimate-watermark-repeater-item').find('.ultimate-watermark-repeater-text').text(val);
            });
            $('body').on('click', '.ultimate-watermark-repeater-remove', function () {
                var min_item = parseInt($(this).attr('data-min-item'));
                var min_item_message = $(this).attr('data-min-item-message');
                var item_length = $(this).closest('.ultimate-watermark-repeater-wrapper').find('.ultimate-watermark-repeater-item').length;
                if (item_length <= min_item) {
                    alert(min_item_message);
                    return;
                }
                var confirm = $(this).attr('data-confirm');
                if (window.confirm((confirm))) {
                    var wrap = $(this).closest('.ultimate-watermark-repeater-wrapper');
                    $(this).closest('.ultimate-watermark-repeater-item').remove();
                    _that.reindexRepeaterItems(wrap);
                }
            });

            $('body').on('click', '.ultimate-watermark-location-search-button', function (e) {
                e.preventDefault();
                _that.mapLocationHtml($(this));
            });

            var locationKeyUpTimer = null;
            $('body').on('keyup', '.ultimate-watermark-marker-location', function (e) {
                var keyUp = $(this);
                clearTimeout(locationKeyUpTimer);
                locationKeyUpTimer = setTimeout(function () {
                    _that.mapLocationHtml(keyUp);
                }, 1000);
            });

            $('body').on('click', '.ultimate-watermark-location-list-item', function () {
                var lat = $(this).attr('data-lat');
                var lng = $(this).attr('data-lng');
                var title = $(this).text();
                var wrap = $(this).closest('.ultimate-watermark-repeater-item');

                $(this).closest('ul').remove();

                wrap.find('input.ultimate-watermark-marker-title').val(title).trigger('change');
                wrap.find('input.ultimate-watermark-marker-location').val(title);
                wrap.find('input.ultimate-watermark-marker-latitude').val(lat).trigger('change');
                wrap.find('input.ultimate-watermark-marker-longitude').val(lng).trigger('change');

            });
            $('body').on('change', '.ultimate-watermark-marker-latitude, .ultimate-watermark-marker-longitude, .ultimate-watermark-marker-title, .ultimate-watermark-marker-content', function () {
                var item_id = $(this).closest('.ultimate-watermark-repeater-item').attr('data-item-id');
                _that.loadMapItem(item_id, true);
            })

            $('body').on('input', '.ultimate-watermark-marker-latitude', function () {
                _that.validateLatLong($(this));
            });
            $('body').on('input', '.ultimate-watermark-marker-longitude', function () {
                _that.validateLatLong($(this));
            });
            $('body').on('click', '.ultimate-watermark-marker-center-position', function () {
                var isChecked = $(this).is(':checked');
                if (isChecked) {
                    var wrap = $(this).closest('.ultimate-watermark-repeater-wrapper');
                    wrap.find('.ultimate-watermark-marker-center-position').prop('checked', false);
                    $(this).prop('checked', true);
                    var index = $(this).closest('.ultimate-watermark-repeater-item').attr('data-item-id');
                    _that.settings.center_index = index;
                    _that.renderWatermarkPreview();
                }
            });
            $('body').on('change', '.ultimate-watermark-marker-image-id, .ultimate-watermark-marker-image-height, .ultimate-watermark-marker-image-width', function (e) {
                e.preventDefault();
            });
            $('body').on('click', '.ultimate-watermark-marker-scroll-wheel-zoom', function () {
                _that.settings.scroll_wheel_zoom = false;
                var isChecked = $(this).is(':checked');
                if (isChecked) {
                    _that.settings.scroll_wheel_zoom = true;
                }
                _that.renderWatermarkPreview();
            });

            $('body').on('click', '.ultimate-watermark-map-control-position', function () {
                var position = $(this).val();
                _that.settings.control_position = position;
                _that.settings.show_control = position !== 'hide';
                _that.renderWatermarkPreview();
            });
            $('body').on('change', '.ultimate-watermark-popup-show-on', function (e) {
                e.preventDefault();
                _that.settings.popup_show_on = $(this).val();
                _that.renderWatermarkPreview();
            });
        },
        validateLatLong: function (el) {
            var validNumber = new RegExp(/^\d*\.?\d*$/);
            if (!validNumber.test($(el).val())) {
                $(el).val(0);
            }

        },
        getMapType: function () {

            var map_type = $('#ultimate_watermark_map_type option:selected').val();

            if (map_type == '' || map_type == null) {

                $('#ultimate-watermark-map-osm-provider.postbox').removeClass('ultimate-watermark-hide');

                return 'google_map';
            }
            if (map_type === "open_street_map") {

                $('#ultimate-watermark-map-osm-provider.postbox').removeClass('ultimate-watermark-hide');

            } else {
                $('#ultimate-watermark-map-osm-provider.postbox').addClass('ultimate-watermark-hide');
            }
            return map_type;

        },
        reindexRepeaterItems: function (wrap) {
            var _that = this;
            var items = $(wrap).find('.ultimate-watermark-repeater-item');
            var index_id = 0;
            $.each(items, function () {

                var old_index = $(this).attr('data-item-id');


                if (old_index != index_id) {

                    var elements = $(this).find('[name*="[' + old_index + ']"], [id*="[' + old_index + ']"]');


                    $.each(elements, function () {
                        var element = $(this);

                        if ($(this).attr("name")) {
                            var name = element.attr('name');
                            var new_name = _that._replaceAll(name, old_index, index_id);
                            $(this).attr('name', new_name);
                        }
                        if ($(this).attr("id")) {
                            var id = element.attr('id');
                            var new_id = _that._replaceAll(id, old_index, index_id);
                            $(this).attr('id', new_id);
                        }
                    })

                }
                $(this).attr('data-item-id', index_id);
                index_id++;
            });
        },
        mapLocationHtml: function (el) {

            var fieldset = $(el).closest('.ultimate-watermark-fieldset');
            var value = fieldset.find(".ultimate-watermark-marker-location").val();
            if (value === '') {
                fieldset.find('.ultimate-watermark-location-lists').remove();
                return;
            }
            this.callLocationAPI(value, fieldset);


        },
        callLocationAPI: function (value, fieldset) {
            var location_search_url = 'https://nominatim.openstreetmap.org/search?q=' + value + '&format=json';

            fetch(location_search_url).then(function (response) {
                return response.json();
            }).then(function (response_data) {
                if (response_data.length > 0) {
                    var el = $('<ul class="ultimate-watermark-location-lists wp-map-block-modal-place-search__results"/>');

                    response_data.forEach(function (item, index) {
                        var title = item.display_name;
                        var lat = item.lat;
                        var lng = item.lon;
                        var li = $('<li class="ultimate-watermark-location-list-item" data-lng="' + lng + '" data-lat="' + lat + '"/>');
                        li.text(title);
                        el.append(li);
                    });
                    fieldset.find('.ultimate-watermark-location-lists').remove();
                    fieldset.append(el);
                }
            });
        },
        _replaceAll: function (str, toReplace, replaceWith) {
            return str ? str.split(toReplace).join(replaceWith) : '';
        },
        renderWatermarkPreview: function () {
            var _that = this;
            console.log(JSON.stringify(_that.settings));

        },
        loadMapItem: function (marker_index, force_remap = false) {

            var _that = this;


        },
        initMediaUploader: function () {
            var _this = this;
            $('body').on('click', '.ultimate-watermark-image-field-add', function (event) {
                event.preventDefault();
                _this.uploadWindow($(this), $(this).closest('.ultimate-watermark-image-field-wrap'));
            });
            $('body').on('click', '.ultimate-watermark-image-delete', function (event) {
                event.preventDefault();
                var imageField = $(this).closest('.ultimate-watermark-field-image');
                imageField.find('.image-wrapper').attr('data-url', '');
                imageField.find('.image-container, .field-container').addClass('ultimate-watermark-hide');
                imageField.find('.ultimate-watermark-image-field-add').removeClass('ultimate-watermark-hide');
                imageField.find('.ultimate-watermark-marker-image-id').val(0).trigger('change');

            });
        },
        uploadWindow: function (uploadBtn, wrapper) {

            var _this = this;
            if (this.image_upload_frame) this.image_upload_frame.close();

            this.image_upload_frame = wp.media.frames.file_frame = wp.media({
                title: uploadBtn.data('uploader-title'),
                button: {
                    text: uploadBtn.data('uploader-button-text'),
                },
                multiple: false
            });

            this.image_upload_frame.on('select', function () {

                var selection = _this.image_upload_frame.state().get('selection');
                var selected_list_node = wrapper.find('.image-container');
                var imageHtml = '';
                var attachment_id = 0;
                selection.map(function (attachment_object, i) {
                    var attachment = attachment_object.toJSON();
                    attachment_id = attachment.id;
                    var attachment_url = attachment.sizes.full.url;
                    imageHtml = $(_this.getImageElement(attachment_url));
                    var p = 'Original Size : ' + attachment.width + 'px / ' + attachment.height + 'px';
                    selected_list_node.closest('.ultimate-watermark-image-field-wrap').find('p.label').remove();
                    selected_list_node.closest('.ultimate-watermark-image-field-wrap').append('<p class="label">' + p + '</p>');

                });

                if (attachment_id > 0) {
                    wrapper.find('.image-container, .field-container').removeClass('ultimate-watermark-hide');
                    wrapper.find('.ultimate-watermark-image-field-add').addClass('ultimate-watermark-hide');
                    selected_list_node.find('.image-wrapper').remove();
                    selected_list_node.append(imageHtml);
                    wrapper.find('.field-container').find('.ultimate-watermark-marker-image-id').val(attachment_id).trigger('change');


                }
            });


            this.image_upload_frame.open();
        },
        getImageElement: function (src) {
            return '<div data-url="' + src + '" class="image-wrapper"><div class="image-content"><img src="' + src + '" alt=""><div class="image-overlay"><a class="ultimate-watermark-image-delete remove dashicons dashicons-trash"></a></div></div></div>';
        },
        getItemMarkerImage: function (item_index, new_item) {
            var wrap = $('.ultimate-watermark-repeater-wrapper').find('.ultimate-watermark-repeater-item[data-item-id="' + item_index + '"]').find('#ultimate_watermark_marker_item_image');
            var _that = this;
            var height = parseInt(wrap.find('.ultimate-watermark-marker-image-height').val());
            var width = parseInt(wrap.find('.ultimate-watermark-marker-image-width').val());
            var image_id = wrap.find('.ultimate-watermark-marker-image-id').val();
            if (image_id === '' || parseInt(image_id) < 1) {

                new_item.iconType = 'default';

                return _that.getMainMarker(new_item);

            }
            new_item.iconType = 'custom';
            new_item.customIconUrl = wrap.find('.image-wrapper').attr('data-url');
            new_item.customIconWidth = width < 1 ? 25 : width;
            new_item.customIconHeight = height < 1 ? 40 : height;

            return new_item;

        },
        getMainMarker: function (new_item) {
            var wrap = $('#ultimate_watermark_marker_image');
            var image_id = wrap.find('.ultimate-watermark-marker-image-id').val();

            if (image_id === '' || parseInt(image_id) < 1) {
                return new_item;

            }
            var height = parseInt(wrap.find('.ultimate-watermark-marker-image-height').val());
            var width = parseInt(wrap.find('.ultimate-watermark-marker-image-width').val());

            new_item.iconType = 'custom';
            new_item.customIconUrl = wrap.find('.image-wrapper').attr('data-url');
            new_item.customIconWidth = width < 1 ? 25 : width;
            new_item.customIconHeight = height < 1 ? 40 : height;

            return new_item;

        }

    };

    $(document).ready(function () {
        GeoMapsAdmin.init();
    });
}(jQuery));
