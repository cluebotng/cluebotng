'''
Gets proportions of the dataset and devides it amongst files.
Usage:
python multi_dump.py <DATABASE> <Proportion 1> <Proportion 2> <Proportion 3> ...
Proportions are (Floating Point, Output File).
Example:
	python multi_dump.py dataset.db "(0.33,'alpha.xml')" "(0.67,'beta.xml')"
NOTE:
	The SUM of the proportions MUST be less than or equal to 1.
'''
from sqlite3 import connect
from zlib import decompress
from sys import argv


def rowcount():
	conn.execute('select count(*) from dataset;')
	for p in conn:
		return p[0]

def out(info,filename):
	file(filename,'w').write('<WPEditSet>')
	f = open(filename,'a')
	for p in info:
		p = decompress(p)
		f.write(p)
	f.write('</WPEditSet>')
	f.close()
	
def db_dump():
	conn.execute('select EditID from dataset order by random();')
	
	return [p[0] for p in conn]
	
def getlist(l):
	q = 'select EditXML from dataset where EditID in ('+','.join([str(p) for p in l])+');'
	conn.execute(q)
	q = []
	for p in conn:
		q.append(eval(p[0]))
	return q
def main():
	args=list(argv)[1:]

	if len(args)<2:
		raise "Not Enough Arguments!"
	db = args[0]
	print db
	db = connect(db)
	global conn
	conn = db.cursor()
	args = args[1:]
	temp = []
	for arg in args:
		try: arg = eval(arg)
		except:
			print 'Syntax Error with Argument "%s". Please make sure the format is:\n\t"(0.2,\'filename.xml\')"'
			quit()
		temp.append(arg)
	args = temp
	del temp
	if float(sum([p for p,g in args]))>1:
		print 'Sum of Proportions is more tha 1!'
		print 'Sum is %f'%int(sum([p for p,g in args]))
		quit()
	
	rc = rowcount()
	print 'There are %d Edits to be divided.'%rc
	args = [(p*rc,g) for p,g in args]
	dbd=db_dump()
	
	for p,g in args:
		print 'starting with '+g
		out(
		getlist(
		dbd[:int(p)]
		),
		g)
		dbd=dbd[int(p):]
		
	
if __name__=='__main__':main()
	
