#include <iostream>
#include <string>
#include <deque>
#include <boost/thread.hpp>
#include <boost/shared_ptr.hpp>
#include <boost/asio.hpp>
#include <libconfig.h++>
#include "framework.hpp"
#include "standardprocessors.hpp"
#include "bayesprocessors.hpp"
#include "xmleditloader.hpp"
#include "neuralnet.hpp"

using namespace WPCluebot;
using namespace std;
using namespace libconfig;

void printUsage(const char * name) {
	cout << "Usage: " << name << " <-f <EditFile> | -l> [-m <ChainName>] [-c <ConfigDirectory>]\n";
	exit(1);
}


libconfig::Setting * globcfg_root = NULL;
libconfig::Setting * globcfg_chains = NULL;

void addConfigChain(EditProcessChain & procchain, Setting & configchain, Setting & linkcfgs, Setting & chaincfgs);

class SubchainModule : public EditProcessor {
	public:
		SubchainModule(libconfig::Setting & cfg) : EditProcessor(cfg) {
			libconfig::Setting & chainspec = configuration["chain"];
			addConfigChain(chain, chainspec, *globcfg_root, *globcfg_chains);
		}
		
		void process(Edit & ed) {
			chain.process(ed);
		}
		
		void finished() {
			chain.finished();
		}
		
	private:
		EditProcessChain chain;
};


void addChainLink(EditProcessChain & procchain, const string & modulename, Setting & moduleconfig) {
	if(modulename == "character_counts") {
		procchain.appendProcessor(boost::shared_ptr<EditProcessor>(new CharacterCounter(moduleconfig)));
	} else if(modulename == "edit_dump") {
		procchain.appendProcessor(boost::shared_ptr<EditProcessor>(new EditDump(moduleconfig)));
	} else if(modulename == "print_progress") {
		procchain.appendProcessor(boost::shared_ptr<EditProcessor>(new ProgressPrinter(moduleconfig)));
	} else if(modulename == "fast_string_search") {
		procchain.appendProcessor(boost::shared_ptr<EditProcessor>(new FastStringSearch(moduleconfig)));
	} else if(modulename == "posix_regex_search") {
		procchain.appendProcessor(boost::shared_ptr<EditProcessor>(new PosixRegexSearch(moduleconfig)));
	} else if(modulename == "posix_regex_replace") {
		procchain.appendProcessor(boost::shared_ptr<EditProcessor>(new PosixRegexReplace(moduleconfig)));
	} else if(modulename == "misc_text_metrics") {
		procchain.appendProcessor(boost::shared_ptr<EditProcessor>(new MiscTextMetrics(moduleconfig)));
	} else if(modulename == "character_replace") {
		procchain.appendProcessor(boost::shared_ptr<EditProcessor>(new CharacterReplace(moduleconfig)));
	} else if(modulename == "word_separator") {
		procchain.appendProcessor(boost::shared_ptr<EditProcessor>(new WordSeparator(moduleconfig)));
	} else if(modulename == "multi_word_separator") {
		procchain.appendProcessor(boost::shared_ptr<EditProcessor>(new MultiWordSeparator(moduleconfig)));
	} else if(modulename == "wordset_diff") {
		procchain.appendProcessor(boost::shared_ptr<EditProcessor>(new WordSetDiff(moduleconfig)));
	} else if(modulename == "wordset_compare") {
		procchain.appendProcessor(boost::shared_ptr<EditProcessor>(new WordSetCompare(moduleconfig)));
	} else if(modulename == "misc_raw_word_metrics") {
		procchain.appendProcessor(boost::shared_ptr<EditProcessor>(new MiscRawWordMetrics(moduleconfig)));
	} else if(modulename == "word_character_replace") {
		procchain.appendProcessor(boost::shared_ptr<EditProcessor>(new WordCharacterReplace(moduleconfig)));
	} else if(modulename == "word_finder") {
		procchain.appendProcessor(boost::shared_ptr<EditProcessor>(new WordFinder(moduleconfig)));
	} else if(modulename == "expression_eval") {
		procchain.appendProcessor(boost::shared_ptr<EditProcessor>(new ExpressionEval(moduleconfig)));
	} else if(modulename == "float_set_creator") {
		procchain.appendProcessor(boost::shared_ptr<EditProcessor>(new FloatSetCreator(moduleconfig)));
	} else if(modulename == "bayesian_training_data_creator") {
		procchain.appendProcessor(boost::shared_ptr<EditProcessor>(new BayesTrainDataCreator(moduleconfig)));
	} else if(modulename == "bayesian_scorer") {
		procchain.appendProcessor(boost::shared_ptr<EditProcessor>(new BayesScorer(moduleconfig)));
	} else if(modulename == "write_properties") {
		procchain.appendProcessor(boost::shared_ptr<EditProcessor>(new WriteProperties(moduleconfig)));
	} else if(modulename == "write_ann_training_data") {
		procchain.appendProcessor(boost::shared_ptr<EditProcessor>(new WriteAnnTrainingData(moduleconfig)));
	} else if(modulename == "run_ann") {
		procchain.appendProcessor(boost::shared_ptr<EditProcessor>(new RunAnn(moduleconfig)));
	} else if(modulename == "trial_run_report") {
		procchain.appendProcessor(boost::shared_ptr<EditProcessor>(new TrialRunReport(moduleconfig)));
	} else if(modulename == "charset_conv") {
		procchain.appendProcessor(boost::shared_ptr<EditProcessor>(new CharsetConverter(moduleconfig)));
	} else if(modulename == "all_prop_charset_conv") {
		procchain.appendProcessor(boost::shared_ptr<EditProcessor>(new AllPropCharsetConverter(moduleconfig)));
	} else if(modulename == "apply_threshold") {
		procchain.appendProcessor(boost::shared_ptr<EditProcessor>(new ApplyThreshold(moduleconfig)));
	} else if(modulename == "chain") {
		procchain.appendProcessor(boost::shared_ptr<EditProcessor>(new SubchainModule(moduleconfig)));
	} else if(modulename == "quote_separator") {
		procchain.appendProcessor(boost::shared_ptr<EditProcessor>(new StandardQuoteSeparator(moduleconfig)));
	} else {
		throw std::runtime_error("Unknown module/chain link");
	}
}

class EditThreadPool {
	public:
		EditThreadPool(EditProcessChain & chainr, int nthreads = 3, int queue_size = 16) : chain(chainr) {
			max_queue_size = queue_size;
			stopflag = false;
			for(int i = 0; i < nthreads; ++i) {
				boost::shared_ptr<boost::thread> tptr(new boost::thread(boost::ref(*this)));
				threads.push_back(tptr);
			}
		}
		~EditThreadPool() {
			stopThreads();
		}
		
		void stopThreads() {
			if(threads.size()) {
				{
					boost::lock_guard<boost::mutex> lock(mut);
					stopflag = true;
				}
				thread_wait_cond.notify_all();
				for(std::vector<boost::shared_ptr<boost::thread> >::iterator it = threads.begin(); it != threads.end(); ++it) {
					(*it)->join();
				}
				threads.clear();
			}
		}
		
		void waitForAllDataProcessed() {
			boost::unique_lock<boost::mutex> lock(mut);
			while(editqueue.size() != 0) {
				main_wait_cond.wait(lock);
			}
		}
		
		void threadMain() {
			for(;;) {
				Edit ed;
				{
					boost::unique_lock<boost::mutex> lock(mut);
					while(editqueue.size() == 0 && !stopflag) {
						thread_wait_cond.wait(lock);
					}
					if(stopflag) return;
					ed = editqueue.back();
					editqueue.pop_back();
				}
				main_wait_cond.notify_one();
				chain.process(ed);
			}
		}
		void operator()() {
			threadMain();
		}
		
		void submitEdit(Edit & ed) {
			boost::unique_lock<boost::mutex> lock(mut);
			while(editqueue.size() >= max_queue_size) {
				main_wait_cond.wait(lock);
			}
			editqueue.push_front(ed);
			thread_wait_cond.notify_one();
		}
		
	private:
		boost::mutex mut;
		boost::condition_variable thread_wait_cond;
		boost::condition_variable main_wait_cond;
		std::deque<Edit> editqueue;
		bool stopflag;
		EditProcessChain & chain;
		int max_queue_size;
		std::vector<boost::shared_ptr<boost::thread> > threads;
};

class NetworkSource {
	public:
		NetworkSource(EditProcessChain & chainr, libconfig::Setting & cfg) : chain(chainr), rootconfig(cfg) {
			for(int i = 0; i < rootconfig["network_output_properties"].getLength(); ++i) {
				string s = (const char *)rootconfig["network_output_properties"][i];
				outprops.push_back(s);
			}
		}
		
		void listen(int port) {
			using boost::asio::ip::tcp;
			boost::asio::io_service io_service;
			tcp::acceptor acceptor(io_service, tcp::endpoint(tcp::v4(), port));
			for(;;) {
				boost::shared_ptr<tcp::socket> socket(new tcp::socket(io_service));
				acceptor.accept(*socket);
				boost::thread thread(boost::ref(*this), socket);
			}
		}
	
		EditProcessChain & chain;
		libconfig::Setting & rootconfig;
		std::vector<std::string> outprops;
		
		void operator()(boost::shared_ptr<boost::asio::ip::tcp::socket> socket) {
			try {
				string cmsg;
				XMLEditParser editparser(rootconfig["xml_edit_parser"]);
				editparser.startParsing();
				//cout << "Sending wpeditset\n";
				cmsg = "<WPEditSet>\n";
				boost::asio::write(*socket, boost::asio::buffer(cmsg), boost::asio::transfer_all());
				for(;;) {
					std::vector<char> rd;
					boost::system::error_code er;
					char cbuf[4192];
					//cout << "reading\n";
					size_t len = socket->read_some(boost::asio::buffer(cbuf, 4192), er);
					//cout << "read\n";
					rd.assign(cbuf, cbuf + len);
					if(er == boost::asio::error::eof) break; else if(er) throw boost::system::system_error(er);
					if(rd.size()) {
						editparser.submitData(&rd.front(), rd.size());
					}
					//cout << "looping available\n";
					while(editparser.availableEdits()) {
						//cout << "got edit\n";
						Edit ed = editparser.nextEdit();
						if(rootconfig.exists("net_require_properties")) {
							bool skipp = false;
							for(int p = 0; p < rootconfig["net_require_properties"].getLength(); ++p) {
								string pname = (const char *)rootconfig["net_require_properties"][p];
								if(!ed.hasProp(pname)) {
									skipp = true;
									break;
								}
							}
							if(skipp) continue;
						}
						//cout << "processing edit\n";
						chain.process(ed);
						std::stringstream sstrm;
						ed.dumpProps(sstrm, outprops);
						string outstr = string("<WPEdit>\n") + string(sstrm.str()) + string("</WPEdit>\n");
						//cout << "sending response\n";
						boost::asio::write(*socket, boost::asio::buffer(outstr), boost::asio::transfer_all());
					}
					if(editparser.gotEndTag()) break;
				}
				cmsg = "</WPEditSet>\n";
				boost::asio::write(*socket, boost::asio::buffer(cmsg), boost::asio::transfer_all());
			} catch (const std::exception & e) {
				cout << "Error: " << e.what() << "\n";
			} catch (...) {
				cout << "Error.\n";
			}
		}
};

void addConfigChain(EditProcessChain & procchain, Setting & configchain, Setting & linkcfgs, Setting & chaincfgs) {
	for(int i = 0; i < configchain.getLength(); ++i) {
		string chainelname = configchain[i];
		// Check if there's a config block for this chain element
		if(linkcfgs.exists(chainelname)) {
			Setting & linkcfg = linkcfgs[chainelname];
			string modulename = chainelname;
			if(linkcfg.exists("module")) modulename = (const char *)linkcfg["module"];
			addChainLink(procchain, modulename, linkcfg);
		} else if(chaincfgs.exists(chainelname)) {	// Check if it's the name of another chain
			addConfigChain(procchain, chaincfgs[chainelname], linkcfgs, chaincfgs);
		} else {	// Assume it's the name of a module without a configuration block
			Setting & blanksetting = linkcfgs.add(chainelname, Setting::TypeGroup);
			addChainLink(procchain, chainelname, blanksetting);
		}
	}
}

int main(int argc, char **argv) {
	string editfile;
	bool editsfromfile = false;
	bool editsfromnet = false;
	int netport;
	string chainname = "default";
	string configdir = "./conf";
	
	if(argc < 2) printUsage(argv[0]);
	int opt;
	while((opt = getopt(argc, argv, "f:m:c:l")) != -1) {
		switch(opt) {
			case 'f':
				editsfromfile = true;
				editfile.assign(optarg);
				break;
			case 'm':
				chainname.assign(optarg);
				break;
			case 'c':
				configdir.assign(optarg);
				break;
			case 'l':
				editsfromnet = true;
				break;
			default:
				printUsage(argv[0]);
		}
	}
	
	Config config;
	try {
		config.readFile((configdir + "/cluebotng.conf").c_str());
	} catch (const ParseException & e) {
		cerr << "Error parsing configuration file " << e.getFile() << " on line " << e.getLine() << ": " << e.getError() << "\n";
		return 1;
	}
	Setting & rootconfig = config.getRoot();
	globcfg_root = &rootconfig;
	
	EditProcessChain chain;
	
	if(!rootconfig.exists("chains")) throw std::runtime_error("Config file has no chains group.");
	Setting & configchains = rootconfig["chains"];
	globcfg_chains = &configchains;
	if(!configchains.exists(chainname)) throw std::runtime_error("No such chain.");
	Setting & rootchaincfg = configchains[chainname];
	
	addConfigChain(chain, rootchaincfg, rootconfig, configchains);
	
	if(editsfromnet) {
		netport = rootconfig["listen_port"];
		NetworkSource ns(chain, rootconfig);
		ns.listen(netport);
	}
	
	
	int num_edits = 0;
	if(editsfromfile) {
		if(!rootconfig.exists("xml_edit_parser")) throw std::runtime_error("No xml_edit_parser section of config.");
		XMLEditParser editparser(rootconfig["xml_edit_parser"]);
		editparser.parseFile_start(editfile);
	
#ifndef SINGLETHREAD
		int nthreads = 3;
		if(rootconfig.exists("threads")) nthreads = rootconfig["threads"];
		EditThreadPool tpool(chain, nthreads);
#endif
		
		while(editparser.parseFile_more()) {
			while(editparser.availableEdits()) {
				Edit ed = editparser.nextEdit();
				if(rootconfig.exists("file_require_properties")) {
					bool skipp = false;
					for(int p = 0; p < rootconfig["file_require_properties"].getLength(); ++p) {
						string pname = (const char *)rootconfig["file_require_properties"][p];
						if(!ed.hasProp(pname)) {
							skipp = true;
							break;
						}
					}
					if(skipp) continue;
				}
				if(editparser.parseFile_size()) {
					ed.setProp<unsigned long long int>("input_xml_file_size", editparser.parseFile_size());
					ed.setProp<unsigned long long int>("input_xml_file_pos", editparser.parseFile_pos());
				}
#ifdef SINGLETHREAD
				chain.process(ed);
#else
				tpool.submitEdit(ed);
#endif
				++num_edits;
			}
		}
#ifndef SINGLETHREAD
		tpool.waitForAllDataProcessed();
		tpool.stopThreads();
#endif
		chain.finished();
		cout << "Processed " << num_edits << " edits.\n";
	}
}
