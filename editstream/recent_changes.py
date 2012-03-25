#!/usr/bin/env python -u

from socket import socket,AF_INET,SOCK_STREAM
from urllib2 import urlopen
from sys import stdout
from re import compile,findall
def get_whitelist():
       
        whitelist = urlopen('http://en.org/w/index.php?title=Wikipedia:Huggle/Whitelist&action=raw&templates=expand').read().split('\n')[2:-1]
        return whitelist
        
class bot:
        def __init__(self):
                self.regexps = compile(r'\:.*? PRIVMSG (?P<channel>.*?) \:\x0314\[\[\x0307(?P<title>.*?)\x0314\]\]\x034 .*?\x0302http://.*?/w/index\.php\?diff\=(?P<diff>[0-9]*)\&oldid\=(?P<oldid>[0-9]*)\x03 \x035\*\x03 \x0303(?P<user>.*?)\x03 \x035\*\x03 \((?P<diffsize>[\+\-][0-9]*)\) \x0310(?P<comment>.*?)\x03')
                self.connect()
                self.whitelist = get_whitelist()
             	self.loadignore()
                #self.flog = log()a
                        #self.loadregexp
        def connect(self):
                        server = 'irc.wikimedia.org'
                        port = 6667
                       
                        nickname = 'DASHBotAV'
                        rc = socket(AF_INET, SOCK_STREAM)
                        rc.connect((str(server), int(port)))
                        rc.recv(4096)
                        nick = '%s' %(nickname)
                        rc.send('NICK %s\r\n' %nick)
                        
                        rc.send('USER %s %s %s :%s\r\n' %(nick, nick, nick, nick))
                        rc.send('JOIN %s\r\n' %'#en.wikipedia')
                        self.rc = rc
        def loadignore(self):
               
                x = urlopen('http://en.wikipedia.org/w/index.php?title=User:ClueBot_NG/Excluded_titles&action=raw&templates=expand').read()
                ignorexps = findall('\n(?!#)(.*?);;',x)
                del x
                self.ignorepage = []
                for p in ignorexps:
                        try:
                                p = compile(p)
                        except Exception, e:
                                #output( str(e))
                                continue
                        self.ignorepage.append(p)
        def run(self):
        		
                    
                     now=10
                     while True:

                     	try:
                     		d = self.rc.recv(4096)
                     	except KeyboardInterrupt:
                     		print '>'*5,'QUIT','<'*5
                     		quit()
                     	except:
                     		del self.rc
                     		self.connect()
                     		continue
                     	#print d
                     	d = d.decode('utf-8', 'replace')
                     	if 'PING' in d:
                                        self.rc.send('PONG ' + d.split()[1] + '\r\n')
                        elif d == '':
                        	try:
                        		self.rc.send('QUIT\r\n')
                        		self.rc.close()
                        		del self.rc
                        	except:
                        		#socket error
                        		pass
                        	self.connect()                        	
                        		
                     	else: 
                                        self.handlemsg(d)
       
        def handlemsg(self,d):
                        if not self.regexps.match(d):
                        
                                return
                        
                        msg = self.regexps.match(d).groupdict()
                     #   print msg
                        del d
                        
                        if msg['title'] == 'User:Tim1357/Exclusion list.css':
                                self.loadignore()
                 
                        
                        if msg['user'].encode('utf-8') in self.whitelist:
                                return
                         
                        for r in self.ignorepage:
                                if r.match(msg['title']) and r.match(msg['title']).group() == msg['title']:
                                        return
                        
                        
                    
                        
                        print( msg['diff'].encode('utf-8')+'\t\t'+msg['title'].encode('utf-8'))
			stdout.flush()
       
if __name__=='__main__':bot().run()