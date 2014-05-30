<?php
/******************************************************************************
 * traverse.php
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
require_once 's3sdk/sdk.class.php';

/***
 * Traverse local content directory $dir and generate json data about existing content packs
 */
function traverseDir($dir) {
	$callingURL = $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) ;
 	$all_json = "{\"content\":[";
    if(!($dp = opendir($dir))) die("Cannot open $dir.");
    $started = false;
    while((false !== $file = readdir($dp)))
       if(is_dir("$dir/$file") ){
            if($file != '.' && $file !='..' && $file != 'tmp'){
			try {
				if (is_file("$dir/$file/manifest")) {
               $string = file_get_contents("$dir/$file/manifest");
			   $json_data = json_decode($string, true);
			   if (array_key_exists("filename", $json_data)) {
				$urlEncFile = rawurlencode($file);
			   $json_data["filename"] = "http://$callingURL/$dir/$urlEncFile/".$json_data["filename"];
			   $string = json_encode($json_data);
			   if ($started)
			   		$all_json = $all_json .",". $string;
			   else {
					$all_json = $all_json . $string ;
					$started = true;
				
				}
			}
			}
            } catch(Exception $e){}
			}
      }
      
    closedir($dp);
 	$all_json = $all_json . "]}";
	return $all_json;
}

/***
 * Traverse local content directory $dir and generate json data about existing content packs
 */
function traverseDirLocal($dir) {
	$callingURL = $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) ;
 	$all_json = "{\"content\":[";
    if(!($dp = opendir($dir))) die("Cannot open $dir.");
    $started = false;
    while((false !== $file = readdir($dp)))
       if(is_dir("$dir/$file") ){
            if($file != '.' && $file !='..' && $file != 'tmp'){
			try {
				if (is_file("$dir/$file/manifest")) {               
			   $json_data["path"] = "$dir/$file/";
			   $json_data["title"] = $file;
			   $string = json_encode($json_data);
			   if ($started)
			   		$all_json = $all_json .",". $string;
			   else {
					$all_json = $all_json . $string ;
					$started = true;
				
			  }
			}
            } catch(Exception $e){}
			}
      }
      
    closedir($dp);
 	$all_json = $all_json . "]}";
	return $all_json;
}


/***
 * Traverse Amazon bucket with name $bucket and directory name $dir
 */
function traverseDirAmazon($bucket, $dir){
	$s3 = new AmazonS3();
	$object_list_array = $s3->get_object_list($bucket, array(
	  'prefix' => $dir
	));
	$all_json = "{\"content\":[";
	$started = false;
	foreach ($object_list_array as $obj) {
		$pos = strpos($obj, "search.db");
		if ($pos === false) {
			$url = $s3->get_object_url($bucket, $obj);
			$pos = strpos($url, "manifest");
			if ($pos !== false) {
			$string = file_get_contents($url);
			$json_data = json_decode($string, true);
			if (array_key_exists("filename", $json_data)) {
				$url = str_replace("/manifest","",$url);
				$json_data["filename"] = $url."/".$json_data["filename"];
				$string = json_encode($json_data);
				if ($started)
					$all_json = $all_json .",". $string;
				else {
					$all_json = $all_json . $string ;
					$started = true;
				}
			}
			}	
		}
	}
	$all_json = $all_json . "]}";
	return $all_json;
}

/***
 * Pick traversal method automatically based on configuration and return json data
 */
function doTraverse(){
	$default_dir = "uploads/";
	$s3ConfigStream = file_get_contents("config.json");
	$s3Config = json_decode($s3ConfigStream, true);
	if ($s3Config["wantS3"] == "true") {
		$json = traverseDirAmazon($s3Config["bucket"],$s3Config["baseDir"]);
	} else{
		$json = traverseDir($default_dir);
	}
	return $json;
}

/***
 * Pick traversal method automatically based on configuration and return json data
 */
function doTraverseRecurse(){
	$jsonAll = array();
	$handle = opendir(".");
	$contentFound = false;
	while (1) {
		$json = "";
		$f = readdir($handle);
		if (!$f) {
			break;
		}
		if (is_dir($f) && $f != "s3sdk" && $f != "." && $f != "..") {
			$default_dir = $f."/uploads/";
			$s3ConfigStream = file_get_contents($f."/config.json");
			if ($s3ConfigStream) {
				$contentFound = true;
				$s3Config = json_decode($s3ConfigStream, true);
				if ($s3Config["wantS3"] == "true") {
					$json = traverseDirAmazon($s3Config["bucket"],$s3Config["baseDir"]);
				} else{
					$json = traverseDir($default_dir);
				}
			}
			$jsonDec = json_decode($json, true);
			$jsonDec["name"] = $f;
			array_push($jsonAll, $jsonDec);
		}
	}
	if ($contentFound){
		$json = "{\"data\": ".json_encode($jsonAll)."}";
	} else {
		$default_dir = "uploads/";
		$s3ConfigStream = file_get_contents("config.json");
		$s3Config = json_decode($s3ConfigStream, true);
		if ($s3Config["wantS3"] == "true") {
			$json = traverseDirAmazon($s3Config["bucket"],$s3Config["baseDir"]);
		} else{
			$json = traverseDir($default_dir);
		}
		$jsonDec = json_decode($json, true);
		$jsonDec["name"] = "Store";
		array_push($jsonAll, $jsonDec);
		$json = "{\"data\": ".json_encode($jsonAll)."}";
	}
	return $json;
}

/***
 * Traverse quarantine data
 */
function traverseQuarantine(){
	$default_dir = "qDir/";
	$s3ConfigStream = file_get_contents("config.json");
	$s3Config = json_decode($s3ConfigStream, true);
	if ($s3Config["wantS3"] == "true") {
		$json = traverseDirAmazon($s3Config["bucket"],$s3Config["baseDir"]."-qDir");
	} else{
		$json = traverseDir($default_dir);
	}
	return $json;
}

?>