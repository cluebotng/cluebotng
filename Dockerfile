FROM debian:latest
ADD src/ /usr/src/cbng-core
ADD data/ /usr/src/cbng-data
RUN apt-get clean
RUN apt-get update

# Deps
RUN apt-get install -y build-essential libboost-system-dev libboost-thread-dev libexpat1-dev libmatheval-dev libfann-dev libconfig++-dev libboost-dev wget libdb5.3++-dev file libfl-dev

# Libiconv
RUN cd /usr/src && wget http://ftp.gnu.org/pub/gnu/libiconv/libiconv-1.14.tar.gz && tar -xvf libiconv-1.14.tar.gz
RUN wget -O /usr/src/libiconv-1.14.patch1 https://gist.githubusercontent.com/paulczar/5493708/raw/b8e40037af5c882b3395372093b78c42d6a7c06e/gistfile1.txt
RUN cd /usr/src/libiconv-1.14 && patch srclib/stdio.in.h < /usr/src/libiconv-1.14.patch1
RUN cd /usr/src/libiconv-1.14 && ./configure --enable-static && make && make install
RUN ldconfig -v

# Cluebot build
RUN cd /usr/src/cbng-core && make clean
RUN cd /usr/src/cbng-core && make

# Stash bins
RUN mkdir -p /opt/cbng/core/bin /opt/cbng/core/etc /opt/cbng/core/var
RUN cp /usr/src/cbng-core/cluebotng /opt/cbng/core/bin/cluebotng
RUN cp /usr/src/cbng-core/create_ann /opt/cbng/core/bin/create_ann
RUN cp /usr/src/cbng-core/create_bayes_db /opt/cbng/core/bin/create_bayes_db
RUN cp /usr/src/cbng-core/print_bayes_db /opt/cbng/core/bin/print_bayes_db
RUN chmod 755 /opt/cbng/core/bin/*

# Build binary dbs
RUN /opt/cbng/core/bin/create_bayes_db /opt/cbng/core/var/bayes.db /usr/src/cbng-data/main_bayes_train.dat
RUN /opt/cbng/core/bin/create_bayes_db /opt/cbng/core/var/two_bayes.db /usr/src/cbng-data/two_bayes_train.dat
RUN cp /usr/src/cbng-data/main_ann.fann /opt/cbng/core/var/main_ann.fann

# Cleanup
RUN rm -rf /usr/src/*
RUN apt-get clean

# Run time settings
WORKDIR /opt/cbng/core/
VOLUME /opt/cbng/core/etc

ENV RUN_MODE=live_run
EXPOSE 3565

# Run!
CMD /opt/cbng/core/bin/cluebotng -l -c /opt/cbng/core/etc -m $RUN_MODE