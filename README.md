Vanilla2-to-phpBB3
===
Instructions:
---
1. Switch to the www/ directory of the destination phpBB3 installation.  
1. Clone the importer script repo:  
    $ git clone https://github.com/mike-teehan/Vanilla2-to-phpBB3.git  
1. Switch to the freshly created Vanilla2-to-phpBB3 directory  
    $ cd Vanilla2-to-phpBB3  
1. Execute the following command to initialize and retrieve the ADOdb library submodule:  
    $ cd ADOdb && git submodule init && git submodule update && cd ..  
1. Edit common.php with the DB info for the Vanilla2 board you want imported.  
1. Open a web browser and point it at: http://{$your_site_here}/Vanilla2-to-phpBB3/van2phpbb.php
1. Wait. The script is very slow. Give it time.  When it is finished, it will print out a bunch of errors that can hopefully be ignored.

Post-conversion:
---
1. The imported forums will be parented to the Example Forum. They will need to be reorganized with the ACP.
2. Standard Users will not have any permissions on the imported forums. This can also be corrected with the ACP.
