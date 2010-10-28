=== ThreeWP Broadcast ===
Tags: network, wpms, wpmu, broadcast, multipost, blogs, posting, simultaneously
Requires at least: 3.0
Tested up to: 3.0
Stable tag: trunk

Network plugin to broadcast a post/page to other blogs. Whitelist, blacklist, groups and automatic category+tag posting/creation available.

== Description ==

Network plugin to broadcast a post to other blogs. Whitelist, blacklist, groups and automatic category+tag posting/creation available. All attached images are reposted to the selected blogs.

Broadcasted posts can be linked to their parents: updated parent posts also update the child posts.

Inspired by <a href="http://wordpress.org/extend/plugins/broadcast-mu/">Tom Lynch's Broadcast MU plugin</a> that doesn't work nowadays due to autosaving and such.

Has options for whitelisting (required blogs), blacklisting, user role access granularity, category and/or tag posting (if the blogs have the same category slugs) and category/tag creation (automatically, per user role) and an uninstall to completely remove itself. No traces of the plugin are left (assuming the created tables are successfully removed).

Available in the following languages:

* English
* Swedish
* Dutch

Did I miss anything?

== Installation ==

1. Unzip and copy the zip contents (including directory) into the `/wp-content/plugins/` directory
1. Activate the plugin sitewide through the 'Plugins' menu in WordPress.

== Screenshots ==

1. Broadcast box during post editing
1. Post / page overview with unlink options
1. Group editing
1. More group editing
1. Site admin settings
1. Required list settings
1. Blacklist settings
1. Uninstall

== Changelog ==
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
