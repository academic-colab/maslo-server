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
class MyDB extends SQLite3
{
    function __construct()
    {
        $this->open('uploads/search.db');
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
function queryDB() {
	$callingURL = "http://".$_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF'])."/"  ;
	$parameter = processInput();
	$db = new MyDB();
	
	$stmt = $db->prepare("SELECT content_search.pack as title, section, '".$callingURL."' || path as filename FROM content_search, content WHERE content match :id and content_search.pack == content.pack order by title");
	$stmt->bindValue(':id', $parameter, SQLITE3_TEXT);


	$result = $stmt->execute();
	
	$jsonString = "{\"data\":[";
	$started = false;
	$currentPack = null;
	$sections = array();
	$resRow = array();
	$numResults = 0;
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

$jString = queryDB();
echo $jString;

?>