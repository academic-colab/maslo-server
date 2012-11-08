<?php
/******************************************************************************
 * upload.php
 *
 * Copyright (c) 2011-2012, Academic ADL Co-Lab, University of Wisconsin-Extension
 * http://www.academiccolab.org/
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301  USA
 *****************************************************************************/
/*
 *  @author Cathrin Weiss (cathrin.weiss@uwex.edu)
 */

//header('Content-Type: text/html; charset=utf-8'); 
date_default_timezone_set('America/Chicago');
require_once 's3sdk/sdk.class.php';

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

session_start();

/***
 * Run full text search indexing
 * Processes contents of the uploaded content pack and indexes text data. Entries in search dbs are created
 */
function runFTS($path, $name, $globalPath, $zipName, $pathToVersion){
	$userName = $_POST['userName'];
	$stripPath = str_replace('"', '\"',$path);
	$stripVPath = str_replace('"', '\"',$pathToVersion);
	$zName = str_replace('"', '\"',$zipName);
	$stripName = str_replace('"', '\"',$name);
	$cmd = 'python FTS.py "'.$stripPath.'" "'.$stripName.'" "'.$globalPath.'" "'.$zName.'" "'.$stripVPath.'" "'.$userName.'" >> FTS.out 2>&1';
	
	exec($cmd);
}

/***
 * Unzip the uploaded zip file
 * $fileName: the zip file name
 * $folderName: the project directory name
 * $destination: directory the content should be unzipped into
 * @return: the project directory name
 */
function doUnzip($fileName, $folderName, $destination){
	$cmd = "unzip -d $destination $fileName";	
	exec($cmd);
	
	$cmd = "ls $destination";
	$io = popen($cmd, "r");
	$dirName = fgets($io, 4096);	
	$encTitle = trim($dirName);
	pclose ( $io );

	$packTitle = $_POST['dirName'];
	/*$encTitle = urlencode($packTitle);
	$encTitle = str_replace('+', '%20', $encTitle);
	$encTitle = str_replace('%27', "'", $encTitle);*/
	if ($encTitle != $packTitle){
		rename($destination.'/'.$encTitle, $destination.'/'.$packTitle);
	}
	return $folderName;

}
 

/***
 * Add search database to zip file
 * $zipName: path to zip file
 * $folder: content pack directory name
 * $sessionLocation: path to the temporary project directory
 * @return: false
 */
function addSearchDB($zipName, $folder, $sessionLocation){
	$f = str_replace('"', '\"', $folder);
	$cmd = 'sh doZip.sh "'.addslashes($sessionLocation).'" "'.addslashes($zipName).'" "'.$f.'"';
	exec($cmd);
	return false;
}

/***
 * Create content pack manifest
 * $title: content pack title
 * $path: path to the folder in which manifest should be stored
 * $zipPath: file name of zip file
 * $manifestPath: Path to temporary project directory
 */
function createManifest($title, $path, $zipPath, $manifestPath){
	$manifestPath = stripslashes($manifestPath);
	$versionStream = file_get_contents($manifestPath."/version");
	$versionData = json_decode($versionStream, true);
	$version = $versionData["version"];

	$today = date("Y-m-d");  
	$data = '{"title": "'.str_replace('"', '\"', $title).'", "filename":"'.str_replace('"', '\"', $zipPath).'", "course":"", "date":"'.$today.'", "version":"'.$version.'", "size":"'.$_SESSION["packSize"].'"}';
	$fname = str_replace("\ ", " ", $path);
	$fname =  $fname."manifest";
	$file = fopen($fname, "w");
	fwrite($file, $data);
	fclose($file);
}

/***
 * Merge zip chunks back into one zip file
 */
function mergeZip($basepath, $numFiles){
	for ($i = 1; $i < $numFiles; $i++){
		$command = "cat ".$basepath.".".$i." >> ".$basepath;
		exec($command);
		$rmcommand = "rm -f ".$basepath.".".$i;
		exec($rmcommand);
	}
}


/***
* Check for existence of a content pack - if it exists, delete all content relating to it
* $packName: Name of the content pack
* @returns: bool, true if pack existed, false if it did not
*/
function checkForPackExist($packName){
	$db = new MyDB('uploads/search.db');
	$query = "SELECT count(*) as count FROM sqlite_master WHERE type='table' AND name='content'";	
	$stmt = $db->prepare($query);		
	if (!$stmt){
		$db->closeDB();
		return false;
	}
	$result = $stmt->execute();
	$resultRows = $result->fetchArray(SQLITE3_ASSOC);
	$cnt = $resultRows["count"];
	if (intval($cnt) == 0) {
		$db->closeDB();
		return false;	
	}
	
	$query = "SELECT count(*) as count FROM content where pack == :id";	
	$stmt = $db->prepare($query);		
	if (!$stmt){
		$db->closeDB();
		return false;
	}
	$stmt->bindValue(':id', $packName, SQLITE3_TEXT);
	$result = $stmt->execute();
	$resultRows = $result->fetchArray(SQLITE3_ASSOC);
	$cnt = $resultRows["count"];
	if (intval($cnt) > 0) {
		$query = "DELETE FROM content where pack == :id";
		$stmt = $db->prepare($query);
		$stmt->bindValue(':id', $packName, SQLITE3_TEXT);
		$stmt->execute();
		$query = "DELETE FROM content_search where pack == :id";
		$stmt = $db->prepare($query);
		$stmt->bindValue(':id', $packName, SQLITE3_TEXT);
		$stmt->execute();
	}
	
	$db->closeDB();
	return true;
}

/***
* Handler function for content pack upload
* Handles each individual step, once all zip chunks are successfully uploaded,
* it recompiles them and processes the data
*/
function receiveUpload(){
	$mainLocation = 'qDir-uploads/';
	$searchLocation = 'uploads/';
	$sessionLocation = $mainLocation."tmp/".session_id().'/';	
	if (!is_dir($sessionLocation)){
		if (!is_dir($mainLocation."tmp/")){
			$cmd = "mkdir ".$mainLocation."tmp/";
			exec($cmd);
		}		
		$cmd = "mkdir $sessionLocation";
		exec($cmd);
	}
	if (!is_dir($sessionLocation)){
		session_unset();
		session_destroy();
		session_regenerate_id(true);
		echo "FAILED.";
		return false;
	}
	
	$uploadfile = $sessionLocation . basename($_FILES['contentPackUpload']['name']);
	$result = move_uploaded_file($_FILES['contentPackUpload']['tmp_name'], $uploadfile);
	if (!$result){		
		if (isset($_SESSION['numFiles'])) {
			$cmd = "rm -f ".$_SESSION['zipName'];
			exec($cmd);
			for ($i = 1; $i < $_SESSION['numFiles']; $i++) {
				$cmd = "rm -f ".$_SESSION['zipName'].".".$i;
				exec($cmd);
			}
		}
		session_unset();
		session_destroy();
		session_regenerate_id(true);
		echo "FAILED.";
		return false;
	} else {
		if (!isset($_SESSION['zipName'])) {
			$_SESSION['numFiles'] = 1;
			$_SESSION['zipName'] = $uploadfile;
		} else {
			$_SESSION['numFiles'] = $_SESSION['numFiles']+1;
		}			
	}   
	
	if ($_SESSION['numFiles'] == $_POST['numberFiles']) {				
		$zipFileName = $sessionLocation.$_POST["zipName"];
		mergeZip($zipFileName, $_POST["numberFiles"]);
		
		$folderName = $_POST['dirName']."/";
		$plainDir = $_POST['dirName'];
		$unchanged = $sessionLocation.$plainDir;
		$unchangedDest = $mainLocation.$folderName;
		$plainDir = $sessionLocation.str_replace(" ", "\ ", $plainDir);
		
		$res = doUnzip($zipFileName, $folderName, $sessionLocation);
		$cmd = 'du -hs "'.$sessionLocation.str_replace('"', '\"',$folderName).'"';

		$io = popen($cmd, "r");
		$size = fgets($io, 4096);	
		$size = trim($size);
		$size = substr ( $size, 0, strpos ( $size, '	' ) );		
		pclose ( $io );
		$_SESSION['packSize'] = $size;
		$zipName = $_POST['zipName'];
		$packTitle = $_POST['packTitle'];		
		$zipFileName = $sessionLocation.$zipName;
		
		checkForPackExist($packTitle);	
		
		runFTS($sessionLocation.$folderName, $packTitle, $searchLocation, $mainLocation.$folderName.$zipName, $unchanged."/version");
		
		addSearchDB($zipName, $folderName  , $sessionLocation);
		
		mkdir($mainLocation.$folderName);
		rename($zipFileName, $mainLocation.$folderName.$zipName);
		
		createManifest($packTitle, $mainLocation.$folderName, $zipName, $unchanged);
		exec('rm -rf "'.$sessionLocation.'"');
		$s3ConfigStream = file_get_contents("config.json");
		$s3Config = json_decode($s3ConfigStream, true);
		if ($s3Config["wantS3"] == "true") {
			$bucket = $s3Config["bucket"];
			$baseDir = $s3Config["baseDir"]."/";
			try {
				$s3 = new AmazonS3();
				$s3->batch()->create_object($bucket, "qDir-".$baseDir.$folderName.$zipName, array(
					'fileUpload' => $unchangedDest.$zipName,
					'acl'         => AmazonS3::ACL_PUBLIC
					));
				$s3->batch()->create_object($bucket, "qDir-".$baseDir.$folderName."manifest", array(
					'fileUpload' => $unchangedDest."manifest",
					'acl'         => AmazonS3::ACL_PUBLIC
				));
				$s3->batch()->create_object($bucket, $baseDir."search.db", array(
					'fileUpload' => $searchLocation."search.db"
				));
				$file_upload_response = $s3->batch()->send();
				unlink("qDir-".$baseDir.$folderName.$zipName);
				unlink("qDir-".$baseDir.$folderName."manifest");
				rmdir("qDir-".$baseDir.$folderName);

			} catch (Exception $e){

			}
		}
		session_unset();
		session_destroy();
		session_regenerate_id(true);
		echo "COMPLETE.";
	} else {
		echo "OK.";
	}
	return true;
}


/***
* Check for user
* $userName: name of user
* $userPass: user password
* @returns: bool, true if pack existed, false if it did not
*/
function checkUser($userName, $userPass){
	$db = new MyDB('users.db');
	$query = "SELECT upass FROM users where uname == :id";
	$stmt = $db->prepare($query);
	$stmt->bindValue(':id', $userName, SQLITE3_TEXT);
	$result = $stmt->execute();
	$resultRows = $result->fetchArray(SQLITE3_ASSOC);
	$pass = $resultRows["upass"];
	$pass = $pass.hash('sha256',hash('sha256',session_id()));
	$pass =  hash('sha256',$pass);
	$db->closeDB();
	if ($pass == $userPass) {
		return true;
	}
	return false;
}

/***
 * Get to work - check user authentication and act accordingly
 */
if (array_key_exists("handshake", $_POST)){
	session_regenerate_id(true);
	echo hash('sha256',session_id());
	
} else {
	if (isset($_POST['userName']) && isset($_POST['password'])) {
		if (isset($_SESSION['init'])){
			if (!array_key_exists('contentPackUpload',$_FILES)) {
				echo "FAILED.";
			} else {
				$result = receiveUpload();
				if (!$result){
					session_unset();
					session_destroy();
					session_regenerate_id(true);
					echo "FAILED.";
				} else {
					echo "OK.";
				}		
			}
			return false;
		}
		
		if (checkUser($_POST['userName'],$_POST['password'])) {
			if (!isset($_SESSION['init'])){			
				session_regenerate_id(false);
				$_SESSION['init'] = 0;							
				echo "OK.";
				return false;
			}
		} else {			
			session_unset();
			session_destroy();
			session_regenerate_id(true);
			echo "DENIED.";
		}	
	} else {			
		session_unset();
		session_destroy();
		session_regenerate_id(true);	
		echo "DENIED.";
	}
}

?>