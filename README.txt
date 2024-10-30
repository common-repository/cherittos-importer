=== Cheritto's Importer ===
Contributors: cheritto
Donate link: https://paypal.me/fiulita
Tags: import, xml import, wxr import, importer, xml importer, wxr importer
Requires at least: 6.0
Tested up to: 6.5.2
Requires PHP: 7.0
Stable tag: 1.0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.htm

Safely import posts, comments, pages, categories, tags and more from Wordpress Export files!

== Description ==

Cheritto's Importer is a tool that let you import data from one or more Wordpress Exported xml files (wxr).

* Files are uploaded in chunks, whose size is determined according to your current php settings, so you don't have to worry about them!
* File checking, import, attachments downloads and thumbnails generation are done asynchronously, so you don't have to worry if you close the page!
* Files are processed in chunks so you don't have to worry about memory usage and large files!
* Supports tag, category and authors import, avoiding content duplication!

== Installation ==

Just activate the plugin, you'll find a new admin menu page on the left: "Importer!". Enjoy!

== Screenshots ==

1. Phase 1 - File Upload
2. Phase 2 - Check files
3. Check is running
4. Phase 3 - Import data!
5. Import is running
6. Phase 4 - Download attachments

== Issues ==
If you find issues using this plugin please send feedback, I'll do my best to fix asap.

== Changelog ==

= 1.0.1 =
* Bugfix.

= 1.0.0 =
* First release.

== Frequently Asked Questions ==

= Will the import alter in some way my posts, pages or any data in the current Wordpress installation? =

No, the plugin will only add new data.

= Can i import multiple files at once? =

Yes, as long as they belong to the same export job (usually, multiple files come from an export done via 'wp cli'). 
If they do not come from the same export job, you will have to go through the import procedure for each xml file.

= Will the plugin duplicate my content? =

You can choose to import all as is or to check if posts or pages with same title, date and type already exist, in which case they won't be imported. 
In any case, authors, categories and tags will never be duplicated; only added if not existing.

= How are authors imported? =

The importer will create new users with role 'author' if no user exists with the same email or nicename. 
A random password will be generated, authors have to reset this password to login.

= Why the import phase is faster than the checking one? =

During the check phase, all files are read and parsed, and data converted in database tables, while during the import phase, data is transferred only between database tables. 
The difference in speed is more evident with large file(s).

= Why the download phase is slow? =

During the download phase, the importer must make a request and save the image. 
Depending on the ability of the called server to handle requests, it may take a while to complete for a large number of images.

= The importer page says 'Check is running'. How can i be sure that the job is not stuck? =

The importer page uses and independent check to verify that the job is running and it does so at every page load, so if you see 'Check is running' it means that all is going fine. 
If this check fails for more than a minute, you will see a red warning.

= I see a red warning alerting me that the job is stuck. What should i do? =

Something has stopped the running job. It may be a server side issue (for example your hosting not allowing long running tasks) or an error. 
In either case, you should cancel the job clicking on 'Cancel job' and retry from start. If the issue persists, open a topic on the support forum or email me: fiulita@gmail.com (i will likely need your xml file(s) in order to check the issue). 
