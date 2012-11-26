<?php
/******************************************************************************
 * util.php
 *
 * Copyright (c) 2011-2012, Academic ADL Co-Lab, University of Wisconsin-Extension
 * http://www.academiccolab.org/
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301  USA
 *****************************************************************************/
/*
 *  @author Cathrin Weiss (cathrin.weiss@uwex.edu)
 */
require_once '../s3sdk/sdk.class.php';

class MyDB extends SQLite3
{
    function __construct($path)
    {
        $this->open($path);
    }

	function closeDB() {
		$this->close();
	}
}

/*** 
 * Authenticate admin user
 * $userName: the user name
 * $password: user password sha256'ed with the sha256'ed session id
 * returns: true, if user credentials are valid, false otherwise
 */
function verifyUser($userName, $password){
	$db = new MyDB("admin.db");
	$query = "SELECT upass FROM users where uname == :id";
	$stmt = $db->prepare($query);
	if ($stmt){
		$stmt->bindValue(':id', $userName, SQLITE3_TEXT);
		$result = $stmt->execute();
		$resultRows = $result->fetchArray(SQLITE3_ASSOC);
		if ($resultRows) {
			$pass = $resultRows["upass"];
			$pass = $pass.hash('sha256',hash('sha256',session_id()));
			$pass =  hash('sha256',$pass);
			$db->closeDB();
			if ($pass == $password) {
				return true;
			}
		}
		return false;
	}
	return false;
}

function preparePreview($title) {
	$t = str_replace('"', '\"', str_replace('`', '\`', str_replace('´', '\´', $title)));
	$cmd = 'mkdir "../uploads/tmp/preview-'.$t.'"';
	exec($cmd);
	$db = new MyDB('../uploads/search.db');
	$query = "SELECT public,path FROM content WHERE pack == :id";
	$stmt = $db->prepare($query);
	$public = -1;
	$path = "";
	if ($stmt){
		$stmt->bindValue(':id', $title, SQLITE3_TEXT);
		$result = $stmt->execute();
		$resultRows = $result->fetchArray(SQLITE3_ASSOC);
		while ($resultRows){
			$public = $resultRows["public"];
			$path = $resultRows["path"];
			$resultRows = $result->fetchArray(SQLITE3_ASSOC);
		}
	} else {
		$db->closeDB();
		return false;
	}
	$db->closeDB();
	if ($public < 0){
		return false;
	}
	
	$s3ConfigStream = file_get_contents("../config.json");
	$s3Config = json_decode($s3ConfigStream, true);
	$bucket = $s3Config["bucket"];
	$baseDir = $s3Config["baseDir"]."/";
	if ($public == 0)
		$baseDir = "qDir-".$baseDir;
	if ($s3Config["wantS3"] == "true"){
		$s3 = new AmazonS3();
		$response = $s3->get_object_list($bucket, array(
		   'prefix' => $baseDir.$title
		));
		foreach ($response as $v) {
		    $pos = strpos($v, ".zip");
			if ($pos > 0) {
				
			}
		}
	} else {
		$path = str_replace('"', '\"', str_replace('`', '\`', str_replace('´', '\´', $path)));
		$zipDir = $path;
		if ($public == 0){
			$zipDir = "qDir-".$zipDir;			
		}
		$zipDir = "../".$zipDir;
		$cmd = 'unzip -d "../uploads/tmp/preview-'.$t.'" "'.$zipDir.'"';
		exec($cmd);
		
	}
	return true;
}

function removePreview($title){
	$t = str_replace('"', '\"', str_replace('`', '\`', str_replace('´', '\´', $title)));
	$cmd = 'rm -rf "../uploads/tmp/preview-'.$t.'"';
	exec($cmd);
	return true;
}

/*** 
 * Delete content pack
 * $title: Content pack title
 * returns: true, if pack successfully deleted, false otherwise
 */
function deleteData($title, $isPublished){
	if (isset($_SESSION['init'])){
	$db = new MyDB('../uploads/search.db');
	$query = "DELETE FROM content where pack == :id";
	$query2 = "DELETE FROM content_search where pack == :id";
	$stmt = $db->prepare($query);
	if ($stmt){
		$stmt->bindValue(':id', $title, SQLITE3_TEXT);
		$stmt->execute();
	} else {
		$db->closeDB();
	}
	$stmt = $db->prepare($query2);
	if ($stmt){
		$stmt->bindValue(':id', $title, SQLITE3_TEXT);
		$stmt->execute();
	} else {
		$db->closeDB();
	}
	$db->closeDB();
	$s3ConfigStream = file_get_contents("../config.json");
	$s3Config = json_decode($s3ConfigStream, true);
	$bucket = $s3Config["bucket"];
	$baseDir = $s3Config["baseDir"]."/";
	if (!$isPublished)
		$baseDir = "qDir-".$baseDir;
	if ($s3Config["wantS3"] == "true"){
		$s3 = new AmazonS3();
		$response = $s3->get_object_list($bucket, array(
		   'prefix' => $baseDir.$title
		));
		foreach ($response as $v) {
		    $s3->batch()->delete_object($bucket, $v);
		}						
		$s3->batch()->create_object($bucket, $s3Config["baseDir"]."/search.db", array(
			'fileUpload' => '../uploads/search.db'
		));
		$file_upload_response = $s3->batch()->send();
	} else {
		$t = str_replace('"', '\"', str_replace('`', '\`', str_replace('´', '\´', $title)));
		$cmd = 'rm -rf "../qDir-uploads/'.$t.'"';
		if ($isPublished)
			$cmd = 'rm -rf "../uploads/'.$t.'"';
		exec($cmd);
	}
	return true;
	}
	return false;
}

/*** 
 * Publish content pack
 * $title: Content pack title
 * returns: true, if pack successfully published, false otherwise
 */
function publishData($title){
	if (isset($_SESSION['init'])){
	$db = new MyDB('../uploads/search.db');
	$query = "UPDATE content SET public = 1 where pack == :id";
	$stmt = $db->prepare($query);
	if ($stmt){
		$stmt->bindValue(':id', $title, SQLITE3_TEXT);
		$stmt->execute();
	}
	$db->closeDB();
	$s3ConfigStream = file_get_contents("../config.json");
	$s3Config = json_decode($s3ConfigStream, true);
	$bucket = $s3Config["bucket"];
	$baseDir = $s3Config["baseDir"]."/";
	if ($s3Config["wantS3"] == "true"){
		$oldFn = 'qDir-'.$baseDir.$title;
		$newFn = $baseDir.$title;
		$s3 = new AmazonS3();
		$response = $s3->get_object_list($bucket, array(
		   'prefix' => $oldFn
		));
		foreach ($response as $v) {
			$v2 = str_replace($oldFn, "", $v);
			$s3->copy_object(array('bucket' => $bucket, 'filename' => $oldFn.$v2), array('bucket' => $bucket, 'filename' => $newFn.$v2),array('acl'  => AmazonS3::ACL_PUBLIC));
			$s3->batch()->delete_object($bucket, $v);
		}			
				
		$file_upload_response = $s3->batch()->send();		
		$s3->create_object($bucket, $baseDir."search.db", array(
			'fileUpload' => '../uploads/search.db'
		));
	} else {
		$t = str_replace('"', '\"', str_replace('`', '\`', str_replace('´', '\´', $title)));
		$res = rename("../qDir-uploads/".$title, "../uploads/".$title);
		if (!$res){
			rename("../qDir-uploads/".$title."/contents.zip", "../uploads/".$title."/contents.zip");
			rename("../qDir-uploads/".$title."/manifest", "../uploads/".$title."/manifest");
			$cmd = 'rm -rf "../qDir-uploads/'.$t.'"';
			exec($cmd);
		}
	}
	return true;
	}
	return false;
}

/*** 
 * Unpublish content pack
 * $title: Content pack title
 * returns: true, if pack successfully unpublished, false otherwise
 */
function unPublishData($title){
	if (isset($_SESSION['init'])){
	$db = new MyDB('../uploads/search.db');
	$query = "UPDATE content SET public = 0 where pack == :id";
	$stmt = $db->prepare($query);
	if ($stmt){
		$stmt->bindValue(':id', $title, SQLITE3_TEXT);
		$stmt->execute();
	}
	$db->closeDB();
	$s3ConfigStream = file_get_contents("../config.json");
	$s3Config = json_decode($s3ConfigStream, true);
	$bucket = $s3Config["bucket"];
	$baseDir = $s3Config["baseDir"]."/";
	if ($s3Config["wantS3"] == "true"){
		$newFn = 'qDir-'.$baseDir.$title;
		$oldFn = $baseDir.$title;
		$s3 = new AmazonS3();
		$response = $s3->get_object_list($bucket, array(
		   'prefix' => $oldFn
		));
		foreach ($response as $v) {
			$v2 = str_replace($oldFn, "", $v);
			$s3->batch()->copy_object(array('bucket' => $bucket, 'filename' => $oldFn.$v2), array('bucket' => $bucket, 'filename' => $newFn.$v2),array('acl'  => AmazonS3::ACL_PUBLIC));
			$s3->batch()->delete_object($bucket, $v);
		}			
				
		$file_upload_response = $s3->batch()->send();		
		$s3->create_object($bucket, $baseDir."search.db", array(
			'fileUpload' => '../uploads/search.db'
		));
	} else {
		$t = str_replace('"', '\"', str_replace('`', '\`', str_replace('´', '\´', $title)));
		$res = rename("../uploads/".$title, "../qDir-uploads/".$title);
		if (!$res){
			rename("../uploads/".$title."/contents.zip", "../qDir-uploads/".$title."/contents.zip");
			rename("../uploads/".$title."/manifest", "../qDir-uploads/".$title."/manifest");
			$cmd = 'rm -rf "../uploads/'.$t.'"';
			exec($cmd);
		}
	}
	return true;
	}
	return false;
}

/***
 * Initializes search DB - will update schema if needed.
 */
function initSearchDB($checkUpdate){
	$db = new MyDB('../uploads/search.db');
	$query = "SELECT count(*) as count FROM sqlite_master WHERE type='table' AND name='content'";
	
	$stmt = $db->prepare($query);
	if ($stmt){
		$query = "SELECT public FROM content";
		$stmt = $db->prepare($query);
		if (!$stmt){			
			$query = "ALTER TABLE content ADD public int DEFAULT 0";
			$db->exec($query);
			$query = "UPDATE content SET public=1 WHERE public=0";
			$db->exec($query);
			
			$cmd = "mkdir ../uploads/tmp";
			exec($cmd);
		}		
		$query = "SELECT tincan FROM content";
		$stmt = $db->prepare($query);
		if ($stmt){
			$db->closeDB();
			return true;
		} else {
			$query = "ALTER TABLE content ADD tincan int DEFAULT 0";
			$db->exec($query);
			$query = "UPDATE content SET tincan=0";
			$db->exec($query);
		}
		
	} else if (!$checkUpdate){
		
		$query = "CREATE TABLE content (pack text, path text, version text, author text, public int DEFAULT 0)";
		$query2 = "CREATE VIRTUAL TABLE content_search using FTS3(pack,section,content,tokenize=porter)";
		$stmt = $db->prepare($query);
		if ($stmt){		
			if ($stmt->execute()){
				$stmt = $db->prepare($query2);
				if ($stmt){		
					$stmt->execute();
				}
			}		
		} else {
			return false;		
		}
	}
	$db->closeDB();
	return false;
}

/*** 
 * Intitializes store with upload directories and database setup
 * returns: true, if initialization successful, false otherwise
 */
function initDBs(){
	$db = new MyDB('../users.db');
	$initQuery = "SELECT count(*) as count FROM sqlite_master WHERE type='table' AND name='users'";
	$stmt = $db->prepare($initQuery);		
	if (!$stmt){
		$db->closeDB();
	} else { 
		$result = $stmt->execute();
		$resultRows = $result->fetchArray(SQLITE3_ASSOC);
		$cnt = $resultRows["count"];
		if (intval($cnt) > 0) {
			$db->closeDB();
			initSearchDB(true);
			return true;	
		}
	}
	
	$query = "CREATE TABLE users (uname text, upass text, firstName text, lastName text, institution text)";
	$initPass = md5('initMASLO');
	
	$insert = "INSERT INTO users VALUES ('masloAdmin', '$initPass', 'Maslo', 'Administrator', '')";
	$stmt = $db->prepare($query);
	if ($stmt){		
		if ($stmt->execute()){
			$stmt = $db->prepare($insert);
			if ($stmt){		
				$stmt->execute();
			}
		}		
	} 
	$db->closeDB();
	$db = new MyDB('admin.db');
	$query = "CREATE TABLE users (uname text, upass text, firstName text, lastName text, institution text)";
	$initPass = md5('initMASLO');
	
	$insert = "INSERT INTO users VALUES ('masloAdmin', '$initPass', 'Maslo', 'Administrator', '')";
	$stmt = $db->prepare($query);
	if ($stmt){		
		if ($stmt->execute()){
			$stmt = $db->prepare($insert);
			if ($stmt){		
				$stmt->execute();
			}
		}		
	} 
	$db->closeDB();
	$cmd = "mkdir ../uploads";
	exec($cmd);	
	$cmd = "mkdir ../qDir-uploads";
	exec($cmd);
	initSearchDB(false);
}

/*** 
 * Delete user
 * $userName: The user name
 * returns: true, if user successfully deleted, false otherwise
 */
function deleteUser($userName){
	if (isset($_SESSION['init'])){
		$db = new MyDB('../users.db');
		$query = "DELETE FROM users WHERE uname == :id";
		$stmt = $db->prepare($query);
		if ($stmt){
			$stmt->bindValue(':id', $userName, SQLITE3_TEXT);
			$stmt->execute();			
		} else {
			$db->close();
			return false;
		}
		$db->close();
		$db = new MyDB('admin.db');
		$stmt = $db->prepare($query);
		if ($stmt){
			$stmt->bindValue(':id', $userName, SQLITE3_TEXT);
			$stmt->execute();			
		}
		$db->close();		
		return true;
	}
	return false;
}

/*** 
 * Insert new user
 * $userName: The user name
 * $password: The user password
 * $firstName: The user's first name
 * $lastName: The user's last name
 * $inst: The user's institution
 * $hasAdmin: Does the user have admin rights? true/false
 * returns: true, if user successfully created, false otherwise
 */
function insertUser($userName, $password, $firstName, $lastName, $inst, $hasAdmin){
	if (isset($_SESSION['init'])){
	$db = new MyDB('../users.db');
	$query = "INSERT INTO users (uname, upass, firstName, lastName, institution) VALUES (:un, :up, :fn, :ln, :i)";
	$password = md5($password);
	$stmt = $db->prepare($query);
	if ($stmt){
		$stmt->bindValue(':un', $userName, SQLITE3_TEXT);
		$stmt->bindValue(':up', $password, SQLITE3_TEXT);
		$stmt->bindValue(':fn', $firstName, SQLITE3_TEXT);
		$stmt->bindValue(':ln', $lastName, SQLITE3_TEXT);
		$stmt->bindValue(':i', $inst, SQLITE3_TEXT);
		$stmt->execute();
	} else {
		$db->closeDB();
		return false;
	}
	$db->closeDB();
	
	if ($hasAdmin) {
		$db = new MyDB('admin.db');
		$stmt = $db->prepare($query);
		if ($stmt){
			$stmt->bindValue(':un', $userName, SQLITE3_TEXT);
			$stmt->bindValue(':up', $password, SQLITE3_TEXT);
			$stmt->bindValue(':fn', $firstName, SQLITE3_TEXT);
			$stmt->bindValue(':ln', $lastName, SQLITE3_TEXT);
			$stmt->bindValue(':i', $inst, SQLITE3_TEXT);
			$stmt->execute();
		} else {
			$db->closeDB();
			return false;
		}
		$db->closeDB();
	}
	return true;
	}
	return false;
}

?>
