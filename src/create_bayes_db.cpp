/*
 * Copyright (C) 2015 Jacobi Carter and Chris Breneman
 *
 * This file is part of ClueBot NG.
 *
 * ClueBot NG is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * ClueBot NG is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with ClueBot NG.  If not, see <http://www.gnu.org/licenses/>.
 */
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

