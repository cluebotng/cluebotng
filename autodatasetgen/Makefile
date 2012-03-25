all: autodatasetgen

autodatasetgen: autodatasetgen.6
	6l -o autodatasetgen autodatasetgen.6

autodatasetgen.6: autodatasetgen.go credentials.go
	6g -o autodatasetgen.6 autodatasetgen.go credentials.go

deploy: all
	bzip2 -z9k autodatasetgen
	scp autodatasetgen.bz2 login.toolserver.org:~/
	rm -f autodatasetgen.bz2

clean:
	rm -f *.6 autodatasetgen *~
