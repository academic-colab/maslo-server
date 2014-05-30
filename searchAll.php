<?php
/******************************************************************************
 * search.php
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
require_once 'traverse.php';

class MyDB extends SQLite3
{

	function __construct($d)
    {
        $this->open($d.'/uploads/search.db');
    }

	function closeDB() {
		$this->close();
	}
}
$default_dir = ".";

/***
 * Process input data - the search script expects only one GET key - 
 * if several are given, only the last one 
 * will be considered. Data entries will be discarded entirely. 
 * Client replaces spaces with ':::', therefore those tokens have to be 
 * turned back into spaces.
 * @return: processed search string
 */
function processInput() {
	$inputData = $_GET;
	$data = "";
	foreach ($inputData as $key => $value) {
		$data = $key;
	}
	$data = str_replace(":::", " ",$data);
	return $data;
}


/***
 * Query the database for search string
 * @return: JSON string containing the result
 */
function queryDB($d) {
	$callingURL = "http://".$_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF'])."/".$d."/"  ;
	$parameter = processInput();
	$db = new MyDB($d);
	
	$stmt = $db->prepare("SELECT content_search.pack as title, section, '".$callingURL."' || path as filename, content.version as version FROM content_search, content WHERE content match :id and content_search.pack == content.pack and content.public == 1 order by title");
	$stmt->bindValue(':id', $parameter, SQLITE3_TEXT);


	$result = $stmt->execute();
	
	$jsonString = "{\"name\":\"".$d."\",\"content\":[";
	$started = false;
	$currentPack = null;
	$sections = array();
	$resRow = array();
	$numResults = 0;
	$jsonData = array();
	$s3ConfigStream = file_get_contents($d."/config.json");
	$s3Config = json_decode($s3ConfigStream, true);
	if ($s3Config["wantS3"] == "true") {
		$json = json_decode(traverseDirAmazon($s3Config["bucket"],$s3Config["baseDir"]),true);
		$json = $json["data"];
		$i = 0;
		while ($i < count($json)) {
			$jsonData[$json[$i]["title"]] = $json[$i]["filename"];
			$i++;
		}
	}
	while ($resultRows = $result->fetchArray(SQLITE3_ASSOC)) {		
		if ($currentPack == null){
			$currentPack = $resultRows["title"];
			$numResults += 1;
		}
		if ($currentPack != $resultRows["title"]) {
			$resRow["sections"] = $sections;
			if ($started) {
				$jsonString .= ",".json_encode($resRow);
			} else {
				$started = true;
				$jsonString .= json_encode($resRow);
			}
			$numResults += 1;
			unset($resRow);
			unset($sections);
			$sections = array();
			$resRow = array();
			$currentPack = $resultRows["title"];
		}
		$resRow["title"] = $resultRows["title"];
		$resRow["filename"] = $resultRows["filename"];
		$resRow["version"] = $resultRows["version"];
		if (array_key_exists($resRow["title"] , $jsonData)) {
			$resRow["filename"] = $jsonData[$resRow["title"]];
		}
		array_push($sections, $resultRows["section"]);
	}
	if ($numResults > 0) {
		$resRow["sections"] = $sections;
		if ($numResults > 1) {
			$jsonString .= ",";
		}
		$str = json_encode($resRow);
		$jsonString .= json_encode($resRow);
	}
		
	$db->closeDB();

	$jsonString =  $jsonString . "]}";

	return $jsonString;
	
	
}

function queryAll(){
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
			$json = queryDB($f);
			$json = json_decode($json, true);
			if (count($json["content"]) > 0)
				array_push($jsonAll, $json);
		}
	}
	$json = "{\"data\": ".json_encode($jsonAll)."}";
	return $json;
}

$jString = queryAll();
echo $jString;

?>