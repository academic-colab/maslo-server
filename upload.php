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
putenv("LANG=en_US.UTF-8");

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
 * Recursively delete directory
 */
function removeDirectory($dirPath) {
	if (!is_dir($dirPath))
		return;
	foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dirPath, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $path) {
	    $path->isFile() ? unlink($path->getPathname()) : rmdir($path->getPathname());
	}	
	rmdir($dirPath);
}

/***
 * Run full text search indexing
 * Processes contents of the uploaded content pack and indexes text data. Entries in search dbs are created
 */
function runFTS($path, $name, $globalPath, $zipName, $pathToVersion){
	$userName = $_POST['userName'];
	$stripPath = str_replace('`', '\`',str_replace('"', '\"',$path));
	$stripVPath = str_replace('"', '\"',str_replace('`', '\`',$pathToVersion));
	$zName = str_replace('"', '\"',str_replace('`', '\`',$zipName));
	$stripName = str_replace('"', '\"',str_replace('`', '\`',$name));
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
	$encTitle = "contents.zip";
	while ($encTitle == "contents.zip") {
		$dirName = fgets($io, 4096);	
		$encTitle = trim($dirName);
	}
	pclose ( $io );

	$packTitle = $_POST['dirName'];
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
	$f = str_replace('`', '\`', $f);
	$f = str_replace('´', '\´', $f);
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
	$category = "";
	if (array_key_exists("category", $versionData))
		$category = $versionData["category"];
	$today = date("Y-m-d");  
	$data = '{"title": "'.str_replace('"', '\"', $title).'", "filename":"'.str_replace('"', '\"', str_replace('`', '\`', $zipPath)).'", "course":"", "date":"'.$today.'", "version":"'.$version.'", "size":"'.$_SESSION["packSize"].'", "category":"'.$category.'"}';
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
		return -1;
	}
	$result = $stmt->execute();
	$resultRows = $result->fetchArray(SQLITE3_ASSOC);
	$cnt = $resultRows["count"];
	if (intval($cnt) == 0) {
		$db->closeDB();
		return -1;	
	}
	
	$query = "SELECT count(*) as count, public FROM content where pack == :id";	
	$stmt = $db->prepare($query);		
	if (!$stmt){
		$db->closeDB();
		return -1;
	}
	$stmt->bindValue(':id', $packName, SQLITE3_TEXT);
	$result = $stmt->execute();
	$resultRows = $result->fetchArray(SQLITE3_ASSOC);
	$cnt = $resultRows["count"];
	$pub = -1;
	if (intval($cnt) > 0) {
		$pub = $resultRows["public"];
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
	return $pub;
}

/*** 
 * Publish content pack
 * $title: Content pack title
 * returns: true, if pack successfully published, false otherwise
 */
function publishData($title){
	if (isset($_SESSION['init']) && strcmp($_SESSION['init'],basename(getcwd())) == 0){
	$db = new MyDB('uploads/search.db');
	$query = "UPDATE content SET public = 1 where pack == :id";
	$stmt = $db->prepare($query);
	if ($stmt){
		$stmt->bindValue(':id', $title, SQLITE3_TEXT);
		$stmt->execute();
	}
	$db->closeDB();
	$s3ConfigStream = file_get_contents("config.json");
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
			'fileUpload' => 'uploads/search.db'
		));
	} else {
		$t = str_replace('"', '\"', str_replace('`', '\`', str_replace('´', '\´', $title)));
		$res = rename("qDir-uploads/".$title, "uploads/".$title);
		if (!$res){
			rename("qDir-uploads/".$title."/contents.zip", "uploads/".$title."/contents.zip");
			rename("qDir-uploads/".$title."/manifest", "uploads/".$title."/manifest");
			$cmd = 'qDir-uploads/'.$t;
			removeDirectory($cmd);
		}
	}
	return true;
	}
	return false;
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
		$cmd = 'du -hs "'.$sessionLocation.str_replace('"', '\"',str_replace('`', '\`',$folderName)).'"';

		$io = popen($cmd, "r");
		$size = fgets($io, 4096);	
		$size = trim($size);
		$size = substr ( $size, 0, strpos ( $size, '	' ) );		
		pclose ( $io );
		$_SESSION['packSize'] = $size;
		$zipName = $_POST['zipName'];
		$packTitle = $_POST['packTitle'];		
		$zipFileName = $sessionLocation.$zipName;
		
		$wasPublished = checkForPackExist($packTitle);	
		
		runFTS($sessionLocation.$folderName, $packTitle, $searchLocation, $mainLocation.$folderName.$zipName, $unchanged."/version");
		
		addSearchDB($zipName, $folderName  , $sessionLocation);
		
		mkdir($mainLocation.$folderName);
		rename($zipFileName, $mainLocation.$folderName.$zipName);
		
		createManifest($packTitle, $mainLocation.$folderName, $zipName, $unchanged);
		removeDirectory( 'client/content/'.$folderName);
		rename($sessionLocation.$folderName, "client/content/".$folderName);
		removeDirectory($sessionLocation);
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
		if ($s3Config["wantDirectPublish"] == "true" && $wasPublished != 0) {
			publishData($packTitle);
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
		if (isset($_SESSION['init']) && strcmp($_SESSION['init'],basename(getcwd())) == 0){		
			if (array_key_exists("numberFiles", $_POST) && $_POST['numberFiles'] > 20){
				if (isset($_SESSION["countFiles"])) {
					$_SESSION["countFiles"] += 1;
				} else {
					$_SESSION["countFiles"] = 1;
				}
				if (intval($_SESSION["countFiles"]) == intval($_POST['numberFiles'])){
					session_unset();
					session_destroy();
					session_regenerate_id(true);
					echo "The content pack you tried to upload is too large (exceeding 20MB).";
				} else {
					echo "OK.";					
				}
				return false;
			}	
			if (!array_key_exists('contentPackUpload',$_FILES)) {

				session_unset();
				session_destroy();
				session_regenerate_id(true);
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
				$_SESSION['init'] = basename(getcwd());							
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