=== Social Privacy ===
Contributors: Justin at Multinc
Donate link: http://multinc.com/wp/donate/
Tags: social privacy, social, privacy, private, protect, restrict, restriction, permit, permission, permissions, rights, allow, access, categories, post, feed, url, email, expiration
Requires at least: 2.5
Tested up to: 2.6.3
Stable tag: 1.1

Social Privacy is a set of open-source plugins for WordPress that restrict the read access of posts or categories to only specified registered users. Install these plugins and you can start blogging for friends and family about topics that are too sensitive to publish publicly.

Some social networks like Facebook support user blogs with extensive privacy control.  Social Privacy grants bloggers an even greater level of privacy control, outside of these walled gardens -- right here in the blogosphere. 

== Description ==

Social Privacy is a set of open-source plugins for WordPress that restrict the read access of posts or categories to only specified registered users. Install these plugins and you can start blogging for friends and family about topics that are too sensitive to publish publicly.

Some social networks like Facebook support user blogs with extensive privacy control.  Social Privacy grants bloggers an even greater level of privacy control, outside of these walled gardens -- right here in the blogosphere. 

= Summary of Features =

Which posts can be restricted?

* individual posts
* categories of posts (multiple categories are supported)

To whom can posts be restricted?

* all registered users
* specific registered users

Which access method is controlled?

* web site
* syndicated news feed (RSS or ATOM)
* email

For protected posts, what will users see in their syndicated feeds?

* authorized users will see either the full post or a summary (with a link to the full post on the web)
* unauthorized users will see only a title or not even see that there is a new post

For public posts or for protected posts, what email content can be sent to authorized users?

* full post
* excerpt (with a link to the full post on the web)
* title only (with a link to the full post on the web)
* no email

Security usually increases inconvenience for users.  What convenience features are supported to balance the additional security?

* automatic user recognition and authentication when the user clicks in the syndicated feeds to go the blog post on the website
* the same for emails

= Advanced Features =

How can one minimize the chance that unauthorized users be able to read protected posts forwarded to them by authorized users?

* a warning message can be included in every web page, feed item, or email for protected posts discouraging permitted users from forwarding
* feed items and email notifications can include limited content, forcing authorized users to go to the web to read the full post, which is less trivial to forward
* authorized users can be forced to enter their full login and password when reading the full post on the web, by disabling the automatic user recognition feature
* the number of times that any specific user can view a post or certain categories of posts can be limited.  Setting this to "1 maximum view" while enabling the automatic user recognition feature can provide a particularly good balance of security vs. convenience
* similarly to the above, the amount of time that any specific user can view a post or certain categories of posts again can also be limited

= Sub-plugins in the Set =

The following 5 plugins have been created or modified to work in unison to give you complete control over access to the posts in your WordPress installation.  If installed individually, some of these plugins provide significant functionality on their own, but their best features are enabled when installed as one set.

## [Social Access Control](http://wordpress.org/extend/plugins/social-access-control/) (based on [Category Access](http://www.coppit.org/blog/archives/173)) ##

Based on the 3rd-party Category Access, this plugin provides the core functionality for restricting the access permissions of posts.  This gives you the ability to permit only specific registered users to read certain posts or certain categories of posts.

## [Subscribe2 for Social Privacy](http://wordpress.org/extend/plugins/subscribe2-for-social-privacy/) (based on [Subscribe2](http://wordpress.org/extend/plugins/subscribe2/)) ##

The 3rd-party Subscribe2 plugin allows registered and unregistered users to be notified via email whenever a new post is published.  It allows users to subscribe to either full posts or excerpts of posts, to choose whether the format of full posts should be HTML or plain-text, and to select which categories they are interested in.

Subscribe2 was enhanced to work with Social Access Control so that users receive emails only for posts or categories of posts that they are permitted to view.  Also, the administrator can choose additional email content levels depending on whether the post is public or restricted: full post, excerpt, title only, no email.

## [Private Files for Social Privacy](http://wordpress.org/extend/plugins/private-files-for-social-privacy/) (based on [Private Files](http://wordpress.org/extend/plugins/private-files/)) ##

The 3rd-party Private Files plugin tightens the security of private posts by preventing clever users from obtaining unauthorized access to contained images and other attachments.

Private Files was enhanced to respect the access permissions of the Social Access Control plugin.

## [Unique URL Authentication](http://wordpress.org/extend/plugins/unique-url-authentication/) ##

The Unique URL Authentication plugin is a new plugin from Multinc that works with the Social Access Control plugin to add access control to syndicated feeds.  Since popular web feed readers such as Google Reader do not support private feeds that require a login and a password, a common solution is to give each registered user their own unique and unguessable feed URL.  With this plugin installed, each user will have his or her own feed and will receive a listing of only the feed items that he or she has access to.

Also, the links contained in each feed item and each email notification that point back to the blog website can also benefit from password-less authentication.  This allows sensitive posts to remain on the website rather than sent out, while giving authorized users single-click convenience.

## [Access Expiration](http://wordpress.org/extend/plugins/access-expiration/) ##

The Access Expiration plugin is another new plugin from Multinc that adds another level of access control for particularly sensitive posts.  For each post or category, you can limit the number of views and the amount of time that an item remains viewable by each user after he or she first accesses the post.  Unlike other plugins that expire posts for all users at the same time, this plugin keeps track of every user's viewing history so that each gets equal access time.  This plugin is useful for limiting the chance that any authorized user accidentally or deliberately forwards a private post to an unauthorized user.

Because authorized users may have legitimate reasons for accessing a post beyond the allotted time, the plugin gives users the simple option of requesting an extension of their viewing privileges.

== Installation ==

There are two ways to install the plugins that make up Social Privacy.  The plugins can be installed as one set from this page, or one at a time by visiting each plugin's web page on wordpress.org (the links are on the [Description](http://wordpress.org/extend/plugins/social-privacy/) page).

We recommend you install the entire set, as described here:

1. Make sure you deactivate any existing installation of the following plugins: [Category Access](http://www.coppit.org/blog/archives/173), [Subscribe2](http://wordpress.org/extend/plugins/subscribe2/), [Private Files](http://wordpress.org/extend/plugins/private-files/).
1. [Download](http://downloads.wordpress.org/plugin/social-privacy.zip) the Social Privacy zip file containing all the plugins in the set.
1. Extract the zip file and copy all 5 of the plugins sub-directories individually into the `/wp-content/plugins/` directory of your WordPress installation.  Don't just copy the parent directory `social-privacy`; it must be the 5 sub-directorires.
1. Activate each plugin through the 'Plugins' menu in WordPress.

= Configuration =

Configure each plugin through the 'Settings' menu in WordPress:
* [Social Access Control](http://wordpress.org/extend/plugins/social-access-control/installation/)
* [Subscribe2 for Social Privacy](http://wordpress.org/extend/plugins/subscribe2-for-social-privacy/installation/)
* [Private Files for Social Privacy](http://wordpress.org/extend/plugins/private-files-for-social-privacy/installation/)
* [Unique URL Authentication](http://wordpress.org/extend/plugins/unique-url-authentication/)
* [Access Expiration](http://wordpress.org/extend/plugins/access-expiration/installation/)

== Frequently Asked Questions ==

= What happens to the settings and data of the 3rd-party base plugins if they are replaced? =

You may have previously used one ore more of the following original plugins: [Category Access](http://www.coppit.org/blog/archives/173), [Subscribe2](http://wordpress.org/extend/plugins/subscribe2/), or [Private Files](http://wordpress.org/extend/plugins/private-files/).  If you only deactivate them but do not delete their settings or data from your WordPress database, Social Privacy will use and take over these settings and data.  Although not yet tested, the transition should be seamless

== Screenshots ==

1. Screenshot of Social Access Control
2. Screenshot of Access Expiration
3. Screenshot of Subscribe2 for Social Privacy
4. Screenshot of Private Files for Social Privacy
5. Screenshot of Unique URL Authentication
