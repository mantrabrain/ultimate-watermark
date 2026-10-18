/**
 * Ultimate Watermark - Media Library grid view.
 *
 * WordPress' grid view (the default Media Library view) has no bulk-action
 * hook, so this adds:
 *  - a watermark dropdown + Apply button to the "Bulk select" toolbar;
 *  - Apply / Remove controls to the attachment details popup.
 * Both call the same AJAX endpoints as the list view (capability-checked per image).
 */
/* i18n: falls back to the original text when wp.i18n is unavailable. */
window.wp = window.wp || {};
window.wp.i18n = window.wp.i18n || {
    __: function (text) { return text; },
    _n: function (single, plural, number) { return number === 1 ? single : plural; },
    sprintf: function (format) {
        var args = Array.prototype.slice.call(arguments, 1), i = 0;
        return String(format).replace(/%(\d+\$)?[sd]/g, function (match, position) {
            return String(position ? args[parseInt(position, 10) - 1] : args[i++]);
        });
    }
};

(function ($) {
    'use strict';

    var __ = wp.i18n.__;
    var config = window.ultimate_watermark_media || {};
    var watermarks = config.manual_watermarks || [];

    if (!config.ajax_url || !config.nonce) {
        return;
    }

    function optionsHtml() {
        var html = '<option value="">' + escapeHtml(__('Watermark…', 'ultimate-watermark')) + '</option>';
        watermarks.forEach(function (w) {
            /* translators: %s: watermark name */
            html += '<option value="' + w.id + '">' + escapeHtml(wp.i18n.sprintf(__('Apply: %s', 'ultimate-watermark'), w.name)) + '</option>';
        });
        html += '<option value="remove">' + escapeHtml(__('Remove watermark', 'ultimate-watermark')) + '</option>';
        return html;
    }

    function escapeHtml(text) {
        return $('<div>').text(text == null ? '' : String(text)).html();
    }

    function notify(type, message) {
        if (window.UWNotifications && typeof window.UWNotifications[type] === 'function') {
            window.UWNotifications[type](type === 'error' ? __('Error', 'ultimate-watermark') : __('Done', 'ultimate-watermark'), message);
        } else {
            window.alert(message);
        }
    }

    function confirmAction(title, message) {
        if (window.UWNotifications && typeof window.UWNotifications.confirm === 'function') {
            return window.UWNotifications.confirm({
                title: title,
                message: message,
                type: 'warning',
                confirmText: title,
                cancelText: __('Cancel', 'ultimate-watermark')
            }).then(function (action) { return action === 'confirm'; });
        }
        return $.Deferred().resolve(window.confirm(message)).promise();
    }

    /**
     * Apply a watermark (id) or remove watermarks ('remove') on the given images.
     */
    function run(action, ids) {
        var removing = action === 'remove';
        var data = {
            action: removing ? 'ultimate_watermark_remove' : 'ultimate_watermark_apply_manual',
            nonce: config.nonce,
            attachment_ids: ids
        };
        if (!removing) {
            data.watermark_id = action;
        }
        return $.post(config.ajax_url, data);
    }

    function messageFrom(response, fallback) {
        return (response && response.data && response.data.message) || fallback;
    }

    // ---------------------------------------------------------------------
    // Bulk select toolbar
    // ---------------------------------------------------------------------

    function selectedIds() {
        var frame = wp.media && wp.media.frame;
        var state = frame && frame.state && frame.state();
        var selection = state && state.get && state.get('selection');
        return selection ? selection.pluck('id') : [];
    }

    function injectBulkControls() {
        var $delete = $('.media-toolbar .delete-selected-button:visible');
        var $existing = $('.uw-grid-bulk');
        if ($existing.length) {
            // Only while "Bulk select" is active (WordPress hides its Delete button otherwise).
            $existing.toggle($delete.length > 0);
            return;
        }
        if (!$delete.length) {
            return;
        }

        var $wrap = $('<span class="uw-grid-bulk" style="display:inline-flex;gap:6px;margin-left:8px;vertical-align:middle;"></span>');
        var $select = $('<select class="uw-grid-bulk-action" aria-label="' + escapeHtml(__('Watermark action', 'ultimate-watermark')) + '"></select>').html(optionsHtml());
        var $apply = $('<button type="button" class="button media-button uw-grid-bulk-apply"></button>').text(__('Apply', 'ultimate-watermark'));
        $wrap.append($select, $apply);
        $delete.after($wrap);

        $apply.on('click', function () {
            var action = $select.val();
            var ids = selectedIds();
            if (!action) {
                notify('error', __('Choose a watermark action first.', 'ultimate-watermark'));
                return;
            }
            if (!ids.length) {
                notify('error', __('Please select at least one media item.', 'ultimate-watermark'));
                return;
            }

            var name = $select.find('option:selected').text();
            var title = action === 'remove' ? __('Remove Watermark', 'ultimate-watermark') : __('Apply Watermark', 'ultimate-watermark');
            var message = wp.i18n.sprintf(
                /* translators: 1: chosen action, 2: number of images */
                wp.i18n._n('%1$s on %2$d selected image?', '%1$s on %2$d selected images?', ids.length, 'ultimate-watermark'),
                name,
                ids.length
            );

            confirmAction(title, message).then(function (ok) {
                if (!ok) {
                    return;
                }
                $apply.prop('disabled', true).text(__('Working…', 'ultimate-watermark'));
                run(action, ids)
                    .done(function (response) {
                        if (response && response.success) {
                            notify('success', messageFrom(response, __('Done.', 'ultimate-watermark')));
                            // Thumbnails keep their URLs: reload so the new files show.
                            window.setTimeout(function () { window.location.reload(); }, 1200);
                        } else {
                            notify('error', messageFrom(response, __('Something went wrong. Please try again.', 'ultimate-watermark')));
                        }
                    })
                    .fail(function () {
                        notify('error', __('Something went wrong. Please try again.', 'ultimate-watermark'));
                    })
                    .always(function () {
                        $apply.prop('disabled', false).text(__('Apply', 'ultimate-watermark'));
                    });
            });
        });
    }

    // ---------------------------------------------------------------------
    // Attachment details popup
    // ---------------------------------------------------------------------

    function enhanceDetails() {
        $('.uw-attachment-watermark').each(function () {
            var $box = $(this);
            var $actions = $box.find('.uw-attachment-actions');
            if (!$actions.length || $actions.data('uwReady')) {
                return;
            }
            $actions.data('uwReady', true).prop('hidden', false);
            var $select = $('<select class="uw-attachment-action" aria-label="' + escapeHtml(__('Watermark action', 'ultimate-watermark')) + '"></select>').html(optionsHtml());
            var $apply = $('<button type="button" class="button button-small uw-attachment-apply" style="margin-left:6px;"></button>').text(__('Apply', 'ultimate-watermark'));
            $actions.append($select, $apply);

            $apply.on('click', function () {
                var action = $select.val();
                if (!action) {
                    return;
                }
                $apply.prop('disabled', true).text(__('Working…', 'ultimate-watermark'));
                run(action, [parseInt($box.data('attachment-id'), 10)])
                    .done(function (response) {
                        var ok = response && response.success;
                        notify(ok ? 'success' : 'error', messageFrom(response, ok ? __('Done.', 'ultimate-watermark') : __('Something went wrong. Please try again.', 'ultimate-watermark')));
                        if (ok) {
                            // Show the changed file instead of the cached preview.
                            $('.media-modal img.details-image, .media-modal .attachment-details .thumbnail img').each(function () {
                                var src = String($(this).attr('src') || '').split('?')[0];
                                if (src) {
                                    $(this).attr('src', src + '?uw=' + Date.now());
                                }
                            });
                            $box.find('.uw-attachment-status').text(action === 'remove'
                                ? __('Not watermarked.', 'ultimate-watermark')
                                : /* translators: %s: comma-separated watermark names */ wp.i18n.sprintf(__('Watermarked with: %s', 'ultimate-watermark'), $select.find('option:selected').text().replace(/^[^:]*:\s*/, '')));
                        }
                    })
                    .fail(function () {
                        notify('error', __('Something went wrong. Please try again.', 'ultimate-watermark'));
                    })
                    .always(function () {
                        $apply.prop('disabled', false).text(__('Apply', 'ultimate-watermark'));
                    });
            });
        });
    }

    // The media views render asynchronously: watch for the toolbar and popups.
    var pending = false;
    new MutationObserver(function () {
        if (pending) {
            return;
        }
        pending = true;
        window.requestAnimationFrame(function () {
            pending = false;
            injectBulkControls();
            enhanceDetails();
        });
    }).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'style'] });
})(jQuery);
