=== ThreeWP Broadcast ===
Tags: network, wpms, wpmu, broadcast, multipost, blogs, posting, simultaneously, child, parent, permalink, post type, custom post type, threewp, wpml, sitepress
Requires at least: 3.3.1
Tested up to: 3.6.1
Stable tag: trunk
Donate link: http://plainview.se/donate/
Contributors: edward_plainview, edward mindreantre

Network plugin for PHP v5.4 to broadcast posts to other blogs in the network. Custom post types, taxonomies, attachments and WPML are supported.

== Description ==

Network plugin for PHP v5.4 to broadcast posts to other blogs in the network. Custom post types, custom taxonomies, post meta and attachments are supported. A blog whitelist and blacklist exist. Includes a plugin that provides nominal support for WPML translations.

Broadcasted posts can be linked to their parents: updated parent posts also update the child posts. Child post permalinks can be overriden with a link to the parent post.

Last used settings are remembered between uses. Broadcasted categories keep their parents, as long as the parents already exist on the child blog of if the parent is broadcasted and created simultaneously as the child category.

Has options for whitelisting (required blogs), blacklisting, user role access granularity, custom post and custom taxonomy support and an uninstall to completely remove itself. No traces of the plugin are left (assuming the created tables are successfully removed).

Requires php v5.4 for trait support. Users of php v5.3 should remain with version 1.18.

Users of php v5.3 can only use the <a href="http://plainview.se/wp-content/uploads/2013/08/threewp-broadcast_v1.18.zip">latest legacy version: v1.18</a>.

Available in the following languages:

* Dutch
* English
* French - Seb giss <sgissinger@gmail.com>
* Italian
* French
* Romanian, Web Geek Sciense <a href="http://webhostinggeeks.com/">Web Hosting Geeks</a>
* Swedish

The git repository can be found at: https://github.com/the-plainview/threewp_broadcast

Did I miss anything?

== Installation ==

1. Check that your web host has PHP v5.4.
1. Activate the plugin locally or sitewide.

== Screenshots ==

1. Broadcast box during post editing, with WPML support line at bottom.
2. Post / page overview with unlink options
3. Admin: General settings
4. User: Help page
5. User: Group settings page
6. Admin: Post types settings page
7. Admin: Required blog list
8. Admin: Blacklist
9. Admin: Uninstall page

== Actions and filters ==

Broadcast offers some actions/filters for plugin developers with which to interact with Broadcast.

The broadcasting actions use the Broadcasting_Data class to store and transmit information. Documentation for this class can be read in `include\Broadcasting_Data.php`.

= action: threewp_broadcast_add_meta_box( $options ) =

Allows the posting meta box to be modified.

$options is a stdClass.
$options->post The post that is being edited.
$options->html The HTML of the meta box.

= action: threewp_brodcast_broadcasting_started( $Broadcasting_Data ) =

Fired just before the loop that broadcasts the post to each selected child blog.

= action: threewp_brodcast_broadcasting_after_switch_to_blog( $Broadcasting_Data ) =

Fired in the broadcast loop, after having switch to the child blog but not done any calculations or posting.

= action: threewp_brodcast_broadcasting_before_restore_current_blog( $Broadcasting_Data ) =

Fired in the broadcast loop, after the child post was created/updated, before restoring back to the original blog.

= action: threewp_brodcast_broadcasting_finished( $Broadcasting_Data ) =

Fired after the broadcast loop, before nulling all broadcast data and returning control to the Wordpress save_post function.

== Frequently Asked Questions ==

= I need support! =

The easiest way to get my attention is to donate a sum of money and then send me an e-mail.

If you're not into donations, try the support forum to see if other users can help you out.

= I need Broadcast customized or modified =

Get in touch with me via e-mail to discuss a deal.

= Is php v5.4 really necessary? =

Yes, if you expect me to write neat, maintainable, legible code.

If you use v5.3 then use the last plugin that works with that version: v1.18. The download link is on the main page.

= Why can I not see the Broadcast meta box? =

Make sure that:

1. The plugin is network enabled
2. Your user level has broadcast access (Broadcast access role)
3. Your user has write access to more than this blog
4. The correct post type(s) have been selected

= Orphans? =

If you have already created posts on other blogs that are supposed to be children of a specific post, you can use the "find orphans" function to find and link them.

Find the post in the post overview and use the row action "find orphans". You will then be presented with a table of possible orphans on each blog. Select the blog and then choose "link orphans" to create the links.

To be considered an orphan the orphaned posts must have the exact same title (name) as the soon-to-be parent.

= Broadcast existing pages in bulk =

Want to broadcast a bunch of existing pages at once? This function will be released after Broadcast 2.0.

As of today, ticket 16031, http://core.trac.wordpress.org/ticket/16031 , is causing the problem.

= Slugs =

Changed slugs (post names) are not updated in the child posts. This is a bug in Wordpress.

= WPAlchemy =

If you have custom post meta boxes via WPAlchemy, you'll probably need to add the following to the custom field exceptions in the settings:

_bcc_

= WPML Sitepress =

There is an included plugin, ThreeWP Broadcast WPML, that provides support for transferring WPML translation data between broadcasted posts.

It works transparently in the background, but in case you've never really used WPML (like myself), here's how I got it working:

1. Enable the Broadcast and Broadcast WPML plugins.
2. Write a new post in a language. Link and broadcast it to another blog in the network.
3. The new post should have the same language in the child blog(s).
4. In the parent blog, create a new translation of the post.
5. Link and broadcast it to the other blogs in the network.
6. The other blogs should now have two translations of the same post and the same post overview listing.

Translated categories and tags are untested as of 2013-07-17.

== Upgrade Notice ==

= 1.29 =

Users are requested to check their post type and custom field exception settings after upgrading.

== Changelog ==

= 1.32 20131004 =
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
