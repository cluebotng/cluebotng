##Config##
backend_server='98.222.57.24'
backend_port='3565'
threads = 3
from re import search
from threading import Thread
from MySQLdb import connect
from urllib2 import urlopen
from urllib import quote
from time import time, mktime, sleep, strptime
from simplejson import loads

		

from sys import stdout,stderr

from Queue import Queue
from xml.sax.saxutils import escape

is_ip = lambda username: set(list(username)).issubset(set(['0', '1', '2', '3', '4', '5', '6', '7', '8', '9','.']))
ns_translate={'100': 'Portal', '101': 'Portal talk', '1': 'Talk', '0': 'Main', '3': 'User talk', '2': 'User', '5': 'Wikipedia talk', '4': 'Wikipedia', '7': 'File talk', '6': 'File', '9': 'MediaWiki talk', '8': 'MediaWiki', '108': 'Book', '109': 'Book talk', '91': 'Thread talk', '90': 'Thread', '93': 'Summary talk', '92': 'Summary', '11': 'Template talk', '10': 'Template', '13': 'Help talk', '12': 'Help', '15': 'Category talk', '14': 'Category'}


def o(m):
	stderr.write(m+'\n')
	

def tounix(date,apifmt=False):
	if not apifmt:
		return int(mktime(strptime(date,'%Y%m%d%H%M%S')))
	else:
		return int(
		mktime(
		strptime(date,'%Y-%m-%dT%H:%M:%SZ')
		)
		)

def fmt(ts,t=True):
	if t:
		return ts.replace('T',' ').replace('Z','')
	else:
		return ts.replace('T','').replace('Z','').replace(':','').replace('-','').replace(' ','')
			

def line(key,value,tabs=1):
	return '%s<%s>%s</%s>'%('\t'*tabs,key,value,key)
	
def out(r):
	
	g=[]
	for key,value in r:
		try:
			value = str(value)
		except:
			value = value.encode('utf-8')
		value = escape(value)
		g.append((key,value))
	
	
	g =dict(g)
	o='\n'.join([
	 '<WPEdit>'
	,line('EditType','change')
	,line('EditID',g['new_id'])
	,line('comment',g['comment'])
	,line('user',g['user_name'])
	,line('user_edit_count',g['user_edit_count'])
	,line('user_distinct_pages',g['user_top_edits'])
	,line('user_warns',g['user_warnings'])
	,line('prev_user',g['old_user'])
	,line('user_reg_time',g['user_registration_date'])
	,'\t<common>'
,		line('page_made_time',g['page_start_time'],2)

,	line('title',g['title'],2)
,	line('namespace',ns_translate[g['namespace']],2)
,	line('creator',g['page_creator'],2)
,	line('num_recent_edits',g['edits_to_page_in_last_two_weeks'],2)
,	line('num_recent_reversions',g['reversions_to_page_in_last_two_weeks'],2)
	, '\t</common>'
	, '\t<current>'
	,line('minor',g['minor'],2)

	,line('timestamp',g['new_timestamp'],2)
	,line('text','\n'+g['new_text']+'\n',2)
	,'\t</current>'
	, '\t<previous>'
	,line('timestamp',g['old_timestamp'],2)

	,line('text','\n'+g['old_text']+'\n',2)
	, '\t</previous>'
	
	, '</WPEdit>'])
	return o	

class editpuller(Thread):
	def __init__(self,relay,work):
		Thread.__init__(self)
		self.relay = relay
		self.work = work
		self.db =  connect(db='enwiki_p', host="enwiki-p.fastdb.toolserver.org", read_default_file="/home/tim1357/.my.cnf")
		self.c=self.db.cursor()
	def run(self):
		while True:
			id,title = self.work.get()
			title=title.strip()
			try:	#print 'Got assignment! Id= '+id+' ; title = '+title
				ts,pid,r = self.gen(id,title)
				ts=fmt(ts)
				(r['edits_to_page_in_last_two_weeks'],r['reversions_to_page_in_last_two_weeks'],r['page_creator'],r['page_start_time']
				) = self.query_page(pid,ts)
				ts=fmt(ts,False)
				(r['user_edit_count'],r['user_registration_date'],te,r['user_warnings'])=self.query_user(r['user_name'],ts)
				
				r['user_top_edits']=te if te else 0
				r=iter(r.items())
				self.relay.add((id,out(r)))
			except Exception,e:
				o(str(e)+' : '+repr((id,title)))
			self.work.task_done()
		
	def gen(self,id,title):
		r={}
		url="""http://en.wikipedia.org/w/api.php?action=query&format=json&rvlimit=2&prop=revisions&titles=%s&rvprop=timestamp|user|comment|flags|content|ids&rvstartid=%d"""%(quote(title),int(id))
		#print url
		url = urlopen(url).read()
		
		url = loads(url)[u'query'][u'pages']
		url = url[url.keys()[0]]
		r['title']=url[u'title']
		r['namespace']=url[u'ns']
		pageid=url[u'pageid']
		url = url[u'revisions']
		if url[0].has_key('comment'):
			r['comment']=url[0][u'comment']
		else:
				r['comment']=""
		if url[0].has_key(u'minor'):
			r['minor']='true'
		else:
				r['minor']="false"
		r['new_timestamp']=tounix(url[0][u'timestamp'],True)
		r['user_name']=url[0][u'user']
		parent_id=url[0][u'parentid']
		r['old_id']=parent_id
		r['new_id']=url[0][u'revid']
		r['new_text']=url[0]['*']
		try:
			r['old_user']=url[1]['user']
		except:
			r['old_user']=''
		try:	
			r['old_timestamp']=tounix(url[1][u'timestamp'],True)
		except:
			r['old_timestamp']=''
		try:
			r['old_text']=url[1]['*']
		except:
			r['old_text']=''
		
		
		return (url[0][u'timestamp'],pageid,r)
	
 	def query_page(self,id,ts = 'NOW()'):
	
		self.db.query('''select count(*), sum(case when rev_comment like 'Revert%%' then 1 else 0 end) from revision where rev_page=%d and rev_timestamp>= (SELECT DATE_FORMAT(DATE_SUB("%s",INTERVAL 2 week),'%%Y%%m%%d%%H%%i%%s'));'''%(id,ts))
		full,per= self.db.use_result().fetch_row(0)[0]
		r=[int(full) if full!=None else 0,int(per) if per!=None else 0]
		del full,per
		self.db.query('''select rev_timestamp, rev_user_text from revision where rev_page=%d and rev_deleted=0 having min(rev_timestamp);'''%id)
		min,user =self.db.use_result().fetch_row(0)[0]
		r.append(user)
		r.append(tounix(min))
		return r
	
	def query_user(self,uname,ts):
		if not is_ip(uname): query=('''select  user_editcount, user_registration, count(distinct rev_page) from user join revision on rev_user=user_id and rev_timestamp<='''+str(ts)+''' where user_name = %s;''',uname.encode('utf-8'))
		else: query=('''select count(*), min(rev_timestamp), count(distinct rev_page) from revision where rev_timestamp<='''+str(ts)+''' and rev_user_text=%s ;''',uname)
		self.c.execute(query[0],(query[1],))
		a,b,d=self.c.fetchall()[0]
		
		self.c.execute('''select count(*) from revision where rev_page = (select page_id from page where  page_namespace=3 and page_title=%s) and (
(rev_comment LIKE '%%warning%%')
OR
( rev_comment LIKE 'General note: Nonconstructive%%;')
OR
(rev_comment LIKE '%%Warning%%')
) and rev_timestamp<=%s;''',(uname.replace(' ','_').encode('utf-8'),ts))
		e=self.c.fetchall()[0][0]
	
		try:a=int(a)
		except:a=''
		try:b=tounix(str(b))
		except:b=''
		try:d=int(d)
		except:d=''
		try:e=int(e)
		except:e=''
		return (a,b,d,e)
		

class botfeeder():
	def __init__(self):
		self.q = Queue()
	
	def add(self,i):
		self.q.put(i)
	
	def run(self):
		print('<WPEditSet>\r\n')
		while True:
			id,xml = self.q.get()
			print xml
			stdout.flush()

			

class reader(Thread):
	def __init__(self,q):
		Thread.__init__(self)
		self.q=q
	def run(self):
		while 1:
			line =raw_input()
			if line.strip() =='>>>>> QUIT <<<<<':
				quit()
			line = line.strip().split('\t\t')
			self.q.put(line)
		
	
def main():
	now = time()
	
	
	difflist = Queue()
	workers = []
	
	bf = botfeeder()
	
	for i in range(4):
		workers.append(editpuller(bf,difflist))
	for i in workers:
		i.start()

	io=reader(difflist)
	io.start()

	bf.run()
			
			
		
if __name__=='''__main__''':
	main()
	#except Exception,e: 
	#	print 'Error: %s'%str(e) 
	quit()