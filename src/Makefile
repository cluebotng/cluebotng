CFLAGS?=-g -O2

all: cluebotng create_ann create_bayes_db print_bayes_db

cluebotng: bayesdb.hpp framework.hpp xmleditloader.hpp bayesprocessors.hpp faststringops.hpp neuralnet.hpp standardprocessors.hpp main.cpp
	g++ $(CFLAGS) main.cpp -static -lexpat -ldb_cxx -liconv -lfann -lconfig++ -lboost_thread -lboost_system -lm -lmatheval -lfl -Wl,-Bdynamic -lpthread -ocluebotng

create_ann: create_ann.cpp
	g++ ${CFLAGS} create_ann.cpp -static -lfann -Wl,-Bdynamic -ocreate_ann

create_bayes_db: create_bayes_db.cpp bayesdb.hpp
	g++ ${CFLAGS} create_bayes_db.cpp -static -ldb_cxx -Wl,-Bdynamic -lpthread -ocreate_bayes_db

print_bayes_db: print_bayes_db.cpp bayesdb.hpp
	g++ ${CFLAGS} print_bayes_db.cpp -static -ldb_cxx -Wl,-Bdynamic -lpthread -oprint_bayes_db

clean:
	rm -f cluebotng create_bayes_db print_bayes_db create_ann


