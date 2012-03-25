A running Cluebot-NG install has two main components - the core and the interface to Wikipedia.

Core
====

The core is written in C++ and listens on a TCP socket for communication from the Wikipedia interface.  It can be compiled by typing 'make', but has a number of prerequisites.  For several of these prerequisites, it uses relatively new features, so the version is important.  If you have all of these prerequisites but still get compile errors, try installing the latest version.

Prerequisites:
* Expat 2.0.1, http://expat.sourceforge.net/
* MathEval 1.1.7, http://www.gnu.org/software/libmatheval/
* Berkeley DB 4.x C++ Bindings, http://www.oracle.com/technetwork/database/berkeleydb/
* libiconv 1.13, http://www.gnu.org/software/libiconv/
* libfann 2.1.0, http://leenissen.dk/fann/
* libconfig 1.4.5 C++ Bindings, http://www.hyperrealm.com/libconfig/
* Boost 1.40.0, http://www.boost.org/

Interface to Wikipedia
======================

The interface to Wikipedia is written in PHP and requires a PHP interpreter.
