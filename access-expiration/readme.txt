=== Access Expiration ===
Contributors: Justin at Multinc
Donate link: http://multinc.com/wp/donate/
Tags: social privacy, social, privacy, private, protect, restrict, restriction, permit, permission, permissions, rights, allow, access, categories, post, url, expiration
Requires at least: 2.5
Tested up to: 2.6
Stable tag: 1.1

For each post or category, you can limit the number of views and the amount of time that an item remains 
viewable by each user after he or she first accesses the post.

== Description ==

The Access Expiration plugin is a plugin from Multinc that adds a level of access control for particularly sensitive posts.  For each post or category, you can limit the number of views and the amount of time that an item remains viewable by each user after he or she first accesses the post.  Unlike other plugins that expire posts for all users at the same time, this plugin keeps track of every user’s viewing history so that each gets equal access time.

You can also impose separate limits for unregistered users, who are all considered as one single user.

When used in combination with the other plugins in the [Social Privacy](http://wordpress.org/extend/plugins/social-privacy/) set, this plugin is useful for limiting the chance that any authorized user accidentally or deliberately forwards a private post to an unauthorized user.

Because authorized users may have legitimate reasons for accessing a post beyond the allotted time, the plugin gives users the simple option of requesting an extension of their viewing privileges.

== Installation ==

Install of installing only this plugin, we recommend that you install all the plugins in the [Social Privacy](http://wordpress.org/extend/plugins/social-privacy/) set to get full privacy control over WordPress.  To do that, follow these [installation](http://wordpress.org/extend/plugins/social-privacy/installation/) instructions.

If you still choose to install only this plugin, do the following:

1. [Download](http://downloads.wordpress.org/plugin/access-expiration.zip) the Access Expiration zip file.
1. Extract the zip file and copy the `access-expiration` directory to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' tab in WordPress
1. Configure in the admin interface of Wordpress:
- To set expiration on a single post, go to the 'Expiration' section in the Write/Manage Post pages.
- To set expiration on an entire category and for other global settings, go to the 'Settings' page in WordPress and then select 'Access Expiration'.

== Frequently Asked Questions ==

= How am I notified of pending requests for access renewal? =

If there are any pending requests for access renewal, the author will see a numerical balloon on top of the 'Access Requests' tab.
And if enabled in the global settings, the author will also be notified by email.

= How do I renew access requests? =

In the admin interface of WordPress, go to the 'Access Requests' tab.

== Screenshots ==

1. The expiration settings page for entire categories, which also holds global settings
2. The expiration settings page for individual posts
3. The page to accept or reject requests for access renewal
