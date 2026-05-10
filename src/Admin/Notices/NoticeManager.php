<?php

namespace MantraBrain\UltimateWatermark\Admin\Notices;

/**
 * Admin Notice Manager
 *
 * Renders two onboarding/marketing notices with a "snooze once, then never"
 * lifecycle:
 *
 *   1. Review prompt (asks for a wordpress.org review)
 *      - Eligible from: install + 7 days
 *      - First close (X / "Maybe later"): re-show after 30 days
 *      - Second close: dismissed forever
 *      - Always dismissed forever if user clicks "Already reviewed"
 *
 *   2. Upgrade-to-Pro nudge (only when Pro is NOT active)
 *      - Eligible from: install + 10 days
 *      - First close: re-show after 20 days
 *      - Second close: dismissed forever
 *      - Hidden permanently if Pro is activated
 *
 * State is stored in a single option ('ultimate_watermark_notices') so the
 * footprint is one DB row regardless of how many notices we add later.
 *
 * @package UltimateWatermark
 * @since   2.0.9
 */
class NoticeManager
{
    private const OPTION_KEY        = 'ultimate_watermark_notices';
    private const NONCE_ACTION      = 'ultimate_watermark_notice_dismiss';
    private const AJAX_ACTION       = 'ultimate_watermark_dismiss_notice';
    private const REVIEW_URL        = 'https://wordpress.org/support/plugin/ultimate-watermark/reviews/?filter=5#new-post';
    private const UPGRADE_URL       = 'https://mantrabrain.com/plugins/ultimate-watermark#pricing';

    private const REVIEW_INITIAL_DAYS = 7;
    private const REVIEW_SNOOZE_DAYS  = 30;
    private const UPGRADE_INITIAL_DAYS = 10;
    private const UPGRADE_SNOOZE_DAYS  = 20;

    public function init(): void
    {
        // Stamp the install time on first run so the day counters have a baseline.
        $this->ensureInstallTime();

        add_action('admin_notices', [$this, 'renderNotices']);
        add_action('wp_ajax_' . self::AJAX_ACTION, [$this, 'handleDismiss']);
    }

    // =========================================================================
    // Render
    // =========================================================================

    public function renderNotices(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Don't pollute non-plugin screens with marketing.
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && isset($screen->id) && strpos($screen->id, 'ultimate-watermark') === false) {
            // Allow notices on the plugin's own pages OR on the dashboard / plugins page.
            $allowed_screens = ['dashboard', 'plugins'];
            if (!in_array($screen->id, $allowed_screens, true)) {
                return;
            }
        }

        if ($this->isReviewEligible()) {
            $this->renderReviewNotice();
        }

        if ($this->isUpgradeEligible()) {
            $this->renderUpgradeNotice();
        }
    }

    private function renderReviewNotice(): void
    {
        $nonce = wp_create_nonce(self::NONCE_ACTION);
        $review_url = esc_url(self::REVIEW_URL);
        ?>
        <div class="notice notice-info uw-onboarding-notice uw-onboarding-notice--review"
             data-uw-notice="review">
            <button type="button"
                    class="notice-dismiss uw-notice-dismiss"
                    data-uw-notice-id="review"
                    data-uw-notice-action="snooze"
                    data-nonce="<?php echo esc_attr($nonce); ?>">
                <span class="screen-reader-text"><?php esc_html_e('Dismiss this notice', 'ultimate-watermark'); ?></span>
            </button>
            <div class="uw-onboarding-notice__inner">
                <div class="uw-onboarding-notice__icon" aria-hidden="true">
                    <span class="dashicons dashicons-star-filled"></span>
                </div>
                <div class="uw-onboarding-notice__body">
                    <h3 class="uw-onboarding-notice__title">
                        <?php esc_html_e('Enjoying Ultimate Watermark?', 'ultimate-watermark'); ?>
                    </h3>
                    <p class="uw-onboarding-notice__copy">
                        <?php esc_html_e('You\'ve been using Ultimate Watermark for over a week — we\'d love it if you took a moment to leave a quick review on WordPress.org. It really helps the plugin grow.', 'ultimate-watermark'); ?>
                    </p>
                    <div class="uw-onboarding-notice__actions">
                        <a href="<?php echo $review_url; ?>"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="button button-primary uw-notice-cta"
                           data-uw-notice-id="review"
                           data-uw-notice-action="dismiss"
                           data-nonce="<?php echo esc_attr($nonce); ?>">
                            <span class="dashicons dashicons-external"></span>
                            <?php esc_html_e('Leave a Review', 'ultimate-watermark'); ?>
                        </a>
                        <button type="button"
                                class="button button-secondary uw-notice-action"
                                data-uw-notice-id="review"
                                data-uw-notice-action="dismiss"
                                data-nonce="<?php echo esc_attr($nonce); ?>">
                            <?php esc_html_e('I already left one', 'ultimate-watermark'); ?>
                        </button>
                        <button type="button"
                                class="button-link uw-notice-action uw-notice-action--quiet"
                                data-uw-notice-id="review"
                                data-uw-notice-action="snooze"
                                data-nonce="<?php echo esc_attr($nonce); ?>">
                            <?php esc_html_e('Maybe later', 'ultimate-watermark'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        $this->printNoticeAssetsOnce();
    }

    private function renderUpgradeNotice(): void
    {
        $nonce = wp_create_nonce(self::NONCE_ACTION);
        $upgrade_url = esc_url(self::UPGRADE_URL);
        ?>
        <div class="notice uw-onboarding-notice uw-onboarding-notice--upgrade"
             data-uw-notice="upgrade">
            <button type="button"
                    class="notice-dismiss uw-notice-dismiss"
                    data-uw-notice-id="upgrade"
                    data-uw-notice-action="snooze"
                    data-nonce="<?php echo esc_attr($nonce); ?>">
                <span class="screen-reader-text"><?php esc_html_e('Dismiss this notice', 'ultimate-watermark'); ?></span>
            </button>
            <div class="uw-onboarding-notice__inner">
                <div class="uw-onboarding-notice__icon uw-onboarding-notice__icon--gradient" aria-hidden="true">
                    <span class="dashicons dashicons-superhero-alt"></span>
                </div>
                <div class="uw-onboarding-notice__body">
                    <h3 class="uw-onboarding-notice__title">
                        <?php esc_html_e('Unlock more with Ultimate Watermark Pro', 'ultimate-watermark'); ?>
                    </h3>
                    <p class="uw-onboarding-notice__copy">
                        <?php esc_html_e('Add unlimited watermarks, dynamic placeholders (EXIF, dates, user data), WooCommerce per-product & per-category overrides, on-the-fly display watermarks, batch operations, and Google Fonts.', 'ultimate-watermark'); ?>
                    </p>
                    <ul class="uw-onboarding-notice__features">
                        <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Unlimited watermark templates', 'ultimate-watermark'); ?></li>
                        <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('WooCommerce product & category support', 'ultimate-watermark'); ?></li>
                        <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Dynamic content & Google Fonts', 'ultimate-watermark'); ?></li>
                    </ul>
                    <div class="uw-onboarding-notice__actions">
                        <a href="<?php echo $upgrade_url; ?>"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="button button-primary uw-notice-cta uw-notice-cta--upgrade"
                           data-uw-notice-id="upgrade"
                           data-uw-notice-action="snooze"
                           data-nonce="<?php echo esc_attr($nonce); ?>">
                            <?php esc_html_e('Upgrade to Pro', 'ultimate-watermark'); ?>
                            <span class="dashicons dashicons-arrow-right-alt"></span>
                        </a>
                        <button type="button"
                                class="button button-secondary uw-notice-action"
                                data-uw-notice-id="upgrade"
                                data-uw-notice-action="dismiss"
                                data-nonce="<?php echo esc_attr($nonce); ?>">
                            <?php esc_html_e('No thanks', 'ultimate-watermark'); ?>
                        </button>
                        <button type="button"
                                class="button-link uw-notice-action uw-notice-action--quiet"
                                data-uw-notice-id="upgrade"
                                data-uw-notice-action="snooze"
                                data-nonce="<?php echo esc_attr($nonce); ?>">
                            <?php esc_html_e('Remind me later', 'ultimate-watermark'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        $this->printNoticeAssetsOnce();
    }

    /**
     * Print the small CSS + JS payload once per page load. Inline so the
     * notice always works even if the admin page-specific assets failed.
     */
    private function printNoticeAssetsOnce(): void
    {
        static $printed = false;
        if ($printed) {
            return;
        }
        $printed = true;
        ?>
        <style>
            .uw-onboarding-notice {
                position: relative;
                padding: 0;
                border: 1px solid #d1d5db;
                border-left-width: 4px;
                border-left-color: #f59e0b;
                background: #fff;
                box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
                margin: 16px 0;
                border-radius: 8px;
                overflow: hidden;
            }
            .uw-onboarding-notice--review { border-left-color: #f59e0b; }
            /* Upgrade nudge uses orange too — same Pro identity. */
            .uw-onboarding-notice--upgrade { border-left-color: #f59e0b; }

            .uw-onboarding-notice .uw-notice-dismiss {
                top: 12px;
                right: 12px;
            }

            .uw-onboarding-notice__inner {
                display: flex;
                gap: 16px;
                align-items: flex-start;
                padding: 18px 44px 18px 20px;
            }

            .uw-onboarding-notice__icon {
                flex-shrink: 0;
                width: 44px;
                height: 44px;
                border-radius: 10px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                background: #fef3c7;
                color: #d97706;
            }
            .uw-onboarding-notice__icon .dashicons {
                font-size: 22px;
                width: 22px;
                height: 22px;
            }
            .uw-onboarding-notice__icon--gradient {
                background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%);
                color: #ffffff;
                box-shadow: 0 4px 12px rgba(245, 158, 11, 0.32);
            }

            .uw-onboarding-notice__body { flex: 1; min-width: 0; }

            .uw-onboarding-notice__title {
                margin: 0 0 6px;
                font-size: 16px;
                font-weight: 700;
                color: #111827;
                letter-spacing: -0.005em;
            }

            .uw-onboarding-notice__copy {
                margin: 0 0 12px;
                color: #4b5563;
                font-size: 13px;
                line-height: 1.5;
                max-width: 720px;
            }

            .uw-onboarding-notice__features {
                list-style: none;
                margin: 0 0 14px;
                padding: 0;
                display: flex;
                flex-wrap: wrap;
                gap: 8px 18px;
                font-size: 12px;
                color: #374151;
            }
            .uw-onboarding-notice__features li {
                margin: 0;
                display: inline-flex;
                align-items: center;
                gap: 4px;
            }
            .uw-onboarding-notice__features .dashicons {
                color: #059669;
                font-size: 16px;
                width: 16px;
                height: 16px;
            }

            .uw-onboarding-notice__actions {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 8px 12px;
            }
            .uw-onboarding-notice__actions .button {
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }
            .uw-onboarding-notice__actions .dashicons {
                font-size: 16px;
                width: 16px;
                height: 16px;
            }
            /* Orange — matches the unified .uw-pro-cta look from admin.css.
               Inlined here so the notice still reads correctly even if the
               main stylesheet hasn't loaded yet. */
            .uw-notice-cta--upgrade {
                background: #f59e0b !important;
                border-color: #f59e0b !important;
                color: #fff !important;
                box-shadow: 0 1px 3px rgba(245, 158, 11, 0.32);
            }
            .uw-notice-cta--upgrade:hover {
                background: #d97706 !important;
                border-color: #d97706 !important;
            }
            .uw-notice-action--quiet {
                color: #6b7280 !important;
                text-decoration: none !important;
                padding: 4px 6px;
            }
            .uw-notice-action--quiet:hover { color: #111827 !important; }

            @media (max-width: 600px) {
                .uw-onboarding-notice__inner { flex-direction: column; padding: 16px 16px 16px 16px; }
                .uw-onboarding-notice__actions .button { flex: 1; justify-content: center; }
            }
        </style>
        <script>
            (function () {
                if (window.uwOnboardingNoticesBound) return;
                window.uwOnboardingNoticesBound = true;

                document.addEventListener('click', function (e) {
                    var trigger = e.target.closest('[data-uw-notice-id]');
                    if (!trigger) return;
                    var noticeId = trigger.getAttribute('data-uw-notice-id');
                    var action   = trigger.getAttribute('data-uw-notice-action') || 'snooze';
                    var nonce    = trigger.getAttribute('data-nonce') || '';

                    var notice = trigger.closest('.uw-onboarding-notice');
                    if (notice) {
                        notice.style.transition = 'opacity 0.18s ease, max-height 0.25s ease, margin 0.25s ease, padding 0.25s ease';
                        notice.style.maxHeight  = notice.offsetHeight + 'px';
                        // Force reflow so the next frame animates from the measured height.
                        // eslint-disable-next-line no-unused-expressions
                        notice.offsetHeight;
                        notice.style.opacity     = '0';
                        notice.style.maxHeight   = '0';
                        notice.style.marginTop   = '0';
                        notice.style.marginBottom = '0';
                        notice.style.paddingTop   = '0';
                        notice.style.paddingBottom = '0';
                        setTimeout(function () { notice.remove(); }, 250);
                    }

                    var ajaxUrl = (window.ajaxurl) ? window.ajaxurl : '/wp-admin/admin-ajax.php';
                    var body = new URLSearchParams();
                    body.append('action', '<?php echo esc_js(self::AJAX_ACTION); ?>');
                    body.append('notice', noticeId);
                    body.append('mode', action);
                    body.append('_wpnonce', nonce);

                    fetch(ajaxUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                        body: body.toString()
                    }).catch(function () { /* fail silently — notice already hidden */ });
                });
            })();
        </script>
        <?php
    }

    // =========================================================================
    // AJAX dismiss handler
    // =========================================================================

    public function handleDismiss(): void
    {
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            wp_send_json_error(['message' => 'Invalid nonce.'], 403);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Forbidden.'], 403);
        }

        $notice = isset($_POST['notice']) ? sanitize_key(wp_unslash($_POST['notice'])) : '';
        $mode   = isset($_POST['mode'])   ? sanitize_key(wp_unslash($_POST['mode']))   : 'snooze';

        if (!in_array($notice, ['review', 'upgrade'], true)) {
            wp_send_json_error(['message' => 'Unknown notice.'], 400);
        }

        $state = $this->getState();

        if ($mode === 'dismiss') {
            // Permanent dismissal regardless of prior state.
            $state[$notice] = ['state' => 'dismissed', 'snoozed_until' => 0];
        } else {
            // Snooze. If already snoozed once, second snooze becomes permanent.
            $current = $state[$notice]['state'] ?? null;
            if ($current === 'snoozed') {
                $state[$notice] = ['state' => 'dismissed', 'snoozed_until' => 0];
            } else {
                $days = ($notice === 'review') ? self::REVIEW_SNOOZE_DAYS : self::UPGRADE_SNOOZE_DAYS;
                $state[$notice] = [
                    'state'         => 'snoozed',
                    'snoozed_until' => time() + ($days * DAY_IN_SECONDS),
                ];
            }
        }

        $this->saveState($state);
        wp_send_json_success(['notice' => $notice, 'state' => $state[$notice]]);
    }

    // =========================================================================
    // Eligibility checks
    // =========================================================================

    private function isReviewEligible(): bool
    {
        $state = $this->getState();
        if (($state['review']['state'] ?? null) === 'dismissed') {
            return false;
        }
        if (time() < $this->getInstallTime() + (self::REVIEW_INITIAL_DAYS * DAY_IN_SECONDS)) {
            return false;
        }
        $snoozed_until = (int) ($state['review']['snoozed_until'] ?? 0);
        if ($snoozed_until > 0 && time() < $snoozed_until) {
            return false;
        }
        return true;
    }

    private function isUpgradeEligible(): bool
    {
        // Pro is active — no upsell.
        if (defined('ULTIMATE_WATERMARK_PRO_VERSION')) {
            return false;
        }
        $state = $this->getState();
        if (($state['upgrade']['state'] ?? null) === 'dismissed') {
            return false;
        }
        if (time() < $this->getInstallTime() + (self::UPGRADE_INITIAL_DAYS * DAY_IN_SECONDS)) {
            return false;
        }
        $snoozed_until = (int) ($state['upgrade']['snoozed_until'] ?? 0);
        if ($snoozed_until > 0 && time() < $snoozed_until) {
            return false;
        }
        return true;
    }

    // =========================================================================
    // State persistence
    // =========================================================================

    private function getState(): array
    {
        $stored = get_option(self::OPTION_KEY, []);
        if (!is_array($stored)) {
            $stored = [];
        }
        $defaults = [
            'install_time' => 0,
            'review'       => ['state' => null, 'snoozed_until' => 0],
            'upgrade'      => ['state' => null, 'snoozed_until' => 0],
        ];
        // Shallow merge — preserve nested defaults for missing keys.
        $merged = array_replace_recursive($defaults, $stored);
        return $merged;
    }

    private function saveState(array $state): void
    {
        update_option(self::OPTION_KEY, $state, false);
    }

    private function getInstallTime(): int
    {
        $state = $this->getState();
        return (int) ($state['install_time'] ?? 0);
    }

    private function ensureInstallTime(): void
    {
        $state = $this->getState();
        if (empty($state['install_time'])) {
            $state['install_time'] = time();
            $this->saveState($state);
        }
    }

    /**
     * Reset notice state (e.g. on plugin deactivation if desired). Public so
     * other modules can wipe state during uninstall.
     */
    public static function resetState(): void
    {
        delete_option(self::OPTION_KEY);
    }
}
