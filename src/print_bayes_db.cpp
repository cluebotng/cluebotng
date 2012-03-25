#include <iostream>
#include <fstream>
#include <string>
#include "bayesdb.hpp"

using namespace std;
using namespace WPCluebot;

int main(int argc, char **argv) {
	if(argc != 2) {
		cout << "Usage: " << argv[0] << " <BayesianDatabaseFile>\n";
		return 1;
	}
	string dbfilename(argv[1]);
	BayesDB baydb;
	baydb.openDBForReading(dbfilename);
	baydb.printSortedDB();
}

