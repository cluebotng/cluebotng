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

