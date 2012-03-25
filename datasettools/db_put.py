'''
Adds edits to a databse given a file.
Usage 
db_put.py <datafile> <database> -threads=3

The Datafile should Be in the Following Format:
<IsVandalism> <EditID> <Source>
	IsVandalism - 1 if the edit is Vandalism, 2 if not
	EditID - The Wikipedia revision_id of the Edit in question.
	Source - Who/Where this information is comming from.
'''
from time import sleep
from sys import argv
from threading import Thread
from Queue import Queue
from urllib2 import urlopen
from zlib import compress
from Queue import Empty

class dbmaker(Thread):
	def __init__(self,db):
		Thread.__init__(self)
		self.q = Queue()
		self.db=db
		self.commit=False
	def add(self,xml,editid,source,isvandalism):
		self.q.put((xml,editid,source,isvandalism))
		
	def run(self):
		from sqlite3 import connect
		self.db=connect(self.db)
		del connect
		self.conn=self.db.cursor()
		while True:
			if self.commit:
				break
			try:xml,editid,source,isvandalism = self.q.get(timeout=.1)
			except Empty: continue
			print 'inserting Row!'
			xml=repr(compress(xml,9))
			try:editid=int(editid)
			except:return
			try:isvandalism=int(isvandalism)
			except:return
			try:self.conn.execute('''
	insert into dataset (EditId,EditXML,IsVandalism,EditSource) Values (?,?,?,?)''',
	(int(editid),xml,int(isvandalism),source,))
			except : print 'EditId %d Aleady Present, skipping'%int(editid)
		self.db.commit()
			

class urlgetThread(Thread):
	def __init__(self,workqueue,dbgo):
		Thread.__init__(self)
		self.worklist=workqueue
		self.dbgo=dbgo
	def run(self):
		while True:
			(isvandalism,editid,source) = self.worklist.get()
			xml = 'http://toolserver.org/~tim1357/cgi-bin/edit_details.py?id=%s&wrap=False&eval=%s'%(editid,str(bool(isvandalism)).title())
			xml = urlopen(xml).read()
			self.dbgo.add(xml,editid,source,isvandalism)













def db_dump():
	print 'Stripping edits already in the dataset'
	conn.execute('select EditID from dataset;')
	return [str(p[0]) for p in conn]

def main():
	args = [p for p in argv][1:]
	if len(args)<2:
		quit('No Instruction File Given!')
	f =args[0]
	db=args[1]
	args = args[2:]
	
	
	threads = 3
	docommit=True
	for arg in args:
		if arg.startswith('-threads') and len(arg)>=len('-threads=3'):
			threads = int(arg[len('-threads='):])
		elif arg =="-nc":
			docommit=False
	try:f = file(f).readlines()
	except:
		raise 'No Input File Given (Must be first arg)'
		
	f = [p.strip() for p in f]
	ts=[]
	work_queue=Queue()
	
	if 1:
		f = [p.split(' ')[:3] for p in f]
		f = [p for p in f if len(p)==3]
		f= [p for p in f if p not in d]
		if f:
			d=db_dump()
			f = [p for p in f if p not in d]
			del d
		if not f:
			quit('No edits to be fetched!')
	for p in f:
		work_queue.put(p)
	del f
	dm=dbmaker(db)
	dm.start()
	for i in range(threads):
		ts.append(urlgetThread(work_queue,dm))
	for p in ts:
		p.start()
		
	while not work_queue.empty():
		sleep(.4)
	sleep(2.5)	
	while not  dm.q.empty():
		sleep(.4)
		
	if docommit:
		dm.commit=True
		sleep(1)
		quit('Commited.\nWe\'re Done!')
	else:
		quit('NOT Commited.\n We\'re Done!')

if __name__=='__main__':
	main()
