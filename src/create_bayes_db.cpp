#include <iostream>
#include <fstream>
#include <string>
#include <stdlib.h>
#include "bayesdb.hpp"

using namespace std;
using namespace WPCluebot;

void getLineSegs(istream & strm, int & isvand, string & word) {
	string line;
	word.clear();
	getline(strm, line);
	if(line.size() < 3) {
		return;
	}
	const char * str = line.c_str();
	const char * num = str;
	for(; *str; ++str) if(*str == ' ') break;
	if(*str != ' ') return;
	string numstr(num, str - num);
	isvand = atoi(numstr.c_str());
	str++;
	word.assign(str);
}

int main(int argc, char **argv) {
	if(argc != 3) {
		cout << "Usage: " << argv[0] << " <BayesianDatabaseFile> <TrainingDataFile>\n";
		return 1;
	}
	string trainfilename(argv[2]);
	string dbfilename(argv[1]);
	ifstream trainfile;
	trainfile.open(trainfilename.c_str());
	BayesDB baydb;
	baydb.createNew(dbfilename);
	unsigned int i = 0;
	cout << "Processing words ...\n";
	while(!trainfile.eof() && !trainfile.bad() && !trainfile.fail()) {
		int isvand;
		string word;
		//trainfile >> isvand;
		//trainfile >> word;
		getLineSegs(trainfile, isvand, word);
		if(word.size() == 0) continue;
		baydb.addWord(word, isvand);
		if(word == "_EDIT_TOTALS") {
			++i;
			if(i % 27 == 0) {
				cout << "\x1B[20D" << i;
				cout.flush();
			}
		}
	}
	cout << "\x1B[20D" << i << "\n";
	cout << "Pruning ...\n";
	baydb.pruneDB(3, 0.15);
}

