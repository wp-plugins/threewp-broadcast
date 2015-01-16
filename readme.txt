=== ThreeWP Broadcast ===
Contributors: edward_plainview
License: GPLv3
Requires at least: 3.3.1
Stable tag: trunk
Tags: broadcast, multipost, sharing, share content, duplicate, posts, marketing, threewp, linking, posts, multiple, blogs, sitepress, woocommerce, synchronize, event organiser, acf, all in one calendar, menu, copy menu, duplicate menu
Tested up to: 4.1

Network content sharing by multiposting between blogs for PHP v5.4+. Posts can be linked to each other and updated automatically.

== Description ==

Network content sharing by multiposting between blogs for PHP v5.4+. Broadcast can be used to copy posts to other blogs, link posts between blogs, share content as templates, etc. Broadcastable features include:

* Parent post links to child posts
* Posts, pages
* Taxonomies (categories, tags, etc)
* Custom fields
* Attached images
* Featured images
* Galleries

Broadcasted posts can be linked to their parents, which updates child posts when the parent post is updated. This includes all data: title, slug, content, custom fields, attachments, etc.

= SEO support =

* Permalinks of child posts (also see Permalinks plugin in the Premium Pack)
* Canonical URLs of child posts.

For those who have Yoast's Wordpress SEO plugin installed, if the Broadcast's canonical URL is selected (which will point to the parent post), it will simultaneously disable Yoast's canonical link. This will prevent search engine penalties.

= Other features =

* Groups plugin enables blog grouping for easy selection
* Custom field blacklist, whitelist and protect list.
* Last used settings are remembered
* User role access granularity
* An enormous amount of extra features in the premium pack

= Premium Pack =

The <a href="http://plainviewplugins.com/threewp-broadcast-premium-pack/" title="Premium Pack's page on the web"><em>Broadcast Premium Pack</em></a> is an actively maintained collection of plugins that expand the functionality of Broadcast.

For a complete list of features and more information, see <a href="http://plainviewplugins.com/threewp-broadcast-premium-pack/" title="Premium Pack's page on the web"><em>Broadcast Premium Pack</em>'s page on the web</a>. Currently the Premium Pack offers:

* <strong>Advanced Custom Fields</strong> adds support for correctly broadcasting attachment field types using the ACF plugin.
* <strong>All Blogs</strong> allows users to broadcast to all blogs in the network without having to be a user of the blog.
* <strong>All In One Calendar</strong> adds support for <a href="http://www.wordpress.org/plugins/all-in-one-event-calendar/">Timely's All In One Calendar</a> plugin.
* <strong>Back To Parent</strong> updates the parent post with the new child content.
* <strong>Attachment Shortcodes</strong> copies attachments specified in custom shortcodes.
* <strong>Comments</strong> broadcasts and sync comments between linked posts.
* <strong>Custom Field Attachments</strong> allows post custom field containing attachment IDs to be broadcasted correctly.
* <strong>Duplicate Attachments</strong> duplicates the attachments from the parent post tp the child posts, instead of regenerating them. Speeds up broadcasting and keeps any manual thumbnail modifications.
* <strong>Event Organiser</strong> adds support for Stephen Harris&#8217; <a href="http://wordpress.org/plugins/event-organiser/">Event Organiser plugin</a>, with events and venues.
* <strong>Keep Child Attachments</strong> keeps the child post's attachments instead of deleting them when updating a broadcast.
* <strong>Keep Child Status</strong> keeps the status of post children to private, pending, published, draft, no matter the status of the parent.
* <strong>Local Links</strong> automatically updates links to local posts on each child blog.
* <strong>Lock Post</strong> allows users to lock editing of posts / pages to only themselves and super admins.
* <strong>Menus</strong> can copy menus between blogs (overwrite / update), with support for equivalent child posts on the child blogs and equivalent taxonomies.
* <strong>No New Terms</strong> prevents taxonomy terms from being created on child blogs.
* <strong>Per Blog Taxonomies</strong> allows individual setting of child post taxonomies.
* <strong>Permalinks</strong> enables more precise permalink control.
* <strong>Polylang</strong> adds support for Broadcasting posts in different languages using Frédéric Demarle&#8217;s <a href="https://wordpress.org/plugins/polylang/">Polylang</a> translation plugin.
* <strong>Protect Child Content</strong> prevents overwriting of child post content.
* <strong>Purge Children</strong> removes children and their attached files from child blogs.
* <strong>Redirect All Children</strong> redirects single post views from visitors of child posts to the parent post.
* <strong>Queue</strong> adds a broadcast queue which helps to broadcast posts to tens / hundreds / more blogs.
* <strong>Send To Many</strong> broadcasts many posts to several blogs at once, instead of individually editing and broadcasting each post.
* <strong>Sync Taxomnomies</strong> synchronize the taxonomies of target blogs with those from a source blog.
* <strong>The Events Calendar</strong> adds support for Modern Tribe's <a href="https://wordpress.org/plugins/the-events-calendar/">The Events Calendar </a> plugin with venues and organisers.
* <strong>User & Blog Settings</strong> (UBS) can hide the broadcast meta box and/or menu, modify the meta box to force/prevent broadcast to blogs, with separate settings for users / blogs / roles.
* <strong>User & Blog Settings Post</strong> uses the modifications from the UBS plugin to broadcast posts with one click.
* <strong>Views</strong> adds support for WP Types and Views content templates.
* <strong>WooCommerce</strong> allows attribute taxonomies and product variations to be broadcasted.

= Documentation =

Although Broadcast is relatively easy to understand by itself, for extra documentation see <a href="http://plainviewplugins.com/threewp-broadcast/">Broadcast's online documentation</a>.

= Misc =

Requires php v5.4 for trait support. PHP 5.3 is no longer officially supported.

Available in the following languages:

* English
* Dutch
* French - Seb giss <sgissinger@gmail.com>
* Italian
* French
* Romanian, Web Geek Sciense <a href="http://webhostinggeeks.com/">Web Hosting Geeks</a>
* Spanish
* Swedish

The git repository can be found at: https://github.com/the-plainview/threewp_broadcast

== Installation ==

1. Check that your web host has PHP v5.4.
1. Activate the plugin locally or sitewide. The latter option is more common (and useful).

== Screenshots ==

1. Broadcast meta box when editing posts
2. Post overview showing linked children
3. Post overview showing linked parents
4. Bulk actions that can be applied to several marked posts at once
5. Post actions for parent posts
6. Post actions for child posts
7. The Broadcast menu
8. Admin settings tab
9. Custom post types tab
10. Maintenance tab
11. Uninstall tab

== Frequently Asked Questions ==

= I need support! =

The easiest way to get my attention is to <a href="mailto:edward@plainviewplugins.com">contact me via e-mail</a> and ask how to donate a little something for my time.

For contract work such as the following, contact me so we can come to an agreement:

* Broadcast is missing a feature you need
* Broadcast isn't properly interacting with other plugins
* Broadcast doesn't work on your custom site

If you're not into donations, try the support forum to see if other users can help you out.

= Debug dumps =

A debug dump is the long text that is displayed when broadcasting a post with debug mode on. This dump can then be read to see what Broadcast is or isn't doing.

To switch on debug mode, see the admin settings. If your site is live it would be wise to input your IP in the associated textarea, so that only you see the debug dump.

= Blacklist, whitelisting, force broadcast =

Broadcasting to specific blogs, hiding blogs and forcing blogs can be acheived with the <em>User & Blog Settings</em> plugin in the <a href="http://plainviewplugins.com/threewp-broadcast-premium-pack/" title="Premium Pack's page on the web"><em>Broadcast Premium Pack</em></a>.

= Bulk broadcast existing pages =

To broadcast many posts at once, see the <em>Send To Many</em> plugin in the <a href="http://plainviewplugins.com/threewp-broadcast-premium-pack/" title="Premium Pack's page on the web"><em>Broadcast Premium Pack</em></a>.

= Galleries and attachments =

Attachments are force-broadcasted: the child posts have all their attachments deleted and then copied again.

If you have a gallery shortcode in the post ( [gallery columns="2" ids="427,433,430,429,428"] ) then Broadcast will first check that the image does not already exist on the child blog. It does this by searching for the post name (the filename minus the extension). If no image is found, it is copied.

If you have manually modified the thumbnails on the parent blog, you might want to use the <em>Duplicate Attachments</em> plugin in the plugin pack, otherwise Wordpress will generate new thumbnails on each child blog.

= Hide broadcast from the users =

The broadcast meta box, menu and columns in the post view can be hidden from users / roles / blogs using <em>User & Blog Settings</em> plugin in the <a href="http://plainviewplugins.com/threewp-broadcast-premium-pack/" title="Premium Pack's page on the web"><em>Broadcast Premium Pack</em></a>.

= Is php v5.4 really necessary? =

Yes. PHP v5.3 has been officially unsupported since the 14th of August, 2014. If your web host refuses to upgrade to a supported version, go find a new host.

= Orphans? =

If you have already created posts on other blogs that are supposed to be children of a specific post, you can use the "find unlinked" bulk action to find and link them.

To be considered an orphan the orphaned posts must have the exact same title (name) as the soon-to-be parent and be of the same post type.

= Timeout problems =

If you have many attachments in your post, and are broadcasting the post to many blogs, you might encounter a PHP timeout. This means that broadcasting exceeded the PHP time limit and had to be aborted.

There are several solutions to this problem:

1. Increase the PHP timeout in your PHP.ini settings. This will not speed up broadcasting, only increase your chances of completely broadcasting the post.
2. Use the <em>Duplicate Attachments</em> plugin in the pack. This will duplicate any attachments + thumbnails instead of regenerating them on each child blog.
3. Use the <em>Queue</em> plugin to put each child broadcast into a queue that is emptied by javascript.

You will not need solution #1 if you use solutions #2 and #3. :)

= Why can I not see the Broadcast meta box? =

Make sure that:

1. The plugin is network enabled
2. Your user level has broadcast access (Broadcast access role)
3. Your user has write access to more than this blog (see Admin settings > Maintenenace > View blog access). Or use the All Blogs premium plugin to access all blogs.
4. The correct post type(s) have been selected
5. <em>User & Blog Settings</em> is not set to hide the meta box from the user / role / blog

= WooCommerce =

Broadcast is capable of handling WooCommerce products.

1. In the custom post type settings: Add "product"
2. In the settings: select broadcast internal custom fields.
3. When broadcasting, select custom fields and taxonomies.

This will broadcast all normal product settings: SKU, price, etc.

If your products have variations, or you want the attribute taxonomies to be synced, you'll be wanting the WooCommerce plugin from the premium pack.

If you have a product gallery, use the "Custom Field Attachments" premium plugin to broadcast the "_product_image_gallery" custom field.

= WPML Sitepress =

WPML is semi-supported via a plugin in the premium pack.

For more information, see WPML discussion here: http://wordpress.org/support/topic/wmpl-integration-not-working

The author suggests using PolyLang instead due to far superior support.

= XCache vs APC opcode cache =

Xcache v2 does not support PHP namespaces, which is a PHP 5.3 feature. Trying to enable Broadcast with Xcache v2 enabled will result in a PHP crash.

Xcache v3, which does support namespaces, has not yet been tested. Anyone with Xcache v3 experience is welcome to contact me with info.

APC works flawlessly.

== Changelog ==

= 16 20150116 =
* Fix: Fixed autoloading error for specific web hosts.
* Fix: Form actions were incorrect for some non-standard installations, causing forms to timeout.
* Optimization: Term syncing only syncs terms that are used. This should solve any out-of-memory errors that those with 39000+ terms were having.
* Premium Pack Plugin: Added "Duplicate Attachments" drastically speeds up broadcasting when using attachments. Also retains any manual modifications to the attachment thumbnails.
* Premium Pack Plugin: Comments: Can now keep comments automatically synced between linked posts.
* Premium Pack Plugin: User & Blog Settings: "On" for checkboxes no longer forces the checkbox to be ticked.
* Premium Pack Plugin: User & Blog Settings: Automatically clean up orphaned criteria.
* Premium Pack Plugin: User & Blog Settings: More robust detection of post types.

= 15 20141218 =
* Code: Allow other plugins to prevent term creation.
* New: Premium Pack Plugin: No New Terms
* Fixed: Sticky status kept when clearing the POST.
* Fixed: Bulk actions not appearing for some users

= 14 20141210 =
* Fix: Display bulk actions in a different way to increase compatability with Admin Columns Pro.
* Fix: Override canonical URL for pages also.
* New: Premium Pack Plugin: Lock Post

= 13 20141201 =
* Fix: Blog groups: Group name can be changed again.
* Debug: Custom fields are shown in the debug dump.
* New: Premium Pack Plugin: Polylang
* POT file and Swedish translation updated.
* Code: Source moved from include/ to src/.
* Code: Uses Plainview SDK with custom namespace for future-proofing and conflict avoidance.

= 12 20141109 =
* New: Post actions have been reworked. Documented here: http://plainviewplugins.com/threewp-broadcast/documentation/post-actions/
* New: "Find orphans" is now "Find unlinked children" and is a bulk action.
* New: Setting to choose how many children to view in the post overview, before displaying a child count.
* Fix: Last used settings notice removed.
* Code: _3wp_broadcast table for last used settings has been removed.
* New: Premium Pack Plugin: The Events Calendar

= 11 20141021 =
* Fix: Fatal error when broadcasting attachments.

= 10 20141020 =
* New: Premium Pack Plugin: Menus
* Code: Major refactoring. ThreeWP_Broadcast() function introduced to easily retrieve the Broadcast instance.
* Code: threewp_broadcast_get_user_writable_blogs is now an action.
* Code: Actions now use the standard Plainview Wordpress SDK actions as the base class.

= 9 20141017 =
* Fix JS error: blogs_to_hide empty value.
* Fix: last_used_settings warning.
* Fix: Settings being forgotten sometimes.
* Fix: Maintenance not working sometimes due to non-standard temp directory.

= 8 20141013 =
* New: Premium Pack Plugin: Protect Child Status
* Fix: Better duplicate image finding.
* Code: broadcasting_after_switch_to_blog action has $broadcast_here property. Allows skipping blogs.
* Code: Last used settings are now stored in the user's meta table, instead of a separate database table. The table will be removed in v9 or v10.
* Code: Better attachment validity checking.

= 7 20140923 =
* Fix: Massive optimization of taxonomy syncing.
* New: "Same Parent" check for broadcast data maintenance check. If two posts on a blog say they have the same parent.
* Fix: Hang upon broadcasting galleries.
* Fix: Not setting child post taxonomies sometimes.
* Fix: Code to prevent broadcast looping (using the ACF plugin).
* Fix: Copy attachments with metadata that don't have filenames (ex: m4a files).

= 6 20140909 =
* Code: broadcast_data class replaces BroadcastData.
* New: Premium Pack Plugin: Back To Parent
* Version 4.0 compataiblity (version bump).
* Obsolete and broken WPML plugin removed. See discussion here: http://wordpress.org/support/topic/wmpl-integration-not-working

= 5 20140830 =
* Hide information from non-network admins.
* Fix: Warning messages on lines ~2000
* Fix: Unnecessary warning for empty blog groups.
* Fix: Unlink all works again.
* Code: Better post modification detection (line 2553).
* Change: Internal fields are now broadcasted per default on NEW installations.

= 4 20140814 =
* Fix: More robust duplicate attachment finding.
* Fix: Do a post type check before broadcasting.
* Fix: Check for invalid thumbnails before broadcasting.
* New: Premium Pack Plugin: All In One Calendar
* New: Premium Pack Plugin: Protect Child Content
* New: Premium Pack Plugin: Redirect All Children
* New: Premium Pack Plugin: User & Blog Settings Post

= 3 20140708 =
* New: Blog groups: after selecting a blog group the value will no longer change back to "no group selected".
* New: Blog groups: select "no group selected" and the same blog group again to unselect the blogs from the group.
* Fix: Featured post conflicts resolved. See 3+1 fixes below.
* Fix: Do not automatically delete the thumbnail (it might not be attached to this post).
* Fix: Removed attachment cache.
* Fix: Only attach copy image to post if it was attached to the parent post.
* New: New related premium pack plugin: Keep Child Attachments.
* Code: Meta Box has two new methods to allow for input modifications before display: convert_form_input_later() and convert_form_inputs_now().
* Note: Changed versioning to rapid release. No more point releases.

= 2.24 20140615 =
* New: Custom field protect list.
* Fix: Better support for Windows servers.

= 2.23 20140520 =
* New: Premium Pack Plugin: Comments
* Code: broadcasting_data->new_post()

= 2.22 20140511 =
* Fix: Attachment metadata was not copied sometimes.
* New: Broadcast file checksum info, for debugging purposes.
* Code: Even more debug info.
* Code: code_export removed. Debug methods are now a trait in the SDK.

= 2.21 20140422 =
* New: Custom roles are accepted.
* New: Broadcast Data check checks that the table has the ID column.
* New: Premium Pack plugin: Purge Children.
* Fix: Fatal error when syncing taxonomies. Sometimes.

= 2.20 20140412 =
* New: Premium Pack plugins: Custom Field Attachments, Sync Taxonomies.
* More debug information when syncing taxonomies.
* Code: collect_post_type_taxonomies for Sync Taxonomies plugin.
* Code: wp_insert_term and wp_update_term hooks, for Sync Taxonomies plugin.

= 2.19 20140402 =
* New: Clear POST setting.
* Fix: More tolerant custom field detection for incorrectly set up custom post types.
* Fix: Italian translation updated.
* Fix: Attachment checking uses "name" instead of "post_name" to search for attachments. Less memory required.
* Code: More debugging code.

= 2.18 20130314 =
* New: Premium Pack plugins: Attachment Shortcodes and Event Organiser.
* New: Debugging can be limited to specific IP addresses.
* Fix: Better gallery shortcode detection.
* Fix: Incorrect debug string during attachment handling.
* Fix: Blog Groups and WPML plugins have version numbers again in order to be upgradeable.
* Code: Even more debugging information available.
* Code: Added find_shortcodes method.

= 2.17 20140226 =
* New: Debug setting and information when broadcasting.
* Fix: Taxonomy names are now synced.
* Fix: Attachment captions fixed.
* Fix: Groups ignores non-existing blogs.
* Code: Added broadcasting_modify_post action.
* Code: Added get_post_types action.

= 2.16 20140211 =
* New: Attachment conflict handling.
* Fix: Do not do anything about save_post if $_POST[ 'broadcast' ] is not set.
* Fix: WPML plugin clears the WPML cache when posting.

= 2.15 20140123 =
* Fix: Menu order is now also broadcasted.

= 2.14 20140117 =
* New: Canonical URL detects and disables the canonical link from Yoast's Wordpress SEO plugin.
* Fix: Better compatability with User Blog Settings and Keep Child Settings plugins.
* Fix: Better support for Microsoft IIS.
* Removed obsolescence message - all of the features are needed by at least one person.

= 2.13 =
Skipping version 13.

= 2.12 20140112 =
* New: Maintenance: View Blog Access check added.
* New: All Blogs plugin is now available in the premium pack.
* Code: get_user_writable_blogs filter moved to priority 11.
* Code: $_POST is now emptied, not removed.
* WPML plugin will be obsoleted soon. A more functional premium plugin will be available.

= 2.11 20131218 =
* New: WordPress v3.8 support.
* New: Custom post types tab shows the available post types on the current blog.
* New: Per Blog Taxonomies plugin is now available in the premium pack.
* New: Permalinks plugin is now available in the premium pack.
* Fix: Nested broadcasting changes blogs to parent blog.
* Fix: Scripts and CSS get version numbers.

= 2.10 20131121 =
* New: Duplicate broadcast data check in maintenance.
* New: WooCommerce plugin is now available in the premium pack.
* Fix: Loading of CSS and JS from non-standard path.
* Fix: Check that the thumbnail is an image before setting it.
* Code: No more global broadcasting_data property.
* Code: broadcasting_data can be used as call stack.

= 2.9 20131113 =
* New: Add "blogs to hide" setting.
* New: Keep Child Status plugin is now available in the premium pack.
* Fix: Blog names are unescaped. No more weird HTML characters in the blog list.
* Code: broadcast array is no longer removed from the $_POST, due to copy by reference on some versions of PHP 5.4.

= 2.8 20131109 =
* New: Attachments are attributed to the original author, instead of the person doing the broadcasting.
* New: Database maintenace admin tab. Experimental. Make a backup first. Has help text.
* Code: Broadcast data table has unique row numbers.

= 2.7 20131101 =
* New: All-links in post overview.
* New: Linked posts can be deleted, trashed, restored and unlinked.
* Fix: Settings are ajaxified again.
* Fix: Group selection works again.
* Fix: No more warnings when using empty custom field blacklist / whitelists.
* Advanced Custom Fields plugin is now available in the premium pack.

= 2.6 20131028 =
* Fix: Broadcasted featured image is attached to post if necessary.
* Fix: List of attachments when broadcasting is not allowed to be cached anymore.
* Fix: Neverending loop fixed when "parent permalinks" is checked.
* Code: prepare_broadcasting_data is now an action.
* Code: last_used_settings copied into meta_box_data.
* Code: broadcasting_data->new_post is newly created for each child blog.

= 2.5 20131024 =
* Fix: No more fatal errors when editing child posts.
* Fix: Finding orphans works again.

= 2.4 20131018 =
* Fix: Selected blogs are shown after selecting a group.
* Fix: Better JS and CSS loading for subdirectory installs.
* Fix: Allow editing of slugs when overriding child permalinks.
* Fix: Child deletion link doesn't delete twice.
* New: Support to hide broadcast box, menu, columns. See User & Blog Settings plugin.

= 2.3 20131011 =
* New: Caching of BroadcastData speeds up the post overview.
* Fix: More than one blog group is visible.
* Code: prepare_meta_box action added. added_meta_box action removed.

= 2.2 20131007 =
* New: Custom field exceptions are now separated into a blacklist and a whitelist.

= 2.1 20131007 =
* Fix: Don't display broadcast meta box if no access, only access to 1 blog.

= 2.0 20131006 =
* Removed: Unecessary role to create taxonomies, which are now automatically created and synced.
* Removed: Blacklisted blogs - replaced with per-blog per-user functionality in Premium pack.
* Removed: Required blogs - replaced with per-blog per-user functionality in Premium pack.
* Fix: Category broadcast bug
* Code: Large rewrites. New actions and filters.
* Code: Minified css.
* Code: Modernized user.js.
* Code: $plugin_version now matches the actual version.

= 1.32 20131003 =
* Fix: Broadcasting of featured images, attachments and galleries works much better.

= 1.31 20130929 =
* Fix: Taxonomies are sometimes missed if uncategorized is used.

= 1.30 20130927 =
* Fix: Fixed bug that sometimes skipped some blogs, at random, when broadcasting.

= 1.29 20130926 =
* Users are requested to check their post type and custom field exception settings after upgrading.
* Fix: Converted array settings to strings, which fixes the foreach() error on line 1544.
* Fix: Posts can be trashed from the parent post overview.

= 1.28 20130924 =
* Fix: Category matching uses more fuzzy searching. Fixes WP_Error on line 1850.

= 1.27 20130923 =
* New: Override child post permalinks works with custom post types.
* New: Permalink cache when overriding child permalinks. Speeds up looking up the same post during a page view.
* WPML plugin version bump.

= 1.26 20130915 =
* Fix: Featured image broadcast works again.
* WPML plugin version bump.

= 1.25 20130905 =
* Code: Fixed Broadcast column in posts view.

= 1.24 20130904 =
* Code: Fixed typo.

= 1.23 20130903 =
* Fix: Image broadcasting works again.
* Fix: Gallery shortcodes are updated.

= 1.22 20130813 =
* Fix: Unlinking works. Again.
* Code: Removed network_admin_menu code.

= 1.21 2013-08-12 =
* New: WPML support plugin added.
* Fix: Moved Broadcast settings to the blog's general settings.
* Fix: Unlinking works again.
* Fix: Add PHP v5.4 version check.
* Code: Added broacast_post() method.
* Code: Added actions.
* Code: Added broadcasting actions.
* Code: More documentation for Broadcasting_Data object, together with refactoring of save_post cost.

= 1.20 2013-06-02 =
* Fix: Attachments should be properly broadcast now.
* Code: Added Broadcasting_Data.php.
* Code: Added $threewp_broadcast->broadcasting_data when broadcasting.
* Code: Most methods have been made public.
* Code: Refactoring and cleanup.
* Code: Tabs, SDK update.
* Still requires PHP v5.4. Ask your web host to update.

= 1.19 2013-05-01 =
* Fix: Trying to add a taxonomy term that already exists at the target blog. Thanks: https://github.com/alisspers
* Fix: Using new wp_trash_post hook when deleting [child] posts. Thanks: https://github.com/alisspers
* Code: Using plainview_sdk. Requires php v5.4 (because of traits).

= 1.18 2013-02-22 =
* New: Option to disable overriding of canonical URLs. Used if other plugins also manipulate the url in the HTML head.
* New: Private posts can be broadcast.
* Fix: Broadcasting of attachments works better. Galleries are also broadcasted (due to editing of the gallery shortcodes).

= 1.17 2013-02-15 =
* New: Children have their canonical links pointed to the parent.

= 1.16 2013-02-14 =
* Fix: Titles and menu order of attached images are also broadcasted. Thanks to werk@haha.nl.

= 1.15 2013-02-13 =
* Fix: post type settings works again.

= 1.14 2013-02-12 =
* Fix: Taxonomies are checked recursively. Thanks to anders@webbgaraget.se.
* Fix: Arrow in broadcast box fixed

= 1.13 2013-01-05 =
* Fix: Compatability with WP 3.5 (roles work again).

= 1.12 2012-08-27 =
* Overridden child permalinks use the nice permalink instead of /?p=123
* Romanian translation from Alexander Ovsov
* Added extra Activity Monitor details, from patch from Flyn.

= 1.11 2012-02-11 =
* Fixed non-broadcasting bug. *sigh*

= 1.10 2012-02-20 =
* Italian added.
* Fixed double-posting bug when using required lists.

= 1.9 2012-02-18 =
* Bug fixed: post_link only receives one parameter.
* Bug fixed: double-posting when using an empty required list.
* Rebroadcast of old images now works (thanks Ross Hawkes).
* Broadcast menu moved to profile menu (for the sake of contributors).
* Better support for contributor roles.
* Better finding of orphan posts.

= 1.8 2012-02-10 =
* Old broadcasted images are properly deleted from child blogs.
* Settings can now be saved again. Sigh.

= 1.7 2012-02-08 =
* Featured Images work again.

= 1.6 2012-02-05 =
* Find orphans
* Bugs fixed that prevented proper broadcasting

= 1.5 2012-02-04 =
* Custom post and custom taxonomy support
* Multiple custom field values with the same key can be broadcasted
* Will not try to attach files that were deleted from disk
* Broadcasted images retain their ALT, TITLE and caption.
* New link icon
* New base php
* Better linking to child posts
* Post meta fields are maybe unserialized
* Fixed Activity Monitor support

= 1.4 =
* Works with WP 3.1
* Added a shrink / expand control in the broadcast meta box

= 1.3 =
* Category syncing works with unparented categories.
* Activity Monitor activities have types.
* $threewp_broadcast->is_broadcasting() is now available.
* Now even more links can be overrided.

= 1.2.1 =
* Custom field exceptions added
* Priority can now be 10 characters
* Category broadcast role is back again. :)

= 1.2 =
* Settings are kept when activating the plugin.
* Child posts are given link info.
* Child post permalinks can be overriden.
* Last used settings are remembered.
* Broadcasted children cannot be rebroadcasted.
* Page templates are broadcasted.
* Broadcasted categories are synchronized with the children.
* Broadcasted custom fields aren't duplicated anymore.
* Sticky status is broadcasted.
* Page parents are kept. The parent page must be broadcasted first.
* Drafts and future posts can also be broadcasted.

= 1.1 =
* Galleries are now broadcastable.

= 1.0 =
* Custom fields can be broadcast.
* Attached images can be broadcast.
* Broadcasted posts are now linked to the parent post.
* Pages can be broadcast.

= 0.3 =
* Dutch translation added, courtesy of Johan Daems.
* Superadmins have access to all blogs, even though they don't.

= 0.2 =
* WP3 compatability

= 0.0.1 =
* Initial public release

== Upgrade Notice ==

= 2.2 =

* Check that your custom field blacklists and whitelists are in good working order.

= 2.0 =

* Blacklist and whitelist have been removed. Their functionality will be replaced by a plugin.
* Blog grouping has been moved into a separate plugin. Requires that users recreate their blog lists.
* Plugin authors will want to look at the old and new filters and actions that Broadcast offers.

= 1.29 =

Users are requested to check their post type and custom field exception settings after upgrading.
