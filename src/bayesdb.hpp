#include <iostream>
#include <string>
#include <db_cxx.h>
#include <stdexcept>
#include <string.h>
#include <stdlib.h>
#include <vector>
#include <utility>
#include <algorithm>

#ifndef _BAYESDB_HPP
#define _BAYESDB_HPP

namespace WPCluebot {


struct BayesDBData {
	unsigned int good_edits;	// Number of good edits word occurs in
	unsigned int bad_edits;		// Number of bad edits word occurs in
	
	BayesDBData(unsigned int ge = 0, unsigned int be = 0) {
		good_edits = ge;
		bad_edits = be;
	}
};

class BayesDB {
	public:
		BayesDB() : db(NULL, 0), db_opened(false), have_cached_total(false) {}
		~BayesDB() {
			if(db_opened) db.close(0);
		}
	
		void createNew(const std::string & fname) {
			db.open(NULL, fname.c_str(), NULL, DB_BTREE, DB_CREATE | DB_TRUNCATE, 0);
			db_opened = true;
		}
		
		void openDB(const std::string & fname) {
			db.open(NULL, fname.c_str(), NULL, DB_BTREE, 0, 0);
			db_opened = true;
		}
		
		void openDBForReading(const std::string & fname) {
			db.open(NULL, fname.c_str(), NULL, DB_BTREE, DB_RDONLY, 0);
			db_opened = true;
			getTotals(cached_total_good, cached_total_bad);
			have_cached_total = true;
		}
		
		/* Adds a single word pertinent to a single edit */
		void addWord(const std::string & word, bool is_bad) {
			Dbt key(const_cast<void *>(reinterpret_cast<const void *>(word.c_str())), word.size());
			Dbt data;
			BayesDBData bdata;
			if(db.get(NULL, &key, &data, 0) != DB_NOTFOUND) {
				int s = data.get_size();
				if(s != sizeof(bdata)) throw std::runtime_error("Bayesian DB returned wrong record size");
				void * vdata = data.get_data();
				memcpy((void *)&bdata, vdata, s);
			}
			if(is_bad) {
				bdata.bad_edits++;
			} else {
				bdata.good_edits++;
			}
			data.set_data((void *)&bdata);
			data.set_size(sizeof(bdata));
			db.put(NULL, &key, &data, 0);
		}
		/* Increments the edit counters */
		void addEdit(bool is_bad) {
			addWord("_EDIT_TOTALS", is_bad);
		}
		
		void getWord(const std::string & word, unsigned int & good_count, unsigned int & bad_count) {
			Dbt key(const_cast<void *>(reinterpret_cast<const void *>(word.c_str())), word.size());
			Dbt data;
			BayesDBData bdata;
			good_count = 0;
			bad_count = 0;
			if(db.get(NULL, &key, &data, 0) != DB_NOTFOUND) {
				int s = data.get_size();
				if(s != sizeof(bdata)) throw std::runtime_error("Bayesian DB returned wrong record size");
				void * vdata = data.get_data();
				memcpy((void *)&bdata, vdata, s);
				good_count = bdata.good_edits;
				bad_count = bdata.bad_edits;
			}
		}
		void getTotals(unsigned int & good_edits, unsigned int & bad_edits) {
			getWord("_EDIT_TOTALS", good_edits, bad_edits);
		}
		
		// Pruning is based on two factors.
		// 1. All words with a total number of edits less that minimum_edits are discarded
		// 2. All words with a vandal-ness close to 0.5 are discarded (where vandal-ness differs from 0.5 by less than min_median_dev)
		void pruneDB(unsigned int minimum_edits = 3, float min_median_dev = 0.3) {
			unsigned int total_good;
			unsigned int total_bad;
			getTotals(total_good, total_bad);
			Dbc * cur;
			db.cursor(NULL, &cur, 0);
			if(cur) {
				Dbt key, data;
				while(cur->get(&key, &data, DB_NEXT) == 0) {
					std::string word((char *)key.get_data(), key.get_size());
					if(word == "_EDIT_TOTALS") continue;
					
					BayesDBData bdata;
					int s = data.get_size();
					if(s != sizeof(bdata)) throw std::runtime_error("Bayesian DB returned wrong record size");
					void * vdata = data.get_data();
					memcpy((void *)&bdata, vdata, s);
					
					unsigned int nedits = bdata.good_edits + bdata.bad_edits;
					float vand_prob = (float)bdata.bad_edits / (float)total_bad;
					float good_prob = (float)bdata.good_edits / (float)total_good;
					float vandalness = vand_prob / (vand_prob + good_prob);
					float vmeddev = 0.5 - vandalness;
					if(vmeddev < 0) vmeddev = 0 - vmeddev;
					
					if(vmeddev < min_median_dev || nedits < minimum_edits) {
						cur->del(0);
					}
				}
				cur->close();
			}
		}
		
		void printDB() {
			using std::cout;
			unsigned int total_good;
			unsigned int total_bad;
			getTotals(total_good, total_bad);
			cout << "# VANDAL-NESS   WORD   GOOD_EDITS   BAD_EDITS\n";
			Dbc * cur;
			db.cursor(NULL, &cur, 0);
			if(cur) {
				Dbt key, data;
				while(cur->get(&key, &data, DB_NEXT) == 0) {
					BayesDBData bdata;
					int s = data.get_size();
					if(s != sizeof(bdata)) throw std::runtime_error("Bayesian DB returned wrong record size");
					void * vdata = data.get_data();
					memcpy((void *)&bdata, vdata, s);
					
					float vand_prob = (float)bdata.bad_edits / (float)total_bad;
					float good_prob = (float)bdata.good_edits / (float)total_good;
					float vandalness = vand_prob / (vand_prob + good_prob);
					
					std::string word((char *)key.get_data(), key.get_size());
					cout << vandalness << " " << word << " " << bdata.good_edits << " " << bdata.bad_edits << "\n";
				}
				cur->close();
			}
		}
		
		void printSortedDB() {
			//std::vector<std::pair<float,std::string> > dbvec;
			std::vector<dbvecinf> dbvec;
			using std::cout;
			unsigned int total_good;
			unsigned int total_bad;
			getTotals(total_good, total_bad);
			Dbc * cur;
			db.cursor(NULL, &cur, 0);
			if(cur) {
				Dbt key, data;
				while(cur->get(&key, &data, DB_NEXT) == 0) {
					BayesDBData bdata;
					int s = data.get_size();
					if(s != sizeof(bdata)) throw std::runtime_error("Bayesian DB returned wrong record size");
					void * vdata = data.get_data();
					memcpy((void *)&bdata, vdata, s);
					
					float vand_prob = (float)bdata.bad_edits / (float)total_bad;
					float good_prob = (float)bdata.good_edits / (float)total_good;
					float vandalness = vand_prob / (vand_prob + good_prob);
					
					std::string word((char *)key.get_data(), key.get_size());
					//dbvec.push_back(std::pair<float,std::string>(vandalness, word));
					dbvecinf dbvi;
					dbvi.score = vandalness;
					dbvi.word = word;
					dbvi.good_edits = bdata.good_edits;
					dbvi.bad_edits = bdata.bad_edits;
					dbvec.push_back(dbvi);
				}
				cur->close();
			}
			std::sort(dbvec.begin(), dbvec.end());
			/*for(std::vector<std::pair<float,std::string> >::iterator it = dbvec.begin(); it != dbvec.end(); ++it) {
				if((*it).second != "_EDIT_TOTALS") cout << (*it).second << " " << (*it).first << "\n";
			}*/
			for(std::vector<dbvecinf>::iterator it = dbvec.begin(); it != dbvec.end(); ++it) {
				if(it->word != "_EDIT_TOTALS") cout << it->word << " " << it->score << " " << it->good_edits << " " << it->bad_edits << "\n";
			}
		}
		
		float getWordVandalProb(unsigned int good_count, unsigned int bad_count, bool corrected_probability = false) {
			unsigned int total_good, total_bad;
			float default_return = -1.0;
			if(have_cached_total) {
				total_good = cached_total_good;
				total_bad = cached_total_bad;
			} else {
				getTotals(total_good, total_bad);
			}
			unsigned int good = good_count, bad = bad_count;
			if(good == 0 && bad == 0) return default_return;
			float fgood = (float)good, fbad = (float)bad, ftgood = (float)total_good, ftbad = (float)total_bad;
			float good_prob = fgood / ftgood;
			float bad_prob = fbad / ftbad;
			float vand_prob = bad_prob / (bad_prob + good_prob);
			if(corrected_probability) {
				float bg_strength = 4.0;
				float word_edits = fgood + fbad;
				vand_prob = (bg_strength * 0.5 + word_edits * vand_prob) / (bg_strength + word_edits);
			}
			return vand_prob;
		}
		
		// Returns <0 if not found
		float getWordVandalProb(const std::string & word, bool corrected_probability = false) {
			unsigned int good, bad;
			getWord(word, good, bad);
			return getWordVandalProb(good, bad, corrected_probability);
		}
	private:
		Db db;
		bool db_opened;
		unsigned int cached_total_good;
		unsigned int cached_total_bad;
		bool have_cached_total;
		
		struct dbvecinf {
			float score;
			std::string word;
			int good_edits;
			int bad_edits;
			bool operator<(const dbvecinf & comp) const {
				if(score < comp.score) return true;
				if(score > comp.score) return false;
				if(word < comp.word) return true;
				if(word > comp.word) return false;
				return false;
			}
		};
};


}

#endif
