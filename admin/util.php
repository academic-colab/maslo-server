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

/*** 
 * Delete content pack
 * $title: Content pack title
 * returns: true, if pack successfully deleted, false otherwise
 */
function deleteData($title){
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
	if ($s3Config["wantS3"] == "true"){
		$s3 = new AmazonS3();
		$response = $s3->get_object_list($bucket, array(
		   'prefix' => $baseDir.$title
		));
		foreach ($response as $v) {
		    $s3->batch()->delete_object($bucket, $v);
		}		
		$file_upload_response = $s3->batch()->send();
		
		$s3->create_object($bucket, $baseDir."search.db", array(
			'fileUpload' => '../uploads/search.db'
		));
	} else {
		$cmd = 'rm -rf "../uploads/'.$title.'"';
		exec($cmd);
	}
	return true;
	}
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
		return true;
	}
	$result = $stmt->execute();
	$resultRows = $result->fetchArray(SQLITE3_ASSOC);
	$cnt = $resultRows["count"];
	if (intval($cnt) > 0) {
		$db->closeDB();
		return true;	
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
	} else {
		return false;		
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
	} else {
		return false;		
	}
	$db->closeDB();
	$cmd = "mkdir ../uploads";
	exec($cmd);
	$db = new MyDB('../uploads/search.db');
	$query = "CREATE TABLE content (pack text, path text, version text, author text)";
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
	$db->closeDB();
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
