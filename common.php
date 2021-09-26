<?php

//PHP runtime config.
error_reporting( E_ALL );
ini_set("display_errors", 1);

//Config Settings:
$config=array();
$config['db']['type'] = 'mysqli';     // access layer used by ADODB to connect to databse.
$config['db']['host'] = 'localhost';  // Host name or "hostname:port".
$config['db']['name'] = '';           // Database name.
$config['db']['user'] = '';           // Authentication Name.
$config['db']['pass'] = '';           // Authentication password.

//Import system function libraries.
require_once( './ADOdb/adodb-exceptions.inc.php' );
require_once( './ADOdb/adodb-errorhandler.inc.php' );
require_once( './ADOdb/adodb.inc.php' );

//Create and test the connection to the databse.
try
{
    $vdb = ADONewConnection($config['db']['type']); 
    $vdb->Connect($config['db']['host'], $config['db']['user'], $config['db']['pass'], $config['db']['name']);
    
    $vdb->SetFetchMode(ADODB_FETCH_ASSOC); // Modes:  ADODB_FETCH_NUM, ADODB_FETCH_ASSOC
    //$db->tblPrefix = $db_prefix;
    //$db->debug = true;
}
catch( Exception $e )
{
    echo "Error Establishing Connecting To Database!"; //$e->getMessage();
    exit();
}
unset( $config['db'] );


?>
