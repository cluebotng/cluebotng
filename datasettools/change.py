'''
Usage:
python change.py <DATABASE> <File with List of Edit IDs> <Action>

Accepted actions are:
-set:v --Set each edit to vandalism.
-set:r --Set each edit to regular.
-delete --Delete each edit.

'''

from sqlite3 import connect
from zlib import compress,decompress
from sys import argv
from re import findall






def main():
	args =  list(argv)[1:]
	if len(args)!=3:
		raise 'Not enough arguments!'
		quit()
	db=args[0]
	f=args[1]
	action=args[2]
	del args
	if action not in ('-set:v'
,'-set:r',
'-delete'):
		raise 'Improper action!'
	try:
		f = file(f).read()
	except:
		raise 'Input file does not exist!'
	f = findall('\d\d\d\d\d+',f)
	try:
		f=[int(p) for p in f if p]
	except:
		raise 'Not all given EditIDs are in correct format!'
		quit()
	print 'There are ',len(f),' diffs to modify.'
	db=connect(db)
	global conn
	conn=db.cursor()
	if action == '-delete':
		for i in f:
			delete(i)
		db.commit()
		quit()
	elif action == '-set:v':
		isvand=True
	elif action == '-set:r':
		isvand=False

	for i in f:
		process(i,isvand)
	db.commit()
	
def delete(i):
	print 'Deleting',i
	conn.execute('delete from dataset where EditID=%s;'%str(i))
	
	
def process(i,isvand):
	print 'Start',i
	conn.execute('select EditXML from dataset where EditID=%s;'%str(i))
	p=[g[0] for g in conn][0]
	if not p:
		print 'Skipping '+str(i)
	p=decompress(eval(p))
	if isvand:
		p = p.replace('<isVandalism>false</isVandalism>','<isVandalism>true</isVandalism>')
	else:
		p = p.replace('<isVandalism>true</isVandalism>','<isVandalism>false</isVandalism>')
	p=eval(compress(p,9))
	d=int(isvand)
	conn.execute('update dataset set EditXML = ?, IsVandalism=? where EditID=?;',(p,d,i))

if __name__=='''__main__''':
	main()