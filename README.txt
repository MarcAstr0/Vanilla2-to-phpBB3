This script and resource files are used to convert pre-existing forums from Vanilla 2, 
version 2.0.18.4, to a newly installed, and slightly modified, version 3.0.11 of phpBB3 Forums.

*** You MUST have the following before running this conversion script! ***

First, you need a working Vanilla 2.x as well as a phpBB3 installation 
Second, you will need the "Alternate Logins" Modification, as well as AutoMod for installing it.
Thirdly, the folder containing this file and conversion script files should be placed in the root directory of the phpBB3 installation prior to running this conversion.

This script is not an Official phpBB3 Conversion script, and cannot be used while installing a new board. 
It must be run from a folder within the root directory of phpBB3, after a fresh installation and modification!

In addition to the above requirements, the ADODB database abstraction library is needed if you plan to use this script as is.
The ADODB library supplies the database object required to connect to Vanilla's database.  The API in phpBB3 is used for connection to the phpBB3 database, as well as some modified functions to insert the converted data.

* Post Conversion Notes:
After having built and tested this script on a decent stock of data, I found conversion to be "good enough" and went ahead and converted my forums.
The script obviously had some bugs, none are too serious, most have to do with conversion of links and smilies from HTML, which can hardly be helped.  
There was one outstanding issue with how the conversion generated the forums in PHPBB3 and their ordering numbers, I had to re-number the forum/category order numbers via the database by one or two in some spots.  That may have to do with how I handled the hidden forum, or just a bad starting number, I didn't document the issue right away as I should have.

All in all, despite the one outstanding issue, conversion when rather well.  All the data, forums/categories, posts, private messages, and of course the users were transfered without any hicups. 
Our users are happy with their new, functioning, forums. :)

Any and all contributions to these scripts is more than welcome, and encouraged.
