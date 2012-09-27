#!/usr/bin/env python
'''
/******************************************************************************
* FTS.py 
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
'''

import sys
import os
import json
import urllib2
import re
import sqlite3 as dbs

## Reads a json file and returns a json object
def getJSON(path, isRelative=True):
	if isRelative : 
		fPath = path + "/manifest"
	else : 
		fPath = path
	try :
		f = open(fPath)
	except : 
		print "File ", fPath, " cannot be opened."
		return None
	else : 
		data = json.load(f)
		f.close()
		return data

## Strip string passed as argument from common stopwords		
def removeStopWords(text):
	stopwords = ""
	try : 
		f = open("stopwords.txt", "r")
	except :
		f = urllib2.urlopen('http://www.textfixer.com/resources/common-english-words.txt')
		stopwords = f.read()
		f = open("stopwords.txt", "w")
		f.write(stopwords)
		f.close()
	else :
		stopwords = f.read()
		f.close()
	stopwords = stopwords.strip().split(",")
	for stopword in stopwords :
		pattern = re.compile(r"\b%s\b"%stopword, re.IGNORECASE)
		text = pattern.sub("", text)
	pattern = re.compile("[\s]+")
	text = pattern.sub(" ", text)
	return text

## Create full text search table for pack contents	
def createTable(db):
	statement = "CREATE VIRTUAL TABLE content_search using FTS3(pack,section,content,tokenize=porter);"
	try :
		db.execute(statement)
		db.commit()
	except:
		pass

## Create basic content pack table		
def createTableUpper(db):
	statement = "CREATE TABLE content (pack text, path text, version text, author text, public int DEFAULT 0);"
	try :
		db.execute(statement)
		db.commit()
	except:
		pass



## insert data into content pack tables - FTS and basic
def insertData(pack, path, db, zipName=None, versionPath=None, author=None):
	data = getJSON(path)
	query = "INSERT INTO content_search(pack, section, content) VALUES (?,?,?)"
	query2 = "INSERT INTO content(pack, path, version, author) VALUES (?,?,?,?)"
	if zipName :
		version = "0"
		authorVal = ""
		if versionPath is not None and author is not None : 
			print versionPath
			versionData = getJSON(versionPath, False)
			if versionData and "version" in versionData : 
				version = versionData["version"]
			authorVal = author
		try : 
			zn = zipName.replace("qDir-", "")
			db.execute(query2, (pack.decode('utf-8'), zn.decode('utf-8'),version, authorVal.decode('utf-8')))
		except Exception, e:
			print "Insert failed: ",pack, zn, version, authorVal
			print e
			pass
	pattern = re.compile("<[^>]+>")	
	print data
	
	for entry in data : 
		title = entry["title"]
		normalTitle = removeStopWords(title)
		try :
			db.execute(query, (pack.decode('utf-8'), title, normalTitle,))
		except Exception, e: 
			print "error:", e
			return
		text = None
		uPath = path.decode('utf-8')
		if entry["type"] == "text"  : 
			newPath = uPath+"/../"+entry["path"]
			f = open(newPath)
			text = f.read().strip()
			f.close()
		else : 
			newPath = uPath+"/../"+ entry["path"]+".dsc"
			try :
				f = open(newPath)
				text = f.read().strip()
				f.close()
			except :
				pass
		if text is not None:
			text = text.decode('utf-8')
			text = pattern.sub(" ", text)
			text = removeStopWords(text)
			try :
				db.execute(query, (pack.decode('utf-8'), title, text,))
			except Exception, e: 
				print "error:", e
				return
				
	db.commit()
			
	
## Create tables if they don't exist, index argument-passed content pack, create database entries
def main(pathToManifest, PackName, pathToGlobalSearch=None, zipName=None, versionPath=None, author=None):
	db = dbs.connect(pathToManifest+"/search.db")
	createTable(db)
	insertData(PackName, pathToManifest, db)
	db.close()
	if (pathToGlobalSearch) :
		db = dbs.connect(pathToGlobalSearch+"/search.db")
		createTable(db)
		createTableUpper(db)
		insertData(PackName, pathToManifest, db, zipName,versionPath, author)
		db.close()
	
	
	
## And now ... get to work.
if __name__ == "__main__" : 
	path = sys.argv[1]
	pack = sys.argv[2]
	globalDb = None
	zipName = None
	versionPath = None
	author = None
	if len(sys.argv) > 3 : 		
		globalDb = sys.argv[3]
	if len(sys.argv) > 4 :
		zipName = sys.argv[4]
	if len(sys.argv) > 5 :
		versionPath = sys.argv[5]
		author = sys.argv[6]
	
	main(path, pack, globalDb, zipName, versionPath, author)
