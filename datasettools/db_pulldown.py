''''
Pulls a set of Edits from a Database.
Usage:
python db_pulldown.py <ListOfEditIDs> <Database> <OutFile>
'''
from sqlite3 import connect
from zlib import decompress 
from sys import argv










def chunks(l, n):
    """ Yield successive n-sized chunks from l.
    """
    for i in xrange(0, len(l), n):
        yield l[i:i+n]









def query(db,listids,o):
	q='select EditXML from dataset where EditID in (%s);'%','.join([str(p) for p in listids])
	db.execute(q)
	for p in db:
		p=eval(p[0])
		p=decompress(p)
		o.write(p)




def printusage():
	print '''
Pulls a set of Edits from a Database.
Usage:
python db_pulldown.py <ListOfEditIDs> <Database> <OutFile>
'''
	quit()



def main():
	args = list(argv)[1:]
	if len(args)!=3:
		printusage()
	f = args[0]
	db = args[1]
	of = args[2]
	of=open(of,'a')
	of.write('<WPEditSet>')
	del args
	conn=connect(db).cursor()
	try:
		f=file(f).readlines()
		f = [int(p.strip()) for p in f if p.strip()]
	except:
		raise 'Error with EditId file.'
	f=chunks(f,100)
	for p in f:
		query(conn,p,of)
	of.write('</WPEditSet>')
	of.close()
	
	
if __name__=='''__main__''':main()
