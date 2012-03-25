#ifndef _NEURALNET_HPP
#define _NEURALNET_HPP

#include "standardprocessors.hpp"
#include <floatfann.h>
#include <boost/thread.hpp>

namespace WPCluebot {


class RunAnn : public EditProcessor {
	public:
		RunAnn(libconfig::Setting & cfg) : EditProcessor(cfg) {
			isetprop = (const char *)configuration["input_set"];
			osetprop = (const char *)configuration["output_set"];
			std::string annfilename = (const char *)configuration["ann_file"];
			ann = fann_create_from_file(annfilename.c_str());
			if(!ann) throw std::runtime_error("Error loading ANN");
		}
		~RunAnn() {
			if(ann) {
				fann_destroy(ann);
			}
		}
		
		void process(Edit & ed) {
			boost::lock_guard<boost::mutex> lock(mut);
			std::vector<float> iset = ed.getProp<std::vector<float> >(isetprop);
			std::vector<fann_type> Fiset;
			for(std::vector<float>::iterator it = iset.begin(); it != iset.end(); ++it) Fiset.push_back(*it);
			
			if(Fiset.size() != fann_get_num_input(ann)) throw std::runtime_error("Inputs to ANN do not match ANN parameters");
			fann_type * Fouts = fann_run(ann, &Fiset.front());
			
			std::vector<float> oset;
			for(int i = 0; i < fann_get_num_output(ann); ++i) oset.push_back(Fouts[i]);
			
			ed.setProp<std::vector<float> >(osetprop, oset);
		}
	
	private:
		struct fann * ann;
		std::string isetprop;
		std::string osetprop;
		boost::mutex mut;
};

class WriteAnnTrainingData : public EditProcessor {
	public:
		WriteAnnTrainingData(libconfig::Setting & cfg) : EditProcessor(cfg) {
			isetprop = (const char *)configuration["input_set"];
			osetprop = (const char *)configuration["output_set"];
			outfilename = (const char *)configuration["filename"];
			tempfilename = outfilename + ".temp";
			if(configuration.exists("tempfilename")) tempfilename = (const char *)configuration["tempfilename"];
			dumpstream.exceptions(std::ofstream::badbit | std::ofstream::failbit);
			dumpstream.open(tempfilename.c_str());
			dumpstream.setf(std::ofstream::fixed);
			dumpstream.precision(10);
			n_edits = 0;
			n_inputs = 0;
			n_outputs = 0;
		}
		~WriteAnnTrainingData() {
			dumpstream.close();
			rmTempFile();
		}
	
		void process(Edit & ed) {
			boost::lock_guard<boost::mutex> lock(mut);
			std::vector<float> iset = ed.getProp<std::vector<float> >(isetprop);
			std::vector<float> oset = ed.getProp<std::vector<float> >(osetprop);
			if(n_edits) {
				if(iset.size() != n_inputs) throw std::runtime_error("ANN train data set size mismatch");
				if(oset.size() != n_outputs) throw std::runtime_error("ANN train data set size mismatch");
			} else {
				n_inputs = iset.size();
				n_outputs = oset.size();
			}
			writeFloatSet(iset);
			writeFloatSet(oset);
			++n_edits;
		}
		
		void finished() {
			if(!n_edits) return;
			dumpstream.close();
			dumpstream.open(outfilename.c_str());
			std::ifstream tstrm;
			tstrm.exceptions(std::ifstream::badbit);
			tstrm.open(tempfilename.c_str());
			dumpstream << n_edits << " " << n_inputs << " " << n_outputs << "\n";
			while(!tstrm.fail() && !tstrm.eof()) {
				char buf[1024];
				tstrm.read(buf, 1024);
				dumpstream.write(buf, tstrm.gcount());
			}
			tstrm.close();
		}
	
	private:
		std::string isetprop;
		std::string osetprop;
		std::string tempfilename;
		std::string outfilename;
		std::ofstream dumpstream;
		int n_edits;
		int n_inputs;
		int n_outputs;
		boost::mutex mut;
		
		void writeFloatSet(std::vector<float> & f) {
			bool first = true;
			for(std::vector<float>::iterator it = f.begin(); it != f.end(); ++it) {
				if(!first) dumpstream << " ";
				first = false;
				dumpstream << *it;
			}
			dumpstream << "\n";
		}
		
		void rmTempFile() {
			unlink(tempfilename.c_str());
		}
};


}

#endif
