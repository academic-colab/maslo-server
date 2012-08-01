MASLO store 
===========

About:
-------
The MASLO store contains all the software needed to receive and serve MASLO 
content packs on any web server with PHP5/SQLite3/Python2 support.
Additionally uploaded content packs may be stored to and received from 
an AWS S3 instance. 

Getting started: 
----------------
If you do not plan to use Amazon AWS's S3, you should be good to go. If you do
have an S3 instance and plan to, copy s3sdk/config-sample.inc.php into 
s3sdk/config.inc.php and enter your AWS credentials ('key'  and 'secret' 
is what you primarily care about).
Additionally, edit config.json and set 'wantS3' to 'true', and enter your 
bucket/base directory information. The base directory is only needed to the 
extent that you may want to choose a common prefix for your content packs.
The bucket needs to already exist in your S3 instance (note: bucket names are
globally unique).

Requesting admin/index.php for the first time will initiate all necessary databases
and files.

You're good to go.

