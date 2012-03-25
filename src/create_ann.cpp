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

