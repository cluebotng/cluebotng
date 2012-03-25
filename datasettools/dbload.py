# -*- coding: utf-8  -*-

'''
---Dataset Database Loader---
Loads a bunch of stand-alone files into an sqlite3 database.
Usage:
python dbload.py  <DIFF SOURCES> <SQL DATABASE>
DIff Sources is:
Path to diff-source-1 if vandalism else 0
'''
from zlib import compress
from sqlite3 import connect
from sys import argv
def file_root(string):
	if '/' not in string:
		return string
	else:
		str=[]
		string=list(string)
		while string[-1]!='/':
			str.append(string.pop())
		return ''.join(str[::-1])
		

def check_table():
	try:
		conn.execute('''select 1 from dataset where 1==1;''')
		return True
	except:
		return False

def maketable():
	conn.execute('''
	create table dataset (EditID int, EditXML blob, IsVandalism int DEFAULT 0, EditSource text default NULL, Primary Key (EditID));''')
	db.commit()

def insert_row(editfile,editid,editsource,isVandalism:
	if 1: editfile = editfile.read()
	editfile=repr(compress(editfile,9))
	try:editid=int(editid)
	except:return
	try:isVandalism=int(isVandalism)
	except:return
	conn.execute('''
	insert into dataset (EditId,EditXML,IsVandalism,EditSource) Values (?,?,?,?)''',
	(int(editid),editfile,int(isVandalism),editsource,))
	
def commit():
	db.commit()
	
def mkdb(db_root):
		global db
		db = connect(db_root)
		global conn
		conn = db.cursor()
def main():
	args = list(argv)
	if not len(args)==3:
		printusage()
		return
	else:
		d_sources,db_root = args[1:]
	
		mkdb(db_root)
		
		del args
		if not check_table():
			print 'Making Table in "'+db_root+'" Datasets. (Indexed by Edit Id)'
			maketable()

		try:
			d_sources = file(d_sources).readlines()
		except IOError:
			raise '''Diff sources does not exist!'''
		
		d_sources = [p.strip().split('-') for p in d_sources]
		d_sources=[p for p in d_sources if len(p)==3]
		temporary_ = []
		
		for id,s,v in d_sources:
			insert_row(file(id),file_root(id),s,v)
		db.commit()


if __name__=="__main__":main()
