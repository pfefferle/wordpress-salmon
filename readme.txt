=== Salmon ===
Contributors: pfefferle
Donate link: https://notiz.blog/donate/
Tags: diso, OStatus, Mastodon, Diaspora, federated, StatusNET, Gnu.Social, Salmon, Comments
Requires at least: 3.0
Tested up to: 4.9.3
Stable tag: 0.9.1
License: MIT
License URI: http://opensource.org/licenses/MIT

Salmon for WordPress

== Description ==

*This is a very early state of a salmon-plugin for WordPress. There are still some bugs and problems... Please
let me know if you found some.*

> As updates and content flow in real time around the Web, conversations around the content are becoming increasingly fragmented into individual silos.  Salmon aims to define a standard protocol for comments and annotations to swim upstream to original update sources -- and spawn more commentary in a virtuous cycle.  It's open, decentralized, abuse resistant, and user centric.

You can find more informations about Salmon here: http://www.salmon-protocol.org/

The plugin currently only supports receiving Salmons, but I am working on a bidirectional version.

This plugin requires the following plugins:

* the `host-meta`-plugin: <https://wordpress.org/plugins/host-meta/>
* the `webfinger`-plugin: <https://wordpress.org/plugins/webfinger/>

and I recommend to use it in combination with OStatus: <https://wordpress.org/plugins/ostatus-for-wordpress/>


== Changelog ==

Project and support maintained on github at [pfefferle/wordpress-salmon](https://github.com/pfefferle/wordpress-salmon).

= 0.9.1 =

* Fixed E-Mail spamming problem

= 0.9.0 =

* general refactoring
* refactored the code to use openssl instead of custom PHP code
* simpler avatar code
* fixed admin pages

= 0.5 =

* version problems

= 0.4.1 =

* fixed feed links (thanks to Stephen Paul Weber)

= 0.4 =

* some changes

= 0.3 =

* fixed rss bug
* WordPress 3.1 fixes

= 0.2 =

* fixed "custom comment types"
* added mailer

= 0.1 =

* Initial release

== Installation ==

1. Upload and install the `salmon`-plugin
2. Install all dependencies through the `salmon`-settings-page

== Frequently Asked Questions ==

The FAQ is taken from the Salom Protocol homepage: <http://www.salmon-protocol.org/faq>

= How does Salmon deal with abuse and spam? =

Early Internet protocols and systems were vulnerable to trivial forgery, and fixing this after the protocols were widely deployed is painful, partial, and slow.  On the other hand, it's also possible to add too much security and make a protocol too restrictive or too complex for large-scale adoption.

Salmon tries to find a good balance.  Salmon adopts a defense-in-depth strategy, assumes that there will be abuse and spammers, but builds in protocol hooks to make an active in-depth defense both cheap and effective with few false positives.

Specifically, spammers can not forge signatures from legitimate users, and it is very hard for them to mint massive numbers of fake identities without detection.  So as a first line of defense, anything whose authorship check fails, is simply dropped.  We're building this in from the start so that we'll never have an installed base of clients doing things insecurely.

Salmon disallows completely anonymous and untraceable messages.  It requires pseudonyms from verifiable identity providers.  This feature allows receivers to rate limit the messages per hour per pseudonym and per identity provider.  (Identity providers themselves have verifiable identities and can gain or lose reputation based on what their users do; every identity provider has to at least have a domain name, raising the bar for spammers significantly, and meaning that simple rate limiting can force the spammers to acquire and "burn" many domain names.)

Assuming wide deployment, we expect that spammers will attack Salmon with the same vigor they throw at email.  However, Salmon implementors can cheaply and easily deal with the simpler techniques the spammers currently use to inject email.  They will also mount active defenses based both on the years of experience in spam-blocking email and the new tools (guaranteed identity, identity providers, distributed reputation) that Salmon enables.

All of this is possible to do, and has been done, with closed systems.  Salmon provides a way to do this with an open protocol that anyone can implement and anyone can interoperate with.

= Does Salmon support threading? =

Yes, the thr:in-reply-to ID can be the ID of a comment (which can itself be a Salmon with a xpost:source element that points at the ultimate source).  While the protocol supports threading, recipients can of course flatten salmon in the feeds that they re-publish -- though the thr:in-reply-to element can be used to reconstitute threads as needed.

= How is Salmon different from using AtomPub to post to a comment feed? =

Salmon is in fact based on and compatible with AtomPub. Salmon greatly enhances interoperability and usability by specifying a distributed identity mechanism for identifying the author and intermediary involved, provides a discovery mechanism, and specifies how things should be linked together.  By not requiring pre-registration or federation but still allowing for verifiable identification, it provides a usable, level playing field for all parties involved.

= Why are you using this new crosspost extension instead of just using, and retaining, atom:id? =

See <http://wiki.activitystrea.ms/Cross-Posting>.

= What else is Salmon useful for? =

The payload can define any kind of message, so Salmon can be extended in arbitrary ways.  It is already being used to communicate mentions to mentionees.  It has also been used to signal cross-site following (by sending a follow message) or to send requests (by sending requests rather than notifications, e.g., a friend request).  It is best used when there is not necessarily predefined relationship or subscription between the source and destination; if there is, PubSubHubbub may be a better choice.
