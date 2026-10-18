=== Ultimate Watermark - Image Watermark, Image Protection & Bulk Watermarking ===
Contributors: mantrabrain
Donate link: https://mantrabrain.com/plugins/ultimate-watermark
Tags: watermark, image watermark, image protection, woocommerce, photography
Requires at least: 5.9
Tested up to: 7.1
Stable tag: 2.2.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Requires PHP: 7.4

Watermark images with text or a logo: automatic, bulk or tiled. WooCommerce & Dokan ready, with backups and one-click restore of every original.

== Description ==

**Ultimate Watermark** adds a copyright text or logo watermark to your WordPress images: automatically on upload, in bulk for the images you already have, or one image at a time. Every original is backed up first, so you can restore any image in one click.

It is built for photographers, bloggers, WooCommerce stores and multi-vendor marketplaces (Dokan) that need real image protection without external services: everything runs on your own server with the same Imagick / GD libraries WordPress already uses.

* 🔗 [Plugin page](https://mantrabrain.com/plugins/ultimate-watermark)
* 🚀 [Try it free](https://try.new/plugins/ultimate-watermark)
* 📚 [Documentation](https://ultimate-watermark.mantrabrain.com/docs/)
* 💎 [Ultimate Watermark Pro](https://mantrabrain.com/plugins/ultimate-watermark/#pricing)

[youtube https://youtu.be/6QcDWgtaJRU]

= Free features =

**Text and image watermarks**

* Text watermark: your copyright line, brand name or URL, with font family, size (up to 200 px), colour, bold, italic, light weight, and underline, overline or line-through.
* Image watermark: any logo or signature from your Media Library (transparent PNG recommended; JPG, WebP and GIF also work).
* Logo size: original, custom width/height, or scaled as a percentage of each photo, so the logo stays proportional on small and large images.
* 9-point position grid, X/Y offsets in pixels or percent (negative values allowed), rotation and opacity.
* **Repeat pattern:** tile the watermark across the whole image with adjustable spacing. Covers landscape, portrait and square photos completely, including rotated text, for a classic stock-photo look.
* Output quality per watermark (JPEG quality, baseline or progressive).
* Live preview with a real photo while you edit, showing which image library (Imagick or GD) is in use.

**Four ways to apply**

* **Automatic:** new uploads are watermarked as they arrive. A toggle in the upload screen and media modals lets you switch it off for a single upload, and choose which watermark(s) to use.
* **Bulk:** select any number of images in the Media Library, in list view (Bulk actions) or grid view (Bulk select), and apply or remove a watermark.
* **Manual:** apply or remove a watermark from a single image: from the attachment details popup or the edit-media screen.
* **Frontend uploads:** images uploaded from the front end of your site, such as a Dokan vendor dashboard or a front-end form, are watermarked too.

**Rules**

Limit a watermark to the images it belongs on, by **image size** (thumbnail, medium, large, full and every theme / WooCommerce size) and **post type** (posts, pages, products, custom post types). Combine conditions with AND / OR. Without rules a watermark applies everywhere. Pro adds 11 more conditions (see below).

**WooCommerce and Dokan**

* Product images, gallery images and variation images are watermarked automatically, including products created from the Dokan vendor dashboard, the block editor, the REST API or imports.
* Rules are evaluated after the product and its categories are saved, so product-based rules work on the very first publish.
* **Downloadable files are never touched:** files uploaded as downloadable products (stored in `woocommerce_uploads`) or attached to a product's downloads are never watermarked, resized, copied or previewed, so buyers always receive the exact original.
* Vendors can only watermark, remove or restore their own images.

**Backup and restore**

* The original is backed up before the first watermark is applied: full size only, or full size plus every watermarked size.
* Restore one image or many from **Watermark → Backups**, or remove the watermark from the Media Library. Thumbnails are regenerated automatically.
* Backups are stored in a protected folder with unguessable names and are only viewable by administrators.
* Failure-safe: images, backups and restores are written to a temporary file and swapped in only when complete. With backups on, an image is never changed if its original could not be saved first.

**Image protection**

* Disable right-click and drag-and-drop on images, optionally also for logged-in users.

**Dashboard and analytics**

* Dashboard with total and protected images, protection rate, active watermarks and recent activity.
* Analytics with watermark usage over time and protection trends.

**Built for real sites**

* Imagick (preferred) with automatic GD fallback, including per-image fallback when Imagick lacks a format.
* JPEG, PNG (transparency kept), WebP, AVIF (with Imagick) and GIF. Animated GIFs are skipped so they keep their animation.
* Large uploads (WordPress' "-scaled" images), cropped copies and sizes that share one file are each watermarked exactly once. Re-applying never stacks a watermark twice.
* Works with every page builder (Gutenberg, Elementor, Divi, Beaver Builder, Bricks): watermarks are part of the image file itself.
* Tested on PHP 7.4 to 8.4 with both GD and Imagick, on WordPress 7.1 with WooCommerce and Dokan. Requires WordPress 5.9 or later.
* Translation-ready, with developer filters and actions throughout.
* 1.x settings and watermarks are migrated automatically.

= Pro features =

[Ultimate Watermark Pro](https://mantrabrain.com/plugins/ultimate-watermark/#pricing) is an add-on for this plugin. It adds:

* **Unlimited watermarks:** the free version has one watermark; Pro removes the limit, so you can use different watermarks per size, post type or product category.
* **11 extra rule conditions:** file type, file size, image width, image height, orientation (landscape / portrait / square), aspect ratio, upload date, user role, post category, and WooCommerce product category and product tag.
* **45+ dynamic placeholders** in text watermarks: site name and URL, dates and times, copyright year and holder, uploader name and role, image title, caption, alt text and filename, post title, post category, custom fields and more.
* **EXIF placeholders:** camera make and model, lens, focal length, aperture, shutter speed, ISO, flash, exposure mode and white balance, for photo credits such as `{{camera_model}} · {{aperture}} · ISO {{iso}}`.
* **30 Google Fonts** (Roboto, Open Sans, Montserrat, Playfair Display, Pacifico, Bebas Neue and more) in a searchable picker that previews each font, downloaded once and cached locally.
* **WooCommerce per-product and per-category watermarks:** choose a watermark on a single product or a product category, with a Product → Category → Global cascade, plus a "Watermark All Products" bulk action.
* **On-the-fly display watermarks:** visitors see watermarked copies of your images in post content, thumbnails and WooCommerce galleries while the files on disk stay untouched. Selected roles see the originals; generated copies are cached.
* **Batch processing:** apply, replace or remove watermarks on thousands of images in tracked batches, filtered by upload date, file size, image width and the post type they are attached to, with progress, cancel and resume. Batches continue in the background via WP-Cron.
* **Automatic updates and priority support** with your license.

= Use cases =

* **Photographers:** protect portfolio shots, watermark a whole season at once, add camera details as a credit (Pro).
* **Bloggers and publishers:** brand every featured image with your site name or logo so copies always point back to you.
* **WooCommerce stores:** watermark every product photo automatically and launch with the whole catalogue protected.
* **Marketplaces (Dokan):** watermark vendors' product previews while the paid downloadable files stay pristine.
* **Stock and digital media sellers:** a tiled watermark across the preview, the clean original only for buyers.

= Privacy =

Ultimate Watermark runs entirely on your server. No images, metadata or usage data are sent to MantraBrain or any third party. (Pro contacts the MantraBrain license server only to validate your key and check for updates, and downloads Google Fonts once when you choose one.)

== Installation ==

= Automatic =

1. In your WordPress admin, go to *Plugins → Add New*.
2. Search for **Ultimate Watermark**.
3. Click *Install Now*, then *Activate*.
4. Go to *Watermark → Add Watermark* and create your first watermark.
5. Upload a new image: it is watermarked automatically.

= Manual =

1. Download the plugin .zip from WordPress.org.
2. In wp-admin go to *Plugins → Add New → Upload Plugin*.
3. Choose the file, click *Install Now*, then *Activate*.
4. Continue from step 4 above.

= Activating Pro =

1. Install and activate this free plugin first (Pro is an add-on).
2. Upload `ultimate-watermark-pro.zip` under *Plugins → Add New → Upload Plugin* and activate it.
3. Go to *Watermark → License* and enter your license key.

== Frequently Asked Questions ==

= How do I add a watermark to all images in WordPress? =

Go to *Watermark → Add Watermark*, choose Text or Image, set the position and opacity, make sure *Automatic watermarking* is on, and save. Every image you upload from then on is watermarked. For images you already have, select them in *Media → Library* and choose *Apply Watermark* (Bulk actions in list view, or *Bulk select* in grid view).

= Can I remove a watermark and get the original image back? =

Yes. With backups on (the default), the original is saved before the first watermark. Open *Watermark → Backups* and click *Restore* (or select several and use *Restore Selected*), or use *Remove watermark* in the Media Library. The thumbnails are regenerated automatically.

= How do I cover the whole image with a watermark? =

Turn on *Repeat Pattern* on the watermark's Appearance tab and set the spacing. The watermark is tiled across the entire image, whatever its size or orientation. A scaled logo (for example 20 %) or a rotated text gives the classic stock-photo look.

= Can I watermark WooCommerce product images? =

Yes. Product, gallery and variation images are watermarked automatically when the product is saved, including on first publish. Add a *Post Type is Product* rule to watermark only product images. With Pro you can also set a different watermark per product or per product category.

= Are my downloadable products (paid files) watermarked? =

No. Files uploaded as downloadable product files in WooCommerce or Dokan, or used in a product's *Downloadable files*, are never watermarked, resized or copied. Buyers always receive the exact file you uploaded. Developers can adjust this with the `ultimate_watermark_preserve_download_originals` filter.

= Does it work with Dokan? =

Yes. Images vendors upload from the Dokan dashboard are watermarked when the product is saved, rules such as *Post Type is Product* work on the first publish, vendors can only change their own images, and downloadable files stay untouched. Enable *Frontend watermarking* on the watermark for front-end uploads.

= Why isn't my watermark applied? =

Check that the watermark is *Active* and has *Automatic watermarking* (or *Frontend watermarking* for front-end uploads) enabled, and that the upload toggle in the media screen is on. If the watermark has rules, it only applies to images that match them: open the *Rules* tab and relax or remove them. Animated GIFs and downloadable product files are skipped on purpose.

= Does the plugin keep EXIF metadata? =

With Imagick (used by most hosts), the photo's EXIF and IPTC data is kept. The GD library cannot keep metadata, so on GD-only servers it is removed from watermarked copies; your backed-up original always keeps it.

= Can I use my own font? =

The free version includes bundled fonts (sans-serif, serif and monospace) that work on every host, mapped to the Arial, Helvetica, Times New Roman, Georgia, Verdana and Courier New choices. Pro adds 30 Google Fonts, downloaded once and cached on your server.

= Will it slow down my site? =

No. Watermarks are applied once, when an image is uploaded or when you apply them, never on page views. (Pro's optional on-the-fly display mode generates a cached copy the first time an image is viewed.)

= Does it work with REST API, block editor and page-builder uploads? =

Yes. Images uploaded through the REST API, the block editor, page builders or headless front ends are watermarked like any other upload.

= Will it work with my CDN or offload plugin? =

Watermarks are written into the image file on your server, so a CDN that pulls from your site serves the watermarked image. Offload plugins that move files to S3 or similar storage should do so after the image has been processed; test with a single upload first.

= I used version 1.x. Will my settings carry over? =

Yes. On update a one-time migration converts your 1.x watermark, settings and per-image records to the new format.

= Is the free version limited? =

The free version is fully functional with one watermark: automatic, bulk, manual and frontend watermarking, repeat pattern, size and post-type rules, backups and restore, and WooCommerce/Dokan support. Pro adds unlimited watermarks, more rule conditions, placeholders, Google Fonts, per-product watermarks, on-the-fly display and batch processing.

= What happens when I deactivate or delete the plugin? =

Watermarked images stay watermarked. To get the originals back, restore them from *Watermark → Backups* first. Your watermarks, settings and backup files (in `wp-content/uploads/ulwm-backup/`) are kept, so nothing is lost if you reinstall; remove that folder manually if you no longer need it.

== Screenshots ==

1. Dashboard: total and protected images, protection rate, active watermarks and recent activity.
2. Analytics: watermark usage over time and image protection trends.
3. Watermarks list: type and preview, behaviour, rules, position, opacity, usage and status of each watermark.
4. Add / Edit Watermark with the live preview.
5. Settings: backup strategy and image protection.
6. Backups: restore or delete originals, one by one or in bulk.
7. Upload screen: switch automatic watermarking on or off and choose the watermarks for this upload.

== Changelog ==

= 2.2.0 - 2026/10/13 =
* **Fixed** - Images assigned as featured, gallery or variation images of a NEW product (Dokan frontend dashboard, WooCommerce REST/CRUD, block editor) were never watermarked; only re-saving an existing product worked. Images are now evaluated once the product and its categories are fully saved, so rules such as "Product Category is X" work on first publish.
* **Fixed** - WooCommerce gallery images were never watermarked automatically (wrong meta key).
* **New** - Protected download files: files uploaded as downloadable products (WooCommerce/Dokan, stored in woocommerce_uploads) or used in a product's downloadable files are never watermarked, copied or previewed.
* **New** - Repeat pattern: tile a watermark across the whole image (any size or orientation) with adjustable spacing.
* **Fixed** - GD library: image watermarks were positioned too low (or outside the image for bottom positions), "Scaled" size was relative to the logo instead of the photo, rotation and percentage offsets were ignored.
* **Fixed** - Large images (over 2560px) got the watermark twice on the "-scaled" file; restoring them from backup created "-scaled-scaled" files and deleted the original.
* **Fixed** - Regenerating thumbnails re-watermarked already watermarked images.
* **Security** - Backups of original images are no longer publicly downloadable (protected folder, unguessable names, admin-only preview); backups are now byte-exact copies.
* **Security** - Settings save requires the manage_options capability; save/delete/toggle only act on watermark posts; the upload toggle is stored per user.
* **Improved** - Negative offsets and rotation are kept; watermark changes take effect immediately with persistent object caches; the whole object cache is no longer flushed on settings save.
* **Improved** - Admin UI: working bulk actions on the Watermarks page and bottom bulk dropdown in the Media Library, correct upload toggle switch and status, fixed "Back to Dashboard" links, backup pagination, analytics rate capped at 100%, Chart.js bundled locally, no "translation files not found" notice.
* **Fixed** - The Media Library upload toggle never sent its setting with uploads (grid view and media modals), so "Apply automatic watermarks to uploaded images" had no effect.
* **Fixed** - Switching tabs on the Add/Edit Watermark screen reloaded the page and discarded unsaved changes; tabs now switch instantly and keep their state.
* **Fixed** - Saved values of 0 (e.g. tile spacing) or an empty list (e.g. bypass roles) were replaced by defaults when editing a watermark.
* **Improved** - Hosts whose Imagick lacks a format (e.g. JPEG/WebP) automatically fall back to GD per image instead of silently skipping it.
* **Improved** - Live preview panel shows the saved values immediately; dead "Save Draft" button removed (Ctrl/Cmd+S saves); keyboard-accessible position grid and tabs; all admin JavaScript messages are translatable.
* **New** - "Frontend watermarking" now works: images uploaded from the front end of the site (e.g. a Dokan vendor dashboard) get the watermarks marked for it, following their Rules, even when automatic watermarking is off.
* **Fixed** - Cropped copies of an already watermarked image (Dokan / WordPress image cropper) were watermarked a second time.
* **Improved** - Variation images of variable products and products moved into a rule's category later are watermarked automatically.
* **Fixed** - Imagick: transparent PNG images lost their transparency after watermarking.
* **Fixed** - Repeat pattern with rotated text left gaps; tiles now use an even, staggered grid on both GD and Imagick.
* **Fixed** - Applying the same watermark again to an image stacked it a second time.
* **Security** - Watermark rule labels on the Watermarks list are escaped (stored XSS by users who can edit watermarks).
* **Security** - Media Library and media edit actions (apply, remove, restore) check that the user may edit each image, so vendors can only change their own media.
* **Improved** - Large images are matched to their attachment exactly (no slow LIKE query); product gallery lookups work on all databases, including SQLite.
* **Improved** - The upload panel marks watermarks that have Rules ("Rules apply"): they are applied once the image matches, for example when it is used on a product in the selected category.
* **Fixed** - Image sizes that share one file (for example "large" and "medium_large", or "medium" and the WooCommerce thumbnail of a square image) were watermarked twice.
* **Fixed** - GD rendered text about a third larger than Imagick and the editor preview (font sizes are pixels). Text watermarks on GD hosts now match; check sizes if you tuned them on a GD host.
* **Safety** - Images are written to a temporary file and swapped in only when complete, so a failed write (full disk, missing codec) never damages an image. The same applies to backups and restores.
* **Safety** - With backups on, an image is not changed if its original could not be backed up. Restore only succeeds (and only clears the watermark records) when the original was restored.
* **Fixed** - Applying a watermark manually to a big ("-scaled") upload lost the link to the original file and backed up the scaled copy instead of the original.
* **Fixed** - Rules based on the post (category, post type) were evaluated while a new post was still an empty draft; images are now evaluated when the post is first saved.
* **Fixed** - A same-named WebP of another attachment (photo.webp next to photo.jpg) could be watermarked or deleted; restoring now also removes the watermarked WebP/AVIF copy of the full-size image.
* **Fixed** - Animated GIFs are left untouched (they would lose their animation); transparent GIFs keep their transparency with GD; AVIF variants are watermarked with Imagick; GD previews keep transparency.
* **Fixed** - Trashed watermarks were still applied by overrides and batches. Backups and usage records are removed when an image is deleted from anywhere (not only from wp-admin).
* **Security** - Backup confirmation dialogs no longer interpret image titles as HTML. A watermark nonce is no longer printed on every front-end page.
* **Improved** - Analytics loads far faster on large sites and shows an error instead of failing silently; the live preview never shows an older result; long watermark names in any language are saved safely; a missing vendor folder shows a notice instead of a fatal error.
* **New** - Downloadable product files (WooCommerce/Dokan) are stored exactly as uploaded: WordPress no longer creates a downsized "-scaled" copy, which WooCommerce and Dokan would otherwise link to buyers instead of the original, nor thumbnail copies of the paid file. Filter: ultimate_watermark_preserve_download_originals.
* **Fixed** - GD: rotated watermarks left a faint tinted box around each copy, clearly visible with the repeat pattern.
* **Fixed** - Protected download files no longer show as broken images (403) in the Media Library, list view and edit screen; they appear as files.
* **Fixed** - Dokan's product image crop step left the vendor's full-size upload unwatermarked at a public URL; it is now watermarked together with its cropped copy (filter: ultimate_watermark_protect_crop_source).
* **New** - Media Library grid view (the default view): watermark or remove watermarks from several images via "Bulk select", and a Watermark section in the attachment details popup with the current status and Apply / Remove.
* **Fixed** - Bold and Italic text settings had no effect with the bundled fonts (and never on GD); the style is now rendered on both GD and Imagick when the font has no bold/italic file.
* **Fixed** - Saving a watermark silently did nothing when a value on another tab was out of range; the editor now opens that tab and shows the message. Text can be up to 200 px (was capped at 72 px in the editor).
* **Improved** - Watermarks list: text previews are always readable (light colours on a dark strip, large sizes scaled down), logos are shown whole on a checkerboard, names no longer wrap word by word, and display-only watermarks have their own icon.
* **Improved** - Phone layouts: editor tabs and the position grid fit the screen; Backups are listed as cards so Restore / Delete stay reachable.
* **Improved** - The Backups page shows small cached thumbnails (kept in the protected backup folder) instead of downloading every full-size original, so it loads quickly even with very large photos.
* **Compatibility** - Verified on PHP 7.4 through 8.4 (GD and Imagick), WooCommerce 10.7 to 11.2 and Dokan Lite 5.3.

= 2.1.5 - 2026/08/21 =
* **Compatibility** - Tested and verified against WordPress 7.1. Every WordPress function the plugin calls was cross-checked against the 7.1 codebase: none are deprecated or removed in this release.
* **Security** - The settings AJAX endpoint verified the nonce but never checked capabilities, so it did not require an administrator. It now requires `manage_options`.
* **Security** - The media-library endpoint that stores the pending watermark selection wrote an option after checking only the nonce. It now requires `upload_files`.
* **Fixed** - The plugin advertised support for PHP 7.4 but could not run on it. Five methods in the backup manager and the GD processor used PHP 8.0 union return types (`bool|string|array`, `\GdImage|false`), which are a parse error on 7.4 - so watermarking and backups fatally errored the moment those classes loaded. The return types moved to docblocks and the whole plugin now parses cleanly on PHP 7.4.
* **Fixed** - "Requires at least" advertised WordPress 5.0, but the backup manager calls `str_ends_with()`, which WordPress only polyfills from 5.9 onward. On WordPress 5.0-5.8 running PHP 7.4 that was a fatal error on the backup screen. The header now states the version the plugin actually needs.

= 2.1.4 - 2026/05/21 =
* **Compatibility** - Tested and verified against WordPress 7.0. The "Tested up to" header is bumped so the WordPress.org installer no longer warns site owners running 7.0 that the plugin is untested on their version.

= 2.1.3 - 2026/05/20 =
* **Fixed** - Text watermarks failed to render on minimal/shared hosts (e.g. IONOS) with the cryptic FreeType error "unable to read font \`'". The plugin now ships three SIL OFL-licensed TTFs in `assets/fonts/` (Nunito sans, Merriweather serif, JetBrains Mono) so text rendering works regardless of host environment, with the family picker mapped to its closest bundled stand-in.
* **Fixed** - Selected Google Fonts (Pro) didn't render on the actually-applied watermark even though the preview showed them correctly. Load-time font validator was rejecting non-system families and silently reverting to Arial; now consults the `ultimate_watermark_allowed_fonts` filter so Pro typefaces survive the save → load round trip.
* **Fixed** - Imagick text watermarks on macOS Homebrew silently fell back to Helvetica when the plugin path contained a space (e.g. Local-by-Flywheel's "Local Sites/" folder). MVG was dropping the path; we now mirror the resolved font onto the Imagick image as well as the draw object.
* **Fixed** - "Remove All Watermarks" on the media edit screen could delete files belonging to unrelated attachments because the cleanup loop globbed `imagename-*.*` and matched siblings like `imagename-2.webp`, then produced confusing "image does not exist" warnings during regen. Cleanup is now driven entirely from `wp_get_attachment_metadata()['sizes']` so only this attachment's size files are touched.
* **Fixed** - Cancel button on every confirmation modal (Remove All, Delete Watermark, Bulk Delete, Media Library bulk action) was running the destructive action because `UWNotifications.confirm()` resolves with the action string and the callers were doing `if (confirmed)` — truthy for the string `'cancel'`. All five call sites now check `action === 'confirm'` explicitly.
* **Improved** - Fail-fast `RuntimeException` with a clear message if the bundled font is somehow missing, instead of letting FreeType emit its cryptic error.
* **Improved** - Removed verbose per-render `error_log()` traces ("Imagick: family=… → using font…", etc.) — debug.log stays quiet in production while `WP_DEBUG` is off.

= 2.1.2 - 2026/05/11 =
* **Fixed** - Optimized plugin title for maximum SEO visibility with comprehensive keywords
* **Fixed** - Added Quick Links section with emojis for better user navigation
* **Fixed** - Updated YouTube video link to proper demo content

= 2.1.1 - 2026/05/11 =
* **Fixed** - Updated plugin title to comply with WordPress.org guidelines (removed WordPress from title)
* **Fixed** - Improved title for better SEO optimization

= 2.1.0 - 2026/05/10 =
* New: Google Fonts integration in Pro — 30+ curated typefaces with on-disk caching and a searchable font picker that renders each option in its own typeface.
* New: WooCommerce auto-apply on product save (Pro) — the per-product / per-category cascade now actually fires when you save a product, including for variations.
* New: Onboarding notice system — review prompt after 7 days, upgrade-to-Pro nudge after 10 days (only if Pro isn't active); both with snooze-once-then-forever lifecycle.
* New: Image Watermark / Get Pro identity — distinct orange CTA so the upgrade path is unmistakable on the menu, plugins-row link, upgrade page and limit modal.
* New: Filter `ultimate_watermark_resolve_font_path` lets Pro / third-party plugins inject custom font files into the rendering pipeline.
* New: Action `ultimate_watermark_admin_enqueue_scripts` lets Pro hook its admin assets at the right point in the asset pipeline.
* Fixed: Live preview "Preview Error" — Imagick processor now resolves real TTF files from disk, scales watermark data for the preview source, and surfaces the actual error message to the admin.
* Fixed: Empty rules array now means "apply everywhere" instead of "never apply" — matches user expectations and other watermarking plugins.
* Fixed: Rule operator whitelist expanded to match the Pro UI — `greater_equal`, `less_equal`, `contains`, `not_contains`, `starts_with`, `ends_with`, `in`, `not_in`, `between`, `not_between` are now actually saved and evaluated.
* Fixed: Date-range rules compare via Unix timestamp instead of `floatval()` of a date string.
* Fixed: `product_cat` / `product_tag` rules with `contains`, `in`, `not_in` and other multi-value operators now correctly evaluate against every term on the product.
* Fixed: Pro user placeholders (`{user_display_name}`, `{user_email}`, etc.) now resolve to the attachment uploader, then the parent post's author, then current user — not just the logged-in admin.
* Fixed: Pro date placeholders use `wp_date()` (locale-aware) and the attachment's upload time instead of server time.
* Fixed: Watermark preview now sets attachment context so EXIF / user / post placeholders resolve to real values during template editing.
* Fixed: WooCommerce category form now includes a CSRF nonce on save.
* Fixed: Update Watermark button on the edit screen no longer reverts to "Create Watermark" after saving.
* Fixed: Dashboard "Protected Images" and "Protection Rate" counters were stuck at 0 because they queried a legacy meta key — now match every flag the watermark pipeline writes.
* Fixed: Asset registry now matches the actual hook suffix WordPress emits for submenu pages, so every page-specific stylesheet loads correctly.
* Improved: Unified design system across all admin pages — single blue brand, single orange Pro identity, full-width layouts, polished header/footer with inline SVG icons, redesigned backup pagination, redesigned confirmation modal alignment.
* Improved: MediaEditIntegration now pulls in the design tokens from `admin.css` so the attachment-screen panel inherits the same look.
* Improved: Documentation URL moved to `https://ultimate-watermark.mantrabrain.com/docs/`.

= 2.0.8 - 2026/04/27 =
* Fixed legacy targeting rules and REST API watermarking issues.
* Improved compatibility with custom rule combinations.

= 2.0.7 - 2025/06/15 =
* Fixed media popup toggle reliability across all WordPress pages.
* Improved compatibility with the block editor and classic editor uploads.

= 2.0.6 - 2025/05/20 =
* Added WordPress.org language pack support.
* Improved post-upload hook isolation to avoid conflicts with other plugins.

= 2.0.5 - 2025/04/18 =
* Fixed JavaScript errors on the Settings page that prevented saving.

= 2.0.4 - 2025/03/25 =
* Improved REST API integration for headless WordPress sites.

= 2.0.3 - 2025/03/05 =
* Compatibility improvements with WooCommerce 9.x.

= 2.0.2 - 2025/02/14 =
* Performance improvements when bulk-watermarking large image sets.

= 2.0.1 - 2025/02/01 =
* Bug fixes and stability improvements.

= 2.0.0 - 2025/01/15 =
* Major rewrite: PSR-4 architecture, unified WatermarkService, REST API support.
* Free version limited to 1 watermark template.
* Pro version adds unlimited templates, dynamic placeholders, on-the-fly display, batch operations, WooCommerce per-product / per-category overrides.

== Upgrade Notice ==

= 2.2.0 =
Fixes WooCommerce/Dokan product images not being watermarked on first publish, never touches downloadable product files, adds a repeat (tiled) pattern, and makes watermarking, backups and restores failure-safe. Text watermarks on GD hosts now match the editor size (they were about a third larger).

= 2.1.5 =
Tested up to WordPress 7.1. Fixes a fatal parse error on PHP 7.4, adds missing capability checks on two AJAX endpoints, and corrects the minimum WordPress version, which was advertised as 5.0 but is actually 5.9.

= 2.1.4 =
Compatibility bump for WordPress 7.0 — clears the "untested on this version" warning in the installer. No code changes; safe to upgrade.

= 2.1.3 =
Critical fix for text watermarks on minimal/shared hosts (IONOS and similar) where missing system fonts produced FreeType errors — the plugin now ships its own bundled TTFs. Also fixes the Cancel button on confirmation modals running the action, Pro Google Fonts not rendering on the actually-applied watermark, and a "Remove All" cleanup bug that could affect unrelated attachments. Recommended for everyone.

= 2.1.0 =
Major reliability release: rules engine fixed (empty rules now apply everywhere, all 14+ operators implemented, multi-value taxonomies), Pro WooCommerce per-product/per-category watermarks fire on save with variation support, Pro placeholders resolve to the attachment uploader and use locale-aware dates, Live Preview fixed, dashboard stats fixed, full design overhaul, new onboarding notice system. Recommended for everyone.

= 2.0.8 =
Fixed legacy targeting rules and REST API watermarking compatibility.

= 2.0.7 =
Fixes a media popup toggle issue affecting some editors. Recommended.

= 2.0.5 =
Fixes Settings page JavaScript errors. Required if you can't save settings.

= 2.0.0 =
Major architectural rewrite. Free version limited to 1 watermark — review the Pro plan if you need more.
