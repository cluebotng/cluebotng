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
#include <fann.h>
#include <iostream>
#include <string>
#include <fstream>
#include <vector>
#include <stdlib.h>

using namespace std;

int main(int argc, char **argv) {
    if(argc < 6) {
        cout << "Usage: " << argv[0] << " <AnnFile> <TrainingData> <MaxEpochs> <DesiredError> <HiddenLayerSize> [HiddenLayer2Size ...]\n";
        return 1;
    }
    string annfilename(argv[1]);
    string trainfilename(argv[2]);
    vector<unsigned int> layersizes;
    layersizes.push_back(0);
    for(int i = 5; i < argc; ++i) layersizes.push_back(atoi(argv[i]));

    ifstream trainfile;
    trainfile.open(trainfilename.c_str());
    int numdata, inputs, outputs;
    trainfile >> numdata;
    trainfile >> inputs;
    trainfile >> outputs;
    trainfile.close();
    layersizes[0] = inputs;
    layersizes.push_back(outputs);

    int maxepochs = atoi(argv[3]);
    float deserror = atof(argv[4]);

    struct fann * ann = fann_create_standard_array(layersizes.size(), &layersizes.front());
    cout << "Inputs: " << inputs << "  Outputs: " << outputs << "\n";
    fann_train_on_file(ann, trainfilename.c_str(), maxepochs, 1, deserror);
    cout << "Saving file.\n";
    fann_save(ann, annfilename.c_str());
    fann_destroy(ann);
}

