CFLAGS?=-g -O2

all: cluebotng create_ann create_bayes_db print_bayes_db

cluebotng: bayesdb.hpp framework.hpp xmleditloader.hpp bayesprocessors.hpp faststringops.hpp neuralnet.hpp standardprocessors.hpp main.cpp
	g++ $(CFLAGS) main.cpp -lexpat -lmatheval -ldb_cxx -liconv -lfann -lconfig++ -lboost_thread -lboost_system -lm -ocluebotng

create_ann: create_ann.cpp
	g++ ${CFLAGS} create_ann.cpp -lfann -ocreate_ann

create_bayes_db: create_bayes_db.cpp bayesdb.hpp
	g++ ${CFLAGS} create_bayes_db.cpp -ldb_cxx -ocreate_bayes_db

print_bayes_db: print_bayes_db.cpp bayesdb.hpp
	g++ ${CFLAGS} print_bayes_db.cpp -ldb_cxx -oprint_bayes_db

clean:
	rm -f cluebotng create_bayes_db print_bayes_db create_ann


