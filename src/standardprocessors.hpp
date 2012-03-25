#ifndef _STANDARDPROCESSORS_HPP
#define _STANDARDPROCESSORS_HPP

#include "framework.hpp"
#include <string>
#include <iostream>
#include <string.h>
#include <fstream>
#include <sys/types.h>
#include <regex.h>
#include <matheval.h>
#include <map>
#include <math.h>
#include <iconv.h>
#include <errno.h>
#include <boost/thread.hpp>
#include "faststringops.hpp"

#define is_lcase(c) ((c) >= 'a' && (c) <= 'z')
#define is_ucase(c) ((c) >= 'A' && (c) <= 'Z')
#define is_digit(c) ((c) >= '0' && (c) <= '9')
inline bool is_vowel(char c) {
	if(is_ucase(c)) c += ('a' - 'A');
	return (c == 'a' || c == 'e' || c == 'i' || c == 'o' || c == 'u' || c == 'y');
}

namespace WPCluebot {

class TrialRunReport : public EditProcessor {
	public:
		TrialRunReport(libconfig::Setting & cfg) : EditProcessor(cfg) {
			prop_isvand = (const char *)configuration["isvandprop"];
			prop_score = (const char *)configuration["score"];
			if(configuration.exists("id")) prop_id = (const char *)configuration["id"];
		}
		
		void process(Edit & ed) {
			boost::lock_guard<boost::mutex> lock(mut);
			editinfo einfo;
			einfo.is_vandalism = ed.getProp<bool>(prop_isvand);
			einfo.score = ed.getProp<float>(prop_score);
			if(prop_id.size()) einfo.id = ed.getProp<std::string>(prop_id);
			edits.push_back(einfo);
		}
		
		void finished() {
			if(configuration.exists("threshold_table")) {
				std::string ttfn = (const char *)configuration["threshold_table"];
				float ival = 0.01;
				if(configuration.exists("threshold_table_interval")) ival = configuration["threshold_table_interval"];
				makeThresholdTable(ttfn, ival);
			}
			if(configuration.exists("report")) {
				std::string rfn = (const char *)configuration["report"];
				writeMainReport(rfn);
				if(configuration.exists("misclassified_file_prefix")) {
					std::string pfx = (const char *)configuration["misclassified_file_prefix"];
					writeMisclassifiedLists(primary_threshold, pfx + "falsepositives.txt", pfx + "falsenegatives.txt");
				}
			}
		}
		
	private:
		std::string prop_isvand;
		std::string prop_score;
		std::string prop_id;
		
		float primary_threshold;
	
		boost::mutex mut;
	
		struct editinfo {
			bool is_vandalism;
			float score;
			std::string id;
		};
		
		std::vector<editinfo> edits;
		
		void writeMisclassifiedLists(float thresh, const std::string & false_pos_fname, const std::string & false_neg_fname) {
			std::ofstream fpfile(false_pos_fname.c_str()), fnfile(false_neg_fname.c_str());
			fpfile.setf(std::ofstream::fixed);
			fnfile.setf(std::ofstream::fixed);
			fpfile.precision(4);
			fnfile.precision(4);
			fpfile << "# False positives based on threshold " << thresh << "\n";
			fnfile << "# False negatives based on threshold " << thresh << "\n";
			for(std::vector<editinfo>::iterator it = edits.begin(); it != edits.end(); ++it) {
				bool think_vandalism = (it->score >= thresh);
				if(think_vandalism && !it->is_vandalism) {
					fpfile << it->score << " " << it->id << "\n";
				}
				if(!think_vandalism && it->is_vandalism) {
					fnfile << it->score << " " << it->id << "\n";
				}
			}
		}
		
		void writeMainReport(const std::string & filename) {
			std::ofstream file(filename.c_str());
			file.setf(std::ofstream::fixed);
			file.precision(4);
			
			file << "=== REPORT ===\n";
			writeConstStats(file);
			primary_threshold = 0.5;
			if(configuration.exists("threshold")) {
				float thresh = configuration["threshold"];
				primary_threshold = thresh;
				writeThresholdStats(file, thresh);
			}
			if(configuration.exists("false_positive_rate")) {
				float fprate = configuration["false_positive_rate"];
				float thresh = calcThresholdFromFalsePositiveRate(fprate, 0.0001);
				file << "With maximum false positive rate " << (fprate * 100.0) << "%, Threshold=" << thresh << "\n";
				primary_threshold = thresh;
				writeThresholdStats(file, thresh);
			}
		}
		
		void writeThresholdStats(std::ostream & strm, float thresh) {
			strm << "Using vandalism threshold " << thresh << ":\n";
			int act_pos, act_neg;
			getConstStats(act_pos, act_neg);
			int tot_pos, tot_neg, fal_pos, fal_neg;
			getStatsWithThreshold(thresh, tot_pos, tot_neg, fal_pos, fal_neg);
			strm << "False positives: " << fal_pos << " (" << ((float)fal_pos / (float)act_neg * 100.0) << "% of legit edits)\n";
			strm << "Correct positives: " << (tot_pos - fal_pos) << " (" << ((float)(tot_pos - fal_pos) / (float)act_pos * 100.0) << "% of vandal edits)\n";
		}
		
		void writeConstStats(std::ostream & strm) {
			int act_pos, act_neg;
			getConstStats(act_pos, act_neg);
			strm << "Trial set: " << (act_pos + act_neg) << " Edits - " << act_pos << " vandalism, " << act_neg << " legit (" << ((float)act_pos / (float)(act_pos + act_neg) * 100.0) << "% vandalism)\n";
		}
		
		float calcThresholdFromFalsePositiveRate(float fprate, float interval = 0.005) {
			int act_pos, act_neg;
			getConstStats(act_pos, act_neg);
			for(float t = 0.0; t <= 1.0; t += interval) {
				int tot_pos, tot_neg, fal_pos, fal_neg;
				getStatsWithThreshold(t, tot_pos, tot_neg, fal_pos, fal_neg);
				float fal_pos_perc = (float)fal_pos / (float)act_neg;
				if(fal_pos_perc <= fprate) return t;
			}
			return 1.0;
		}
		
		void makeThresholdTable(const std::string & filename, float interval) {
			std::ofstream file(filename.c_str());
			file.setf(std::ofstream::fixed);
			file.precision(4);
			file << "# Threshold Correct_Positives False_Positives\n";
			int act_pos, act_neg;
			getConstStats(act_pos, act_neg);
			for(float t = 0.0; t <= 1.0; t += interval) {
				int tot_pos, tot_neg, fal_pos, fal_neg;
				getStatsWithThreshold(t, tot_pos, tot_neg, fal_pos, fal_neg);
				float cor_pos_perc = (float)(tot_pos - fal_pos) / (float)act_pos;
				float fal_pos_perc = (float)fal_pos / (float)act_neg;
				file << t << " " << cor_pos_perc << " " << fal_pos_perc << "\n";
			}
		}
		
		void getStatsWithThreshold(float threshold, int & total_positives, int & total_negatives, int & false_positives, int & false_negatives) {
			false_positives = 0;
			false_negatives = 0;
			total_positives = 0;
			total_negatives = 0;
			for(std::vector<editinfo>::iterator it = edits.begin(); it != edits.end(); ++it) {
				bool think_vandalism = (it->score >= threshold);
				if(think_vandalism && !it->is_vandalism) false_positives++;
				if(!think_vandalism && it->is_vandalism) false_negatives++;
				if(think_vandalism) total_positives++; else total_negatives++;
			}
		}
		
		void getConstStats(int & actual_positives, int & actual_negatives) {
			actual_positives = 0;
			actual_negatives = 0;
			for(std::vector<editinfo>::iterator it = edits.begin(); it != edits.end(); ++it) {
				if(it->is_vandalism) actual_positives++; else actual_negatives++;
			}
		}
};

class EditDump : public EditProcessor {
	public:
		EditDump(libconfig::Setting & cfg) : EditProcessor(cfg) {
			std::string dfilename = configuration["filename"];
			dumpstream.exceptions(std::ofstream::badbit | std::ofstream::failbit);
			dumpstream.open(dfilename.c_str());
			dumpstream << "<WPEditSet>\n";
			
			maxfieldlen = -1;
			if(configuration.exists("maxlen")) maxfieldlen = configuration["maxlen"];
		}
		~EditDump() {
			dumpstream << "</WPEditSet>\n";
		}

		void process(Edit & ed) {
			boost::lock_guard<boost::mutex> lock(mut);
			dumpstream << "<WPEdit>\n";
			ed.dump(dumpstream, maxfieldlen);
			dumpstream << "</WPEdit>\n\n\n";
		}
	private:
		std::ofstream dumpstream;
		int maxfieldlen;
		boost::mutex mut;
};

class WriteProperties : public EditProcessor {
	public:
		WriteProperties(libconfig::Setting & cfg) : EditProcessor(cfg) {
			std::string dfilename = configuration["filename"];
			dumpstream.exceptions(std::ofstream::badbit | std::ofstream::failbit);
			dumpstream.open(dfilename.c_str());
		}

		void process(Edit & ed) {
			boost::lock_guard<boost::mutex> lock(mut);
			libconfig::Setting & proplist = configuration["properties"];
			for(int i = 0; i < proplist.getLength(); ++i) {
				if(i) dumpstream << ", ";
				dumpstream << (const char *)proplist[i] << "=" << ed.propToString(ed.properties[(const char *)proplist[i]]);
			}
			dumpstream << "\n";
			dumpstream.flush();
		}
	private:
		std::ofstream dumpstream;
		boost::mutex mut;
};

class ProgressPrinter : public EditProcessor {
	public:
		ProgressPrinter(libconfig::Setting & cfg) : EditProcessor(cfg) {
			interval = 3;
			if(configuration.exists("interval")) interval = configuration["interval"];
			count = 0;
			last_percent = 0.0;
			printing_progress = false;
		}
		void process(Edit & ed) {
			boost::lock_guard<boost::mutex> lock(mut);
			if((count++) % interval != 0) return;
			if(ed.hasProp("input_xml_file_pos") && ed.hasProp("input_xml_file_size")) {
				unsigned long long int siz = ed.getProp<unsigned long long int>("input_xml_file_size");
				unsigned long long int pos = ed.getProp<unsigned long long int>("input_xml_file_pos");
				float percent = (float)pos / (float)siz;
				if(percent < last_percent) percent = last_percent;
				last_percent = percent;
				printProgressBar(count, percent);
			} else {
				printSimpleCount(count);
			}
		}
		void finished() {
			if(printing_progress) {
				printProgressBar(count, 1.0);
			} else {
				printSimpleCount(count);
			}
			std::cout << "\n";
		}
	private:
		int interval;
		int count;
		bool printing_progress;
		boost::mutex mut;
		
		float last_percent;
		
		void printSimpleCount(int c) {
			std::cout << "\x1B[20D" << c;
			std::cout.flush();
		}
		
		void printProgressBar(int c, float perc) {
			printing_progress = true;
			int bar_size = 60;
			std::cout << "\x1B[" << (bar_size + 40) << "D";
			int bar_used = (int)((float)(bar_size - 2) * perc);
			std::cout << "[";
			std::string barustr(bar_used, '=');
			std::cout << barustr;
			int bar_space = bar_size - 2 - bar_used;
			if(bar_used != bar_size - 2) {
				std::cout << ">";
				bar_space--;
			}
			if(bar_space > 0) {
				std::string barspcstr(bar_space, ' ');
				std::cout << barspcstr;
			}
			std::cout.setf(std::ios::fixed);
			std::cout.precision(1);
			std::cout << "]   " << (perc*100.0) << "%   " << c;
			std::cout.flush();
		}
};

class CharacterCounter : public TextProcessor {
	public:
		CharacterCounter(libconfig::Setting & cfg) : TextProcessor(cfg) {}
		
		void processText(Edit & ed, const std::string & text, const std::string & proppfx) {
			int counts[256];
			memset(counts, 0, sizeof(counts));
			const char * str = text.c_str();
			for(; *str; ++str) counts[*str]++;
			libconfig::Setting & metrics = configuration["metrics"];
			for(int i = 0; i < metrics.getLength(); ++i) {
				const char * metricname = metrics[i].getName();
				const char * metricchars = metrics[i];
				int cnt = 0;
				for(; *metricchars; ++metricchars) cnt += counts[*metricchars];
				ed.setProp<int>(proppfx + metricname, cnt);
			}
		}
};

class MiscTextMetrics : public TextProcessor {
	public:
		MiscTextMetrics(libconfig::Setting & cfg) : TextProcessor(cfg) {}
		
		void processText(Edit & ed, const std::string & text, const std::string & proppfx) {
			const char * str = text.c_str();
			
			// Longest run
			{
				int lrun = 0;
				int crun = 0;
				if(*str) {
					crun = 1;
					for(const char * c = str + 1; *c; ++c) {
						if(*c == *(c - 1)) ++crun; else {
							if(crun > lrun) lrun = crun;
							crun = 1;
						}
					}
					if(crun > lrun) lrun = crun;
				}
				ed.setProp<int>(proppfx + "longest_run", lrun);
			}
			
			
		}
};

class FastStringSearch : public TextProcessor {
	public:
		FastStringSearch(libconfig::Setting & cfg) : TextProcessor(cfg) {
			libconfig::Setting & metrics = configuration["metrics"];
			for(int i = 0; i < metrics.getLength(); ++i) {
				std::string metricname = (const char *)metrics[i].getName();
				libconfig::Setting & searches = metrics[i];
				for(int j = 0; j < searches.getLength(); ++j) {
					std::string searchtext = (const char *)searches[j];
					strfinder.addCategorySearch(searchtext, metricname);
				}
			}
		}
		
		void processText(Edit & ed, const std::string & text, const std::string & proppfx) {
			boost::lock_guard<boost::mutex> lock(mut);
			strfinder.processString(text);
			std::map<std::string,unsigned int> cnts = strfinder.getCategoryCounts();
			for(std::map<std::string,unsigned int>::iterator it = cnts.begin(); it != cnts.end(); ++it) {
				std::string propname = proppfx + it->first;
				int c = it->second;
				ed.setProp<int>(propname, c);
			}
		}
		
	private:
		boost::mutex mut;
		MultiStrFind<256,0> strfinder;
};

class StandardQuoteSeparator : public EditProcessor {
	public:
		StandardQuoteSeparator(libconfig::Setting & cfg) : EditProcessor(cfg) {
			inprop = (const char *)configuration["input"];
			if(configuration.exists("output_quotes")) outquoteprop = (const char *)configuration["output_quotes"];
			if(configuration.exists("output_noquotes")) outnoquoteprop = (const char *)configuration["output_noquotes"];
		}
		
		void process(Edit & ed) {
			std::string origstr = ed.getProp<std::string>(inprop);
			const char * orig = origstr.c_str();
			char * withquotes = new char[origstr.size() + 1];
			char * withoutquotes = new char[origstr.size() + 1];
			char * withquotes_pos = withquotes;
			char * withoutquotes_pos = withoutquotes;
			bool quoted = false;
			for(; *orig; ++orig) {
				char c = *orig;
				if(c == '"') {
					quoted = (!quoted);
					continue;
				}
				if(c == '\n') {
					if(quoted) *(withquotes_pos++) = c;
					quoted = false;
				}
				if(quoted) {
					*(withquotes_pos++) = c;
				} else {
					*(withoutquotes_pos++) = c;
				}
			}
			*withquotes_pos = 0;
			*withoutquotes_pos = 0;
			std::string withquotes_str(withquotes);
			std::string withoutquotes_str(withoutquotes);
			delete[] withquotes;
			delete[] withoutquotes;
			if(outquoteprop.size()) ed.setProp<std::string>(outquoteprop, withquotes_str);
			if(outnoquoteprop.size()) ed.setProp<std::string>(outnoquoteprop, withoutquotes_str);
		}
		
	private:
		std::string inprop;
		std::string outquoteprop;
		std::string outnoquoteprop;
};

class CharsetConverter : public TextProcessor {
	public:
		CharsetConverter(libconfig::Setting & cfg) : TextProcessor(cfg) {
			const char * from = configuration["from"];
			const char * to = "ASCII";
			icnv = iconv_open(to, from);
			int one = 1;
			iconvctl(icnv, ICONV_SET_DISCARD_ILSEQ, &one);
		}
		~CharsetConverter() {
			iconv_close(icnv);
		}
		
		void processText(Edit & ed, const std::string & text, const std::string & proppfx) {
			boost::lock_guard<boost::mutex> lock(mut);
			std::vector<char> intextvec(text.begin(), text.end());
			char * intextchars = &intextvec.front();
			size_t inbytes = intextvec.size();
			char * outtextchars = new char[inbytes];
			char * origouttextchars = outtextchars;
			size_t outbytes = inbytes;
			size_t ret = iconv(icnv, &intextchars, &inbytes, &outtextchars, &outbytes);
			if(ret == (size_t)-1) {
				delete[] origouttextchars;
				throw std::runtime_error("Character set conversion error");
			}
			std::string outstring(origouttextchars, intextvec.size() - outbytes);
			ed.setProp<std::string>(proppfx, outstring);
			delete[] origouttextchars;
		}
		
	private:
		iconv_t icnv;
		boost::mutex mut;
};

class AllPropCharsetConverter : public EditProcessor {
	public:
		AllPropCharsetConverter(libconfig::Setting & cfg) : EditProcessor(cfg) {
			const char * from = configuration["from"];
			const char * to = "ASCII";
			icnv = iconv_open(to, from);
			int one = 1;
			iconvctl(icnv, ICONV_SET_DISCARD_ILSEQ, &one);
		}
		~AllPropCharsetConverter() {
			iconv_close(icnv);
		}
		
		void process(Edit & ed) {
			boost::lock_guard<boost::mutex> lock(mut);
			for(std::map<std::string,boost::any>::iterator it = ed.properties.begin(); it != ed.properties.end(); ++it) {
				if(it->second.type() != typeid(std::string)) continue;
				std::string text = boost::any_cast<std::string>(it->second);
				if(text.size() == 0) continue;
				std::vector<char> intextvec(text.begin(), text.end());
				char * intextchars = &intextvec.front();
				size_t inbytes = intextvec.size();
				char * outtextchars = new char[inbytes];
				char * origouttextchars = outtextchars;
				size_t outbytes = inbytes;
				size_t ret = iconv(icnv, &intextchars, &inbytes, &outtextchars, &outbytes);
				if(ret == (size_t)-1) {
					delete[] origouttextchars;
					if(errno == E2BIG) throw std::runtime_error("Character set conversion error: Too big"); else
					if(errno == EILSEQ) throw std::runtime_error("Character set conversion error: Invalid sequence"); else
					if(errno == EINVAL) throw std::runtime_error("Character set conversion error: Incomplete sequence"); else
					throw std::runtime_error("Character set conversion error: Unknown");
				}
				std::string outstring(origouttextchars, intextvec.size() - outbytes);
				ed.setProp<std::string>(it->first, outstring);
				delete[] origouttextchars;
			}
		}
		
	private:
		iconv_t icnv;
		boost::mutex mut;
};

class WordSeparator : public TextProcessor {
	public:
		WordSeparator(libconfig::Setting & cfg) : TextProcessor(cfg) {
			for(int i = 0; i < 256; ++i) {
				validchars[i] = false;
				ignorechars[i] = false;
			}
			const char * wchars = configuration["valid_word_chars"];
			for(; *wchars; ++wchars) validchars[*wchars] = true;
			if(configuration.exists("ignore_chars")) {
				const char * ichars = configuration["ignore_chars"];
				for(; *ichars; ++ichars) ignorechars[*ichars] = true;
			}
		}
		
		void processText(Edit & ed, const std::string & text, const std::string & proppfx) {
			WordSet cset;
			const char * word_start = NULL;
			const char * pos = text.c_str();
			for(; *pos; ++pos) {
				char c = *pos;
				if(ignorechars[c]) continue;
				if(validchars[c]) {
					if(!word_start) word_start = pos;
				} else {
					if(word_start) {
						std::string word(word_start, pos - word_start);
						cset[word]++;
						word_start = NULL;
					}
				}
			}
			if(word_start) if(*word_start) {
				std::string word(word_start);
				cset[word]++;
			}
			ed.setProp<WordSet>(proppfx, cset);
		}
		
	private:
		bool validchars[256];
		bool ignorechars[256];
};

class MultiWordSeparator : public TextProcessor {
	public:
		MultiWordSeparator(libconfig::Setting & cfg) : TextProcessor(cfg) {
			wordseries = configuration["num_words_together"];
			standardseparator = ' ';
			if(configuration.exists("standard_separator")) {
				std::string sepstr = (const char *)configuration["standard_separator"];
				if(sepstr.size()) standardseparator = sepstr[0];
			}
			for(int i = 0; i < 256; ++i) {
				validchars[i] = false;
				ignorechars[i] = false;
			}
			const char * wchars = configuration["valid_word_chars"];
			for(; *wchars; ++wchars) validchars[*wchars] = true;
			if(configuration.exists("ignore_chars")) {
				const char * ichars = configuration["ignore_chars"];
				for(; *ichars; ++ichars) ignorechars[*ichars] = true;
			}
		}
		
		void processText(Edit & ed, const std::string & text, const std::string & proppfx) {
			std::vector<std::string> wordlist;
			wordlist.reserve(text.size() / 4);
			const char * word_start = NULL;
			const char * pos = text.c_str();
			for(; *pos; ++pos) {
				char c = *pos;
				if(ignorechars[c]) continue;
				if(validchars[c]) {
					if(!word_start) word_start = pos;
				} else {
					if(word_start) {
						std::string word(word_start, pos - word_start);
						wordlist.push_back(word);
						word_start = NULL;
					}
				}
			}
			if(word_start) if(*word_start) {
				std::string word(word_start);
				wordlist.push_back(word);
			}
			
			WordSet set;
			if(wordlist.size() >= wordseries) {
				for(std::vector<std::string>::iterator it = wordlist.begin() + wordseries - 1; it != wordlist.end(); ++it) {
					std::string cstr;
					for(int i = wordseries - 1; i >= 0; --i) {
						cstr += *(it - i);
						if(i) cstr += standardseparator;
					}
					set[cstr]++;
				}
			}
			ed.setProp<WordSet>(proppfx, set);
		}
		
	private:
		bool validchars[256];
		bool ignorechars[256];
		int wordseries;
		char standardseparator;
};

class CharacterReplace : public TextProcessor {
	public:
		CharacterReplace(libconfig::Setting & cfg) : TextProcessor(cfg) {
			for(int i = 0; i < 256; ++i) {
				charreplacements[i] = i;
				removechars[i] = false;
				removemultiplechars[i] = false;
				keepchars[i] = true;
			}
			if(configuration.exists("find") && configuration.exists("replace")) {
				const char * findchars = configuration["find"];
				const char * replacechars = configuration["replace"];
				while(*findchars && *replacechars) {
					charreplacements[*findchars] = *replacechars;
					findchars++;
					replacechars++;
				}
			}
			if(configuration.exists("remove")) {
				const char * c = configuration["remove"];
				for(; *c; ++c) removechars[*c] = true;
			}
			if(configuration.exists("removemulti")) {
				const char * c = configuration["removemulti"];
				for(; *c; ++c) removemultiplechars[*c] = true;
			}
			if(configuration.exists("keep")) {
				const char * c = configuration["keep"];
				for(int i = 0; i < 256; ++i) keepchars[i] = false;
				for(; *c; ++c) keepchars[*c] = true;
			}
		}
		
		void processText(Edit & ed, const std::string & text, const std::string & proppfx) {
			char * res = new char[text.size()];
			char * respos = res;
			const char * src = text.c_str();
			bool first = true;
			for(; *src; ++src) {
				char c = charreplacements[*src];
				if(removechars[c]) continue;
				if(removemultiplechars[c] && !first) if(*(respos - 1) == c) continue;
				if(!keepchars[c]) continue;
				*(respos++) = c;
				first = false;
			}
			std::string pstr(res, respos - res);
			delete[] res;
			ed.setProp<std::string>(proppfx, pstr);
		}
		
	private:
		char charreplacements[256];
		bool removechars[256];
		bool removemultiplechars[256];
		bool keepchars[256];
};

class PosixRegexSearch : public TextProcessor {
	public:
		PosixRegexSearch(libconfig::Setting & cfg) : TextProcessor(cfg) {
			libconfig::Setting & metrics = configuration["metrics"];
			for(int i = 0; i < metrics.getLength(); ++i) {
				std::string metricname = (const char *)metrics[i].getName();
				libconfig::Setting & searches = metrics[i];
				for(int j = 0; j < searches.getLength(); ++j) {
					int flags = 0;
					if(searches[j].exists("flags")) {
						std::string flagstr = (const char *)searches[j]["flags"];
						for(std::string::iterator flagit = flagstr.begin(); flagit != flagstr.end(); ++flagit) {
							switch(*flagit) {
								case 'E': flags |= REG_EXTENDED; break;
								case 'I': flags |= REG_ICASE; break;
								case 'N': flags |= REG_NEWLINE; break;
								default: throw std::runtime_error("Unknown regex flag");
							}
						}
					}
					if(!searches[j].exists("regex")) throw std::runtime_error("No regex given");
					std::string regexstr = (const char *)searches[j]["regex"];
					regexes.push_back(boost::shared_ptr<PosixRegex>(new PosixRegex(regexstr, flags, metricname)));
					regexes.back()->metric = metricname;
				}
			}
		}
		
		void processText(Edit & ed, const std::string & text, const std::string & proppfx) {
			std::map<std::string,int> cnts;
			for(std::vector<boost::shared_ptr<PosixRegex> >::iterator it = regexes.begin(); it != regexes.end(); ++it) {
				int n = (*it)->countMatches(text);
				cnts[(*it)->metric] += n;
			}
			for(std::map<std::string,int>::iterator it = cnts.begin(); it != cnts.end(); ++it) {
				ed.setProp<int>(proppfx + it->first, it->second);
			}
		}
		
	private:
		struct PosixRegex {
			regex_t preg;
			std::string metric;
			boost::mutex mut;
			
			PosixRegex(const std::string & regexstr, int flags, const std::string & name) {
				int r = regcomp(&preg, regexstr.c_str(), flags);
				if(r != 0) {
					std::string exstr("POSIX Regex Compile Error on Regex ");
					exstr += name + ": ";
					switch(r) {
						case REG_BADBR: exstr += "Invalid use of back reference operator."; break;
						case REG_BADPAT: exstr += "Invalid use of pattern operators."; break;
						case REG_BADRPT: exstr += "Invalid use of repetition operators."; break;
						case REG_EBRACE: exstr += "Un-matched braces."; break;
						case REG_EBRACK: exstr += "Un-matched brackets."; break;
						case REG_ECOLLATE: exstr += "Invalid collating element."; break;
						case REG_ECTYPE: exstr += "Unknown character class."; break;
						case REG_EESCAPE: exstr += "Trailing backslash."; break;
						case REG_EPAREN: exstr += "Un-matched parens."; break;
						case REG_ERANGE: exstr += "Invalid use of range operator."; break;
						case REG_ESUBREG: exstr += "Invalid back reference."; break;
						default: exstr += "Unknown error."; break;
					}
					throw std::runtime_error(exstr.c_str());
				}
			}
			~PosixRegex() {
				regfree(&preg);
			}
			
			int countMatches(const std::string & str) {
				boost::lock_guard<boost::mutex> lock(mut);
				regmatch_t matches[10];
				const char * pos = str.c_str();
				int c = 0;
				for(;;) {
					int r = regexec(&preg, pos, 10, matches, 0);
					if(r != 0) break;
					++c;
					if(matches[0].rm_so == -1) break;
					pos += matches[0].rm_eo;
				}
				return c;
			}
		};
		
		std::vector<boost::shared_ptr<PosixRegex> > regexes;
};


class PosixRegexReplace : public TextProcessor {
	public:
		PosixRegexReplace(libconfig::Setting & cfg) : TextProcessor(cfg) {
			libconfig::Setting & replacements = configuration["replacements"];
			for(int i = 0; i < replacements.getLength(); ++i) {
				int flags = 0;
				if(replacements[i].exists("flags")) {
					std::string flagstr = (const char *)replacements[i]["flags"];
					for(std::string::iterator flagit = flagstr.begin(); flagit != flagstr.end(); ++flagit) {
						switch(*flagit) {
							case 'E': flags |= REG_EXTENDED; break;
							case 'I': flags |= REG_ICASE; break;
							case 'N': flags |= REG_NEWLINE; break;
							default: throw std::runtime_error("Unknown regex flag");
						}
					}
				}
				if(!replacements[i].exists("regex")) throw std::runtime_error("No regex given");
				if(!replacements[i].exists("replace")) throw std::runtime_error("No replacement given");
				std::string regexstr = (const char *)replacements[i]["regex"];
				std::string replacestr = (const char *)replacements[i]["replace"];
				regexes.push_back(boost::shared_ptr<PosixRegex>(new PosixRegex(regexstr, flags, "replacement")));
				regexes.back()->replacement = replacestr;
			}
		}
		
		void processText(Edit & ed, const std::string & text, const std::string & proppfx) {
			std::string res = text;
			for(std::vector<boost::shared_ptr<PosixRegex> >::iterator it = regexes.begin(); it != regexes.end(); ++it) {
				res = (*it)->replaceStr(res);
			}
			ed.setProp(proppfx, res);
		}
		
	private:
		struct PosixRegex {
			regex_t preg;
			std::string replacement;
			boost::mutex mut;
			
			PosixRegex(const std::string & regexstr, int flags, const std::string & name) {
				int r = regcomp(&preg, regexstr.c_str(), flags);
				if(r != 0) {
					std::string exstr("POSIX Regex Compile Error on Regex ");
					exstr += name + ": ";
					switch(r) {
						case REG_BADBR: exstr += "Invalid use of back reference operator."; break;
						case REG_BADPAT: exstr += "Invalid use of pattern operators."; break;
						case REG_BADRPT: exstr += "Invalid use of repetition operators."; break;
						case REG_EBRACE: exstr += "Un-matched braces."; break;
						case REG_EBRACK: exstr += "Un-matched brackets."; break;
						case REG_ECOLLATE: exstr += "Invalid collating element."; break;
						case REG_ECTYPE: exstr += "Unknown character class."; break;
						case REG_EESCAPE: exstr += "Trailing backslash."; break;
						case REG_EPAREN: exstr += "Un-matched parens."; break;
						case REG_ERANGE: exstr += "Invalid use of range operator."; break;
						case REG_ESUBREG: exstr += "Invalid back reference."; break;
						default: exstr += "Unknown error."; break;
					}
					throw std::runtime_error(exstr.c_str());
				}
			}
			~PosixRegex() {
				regfree(&preg);
			}
			
			std::string replaceStr(const std::string & str) {
				boost::lock_guard<boost::mutex> lock(mut);
				regmatch_t matches[10];
				const char * pos = str.c_str();
				std::string res;
				for(;;) {
					int r = regexec(&preg, pos, 10, matches, 0);
					if(r != 0) break;
					if(matches[0].rm_so == -1) break;
					res.append(pos, matches[0].rm_so);
					pos += matches[0].rm_eo;
				}
				res.append(pos);
				return res;
			}
		};
		
		std::vector<boost::shared_ptr<PosixRegex> > regexes;
};

class WordSetCompare : public EditProcessor {
	public:
		WordSetCompare(libconfig::Setting & cfg) : EditProcessor(cfg) {
			firstsetprop = (const char *)configuration["firstset"];
			secondsetprop = (const char *)configuration["secondset"];
			if(configuration.exists("num_common_words")) numcommonprop = (const char *)configuration["num_common_words"];
		}
		
		void process(Edit & ed) {
			WordSet set1 = ed.getProp<WordSet>(firstsetprop);
			WordSet set2 = ed.getProp<WordSet>(secondsetprop);
			if(numcommonprop.size()) {
				WordSet * small;
				WordSet * big;
				if(set1.size() > set2.size()) {
					small = &set2;
					big = &set1;
				} else {
					small = &set1;
					big = &set2;
				}
				int n = 0;
				for(WordSet::iterator it = small->begin(); it != small->end(); ++it) {
					if(big->count(it->first)) {
						n += set2[it->first];
					}
				}
				ed.setProp<int>(numcommonprop, n);
			}
		}
	
	private:
		std::string firstsetprop;
		std::string secondsetprop;
		std::string numcommonprop;
};

class WordSetDiff : public EditProcessor {
	public:
		WordSetDiff(libconfig::Setting & cfg) : EditProcessor(cfg) {
			in_prev_prop = (const char *)configuration["previous"];
			in_cur_prop = (const char *)configuration["current"];
			if(configuration.exists("added")) out_add_prop = (const char *)configuration["added"];
			if(configuration.exists("removed")) out_del_prop = (const char *)configuration["removed"];
			if(configuration.exists("delta")) out_delta_prop = (const char *)configuration["delta"];
		}
		
		void process(Edit & ed) {
			WordSet prev = ed.getProp<WordSet>(in_prev_prop);
			WordSet delta = ed.getProp<WordSet>(in_cur_prop);
			
			for(WordSet::iterator it = prev.begin(); it != prev.end(); ++it) delta[it->first] -= it->second;
			if(out_delta_prop.size()) ed.setProp<WordSet>(out_delta_prop, delta);
			
			if(out_add_prop.size() || out_del_prop.size()) {
				WordSet added, removed;
				for(WordSet::iterator it = delta.begin(); it != delta.end(); ++it) {
					if(it->second > 0 && out_add_prop.size()) added[it->first] = it->second;
					if(it->second < 0 && out_del_prop.size()) removed[it->first] = 0 - it->second;
				}
				if(out_add_prop.size()) ed.setProp<WordSet>(out_add_prop, added);
				if(out_del_prop.size()) ed.setProp<WordSet>(out_del_prop, removed);
			}
		}
	
	private:
		std::string in_prev_prop;
		std::string in_cur_prop;
		std::string out_add_prop;
		std::string out_del_prop;
		std::string out_delta_prop;
};

class MiscRawWordMetrics : public WordSetProcessor {
	public:
		MiscRawWordMetrics(libconfig::Setting & cfg) : WordSetProcessor(cfg) {}
		
		void processWordSet(Edit & ed, const WordSet & wordset, const std::string & proppfx) {
			int total_num_words = 0;					// Total number of words in the set
			int total_distinct_words = wordset.size();	// Number of distinct words in the set
			int num_lcase_words = 0;					// Number of words that are entirely lowercase
			int num_ucase_words = 0;					// Number of words that are entirely capitalized
			int num_firstucase_words = 0;				// Number of words with only the first letter capitalized
			int num_middleucase_words = 0;				// Number of words with caps in the middle, but not completely caps
			int num_alldigits_words = 0;				// Number of words that are entirely numeric
			int num_partdigits_words = 0;				// Number of words that are partially digits and partially alpha
			int num_novowels_words = 0;					// Number of words that contain no vowels
			int max_word_len = 0;						// Length of the longest word
			int max_word_repeats = 0;					// Maximum number of times a single word was used
			int max_char_repeat = 0;					// Maximum number of consecutive single character repeats
			int max_ucase_word_len = 0;
			
			for(WordSet::const_iterator it = wordset.begin(); it != wordset.end(); ++it) {
				int word_occurrences = it->second;
				const char * word = it->first.c_str();
				int word_len = it->first.size();
				if(!*word || !word_len) continue;
				
				bool has_lcase_char = false;
				bool has_ucase_char = false;
				bool has_first_lcase_char = false;
				bool has_first_ucase_char = false;
				bool has_digit_char = false;
				bool has_vowel_char = false;
				int num_lcase_chars = 0;
				int num_ucase_chars = 0;
				int num_digits = 0;
				
				int cur_char_repeat = 1;
				int cur_max_char_repeat = 1;
				
				if(is_lcase(*word)) has_first_lcase_char = true;
				if(is_ucase(*word)) has_first_ucase_char = true;
				
				bool lfc = true;
				for(; *word; ++word) {
					char c = *word;
					if(is_lcase(c)) { has_lcase_char = true; num_lcase_chars++; }
					if(is_ucase(c)) { has_ucase_char = true; num_ucase_chars++; }
					if(is_digit(c)) { has_digit_char = true; num_digits++; }
					if(is_vowel(c)) has_vowel_char++;
					if(!lfc) {
						if(c == *(word - 1)) {
							cur_char_repeat++;
							if(cur_char_repeat > cur_max_char_repeat) cur_max_char_repeat = cur_char_repeat;
						} else cur_char_repeat = 1;
					}
					lfc = false;
				}
				
				total_num_words += word_occurrences;
				if(has_lcase_char && !has_ucase_char) num_lcase_words += word_occurrences;
				if(num_ucase_chars == word_len && word_len > 1) {
					num_ucase_words += word_occurrences;
					if(word_len > max_ucase_word_len) max_ucase_word_len = word_len;
				}
				if(has_first_ucase_char && num_ucase_chars != word_len && word_len > 1) num_firstucase_words += word_occurrences;
				if(!has_first_ucase_char && has_ucase_char) num_middleucase_words += word_occurrences;
				if(has_digit_char && !has_lcase_char && !has_ucase_char) num_alldigits_words += word_occurrences; else
				if(has_digit_char) num_partdigits_words += word_occurrences;
				if((has_ucase_char || has_lcase_char) && !has_vowel_char) num_novowels_words += word_occurrences;
				
				if(word_len > max_word_len) max_word_len = word_len;
				if(word_occurrences > max_word_repeats) max_word_repeats = word_occurrences;
				if(cur_max_char_repeat > max_char_repeat) max_char_repeat = cur_max_char_repeat;
			}
			
			ed.setProp<int>(proppfx + "word_count", total_num_words);
			ed.setProp<int>(proppfx + "distinct_word_count", total_distinct_words);
			ed.setProp<int>(proppfx + "all_lcase_word_count", num_lcase_words);
			ed.setProp<int>(proppfx + "all_ucase_word_count", num_ucase_words);
			ed.setProp<int>(proppfx + "max_all_ucase_word_len", max_ucase_word_len);
			ed.setProp<int>(proppfx + "first_ucase_word_count", num_firstucase_words);
			ed.setProp<int>(proppfx + "middle_ucase_word_count", num_middleucase_words);
			ed.setProp<int>(proppfx + "numeric_word_count", num_alldigits_words);
			ed.setProp<int>(proppfx + "part_numeric_word_count", num_partdigits_words);
			ed.setProp<int>(proppfx + "novowels_word_count", num_novowels_words);
			ed.setProp<int>(proppfx + "max_word_len", max_word_len);
			ed.setProp<int>(proppfx + "max_word_repeats", max_word_repeats);
			ed.setProp<int>(proppfx + "longest_char_run", max_char_repeat);
		}
};


class WordCharacterReplace : public WordSetProcessor {
	public:
		WordCharacterReplace(libconfig::Setting & cfg) : WordSetProcessor(cfg) {
			for(int i = 0; i < 256; ++i) {
				charreplacements[i] = i;
				removechars[i] = false;
				removemultiplechars[i] = false;
				keepchars[i] = true;
			}
			if(configuration.exists("find") && configuration.exists("replace")) {
				const char * findchars = configuration["find"];
				const char * replacechars = configuration["replace"];
				while(*findchars && *replacechars) {
					charreplacements[*findchars] = *replacechars;
					findchars++;
					replacechars++;
				}
			}
			if(configuration.exists("remove")) {
				const char * c = configuration["remove"];
				for(; *c; ++c) removechars[*c] = true;
			}
			if(configuration.exists("removemulti")) {
				const char * c = configuration["removemulti"];
				for(; *c; ++c) removemultiplechars[*c] = true;
			}
			if(configuration.exists("keep")) {
				const char * c = configuration["keep"];
				for(int i = 0; i < 256; ++i) keepchars[i] = false;
				for(; *c; ++c) keepchars[*c] = true;
			}
		}
		
		void processWordSet(Edit & ed, const WordSet & wordset, const std::string & proppfx) {
			WordSet resset;
			for(WordSet::const_iterator it = wordset.begin(); it != wordset.end(); ++it) {
				char * res = new char[it->first.size()];
				char * respos = res;
				const char * src = it->first.c_str();
				bool first = true;
				for(; *src; ++src) {
					char c = charreplacements[*src];
					if(removechars[c]) continue;
					if(removemultiplechars[c] && !first) if(*(respos - 1) == c) continue;
					if(!keepchars[c]) continue;
					*(respos++) = c;
					first = false;
				}
				std::string pstr(res, respos - res);
				delete[] res;
				if(pstr.size()) resset[pstr] += it->second;
			}
			ed.setProp<WordSet>(proppfx, resset);
		}
		
	private:
		char charreplacements[256];
		bool removechars[256];
		bool removemultiplechars[256];
		bool keepchars[256];
};

class WordFinder : public WordSetProcessor {
	public:
		WordFinder(libconfig::Setting & cfg) : WordSetProcessor(cfg) {
			libconfig::Setting & metrics = configuration["metrics"];
			for(int i = 0; i < metrics.getLength(); ++i) {
				std::string metricname = (const char *)metrics[i].getName();
				libconfig::Setting & mwords = metrics[i];
				for(int j = 0; j < mwords.getLength(); ++j) {
					std::string word = (const char *)mwords[j];
					std::vector<int> * ilist = wordtree.find(word);
					if(ilist) {
						ilist->push_back(metricnames.size());
					} else {
						std::vector<int> nlist;
						nlist.push_back(metricnames.size());
						wordtree.add(word, nlist);
					}
					metricnames.push_back(metricname);
				}
			}
		}
		
		void processWordSet(Edit & ed, const WordSet & wordset, const std::string & proppfx) {
			std::vector<int> cnts(metricnames.size(), 0);
			for(WordSet::const_iterator it = wordset.begin(); it != wordset.end(); ++it) {
				std::vector<int> * cats = wordtree.find(it->first);
				if(cats) {
					for(std::vector<int>::iterator it2 = cats->begin(); it2 != cats->end(); ++it2) {
						cnts[*it2] += it->second;
					}
				}
			}
			for(int i = 0; i < metricnames.size(); ++i) {
				ed.setProp<int>(proppfx + metricnames[i], cnts[i]);
			}
		}
		
	private:
		//StrTree<std::vector<int>, 256, 0> wordtree;
		StrTree<std::vector<int>, 96, 32> wordtree;	// Printable ascii
		std::vector<std::string> metricnames;
};

class ExpressionEval : public EditProcessor {
	public:
		ExpressionEval(libconfig::Setting & cfg) : EditProcessor(cfg) {
			expr_result_type = RESTYPE_FLOAT;
			if(configuration.exists("result_type")) {
				std::string rtstr = (const char *)configuration["result_type"];
				if(rtstr == "int") expr_result_type = RESTYPE_INT; else
				if(rtstr == "float") expr_result_type = RESTYPE_FLOAT; else
				throw std::runtime_error("Unknown result type");
			}
			libconfig::Setting & cexprs = configuration["expressions"];
			for(int i = 0; i < cexprs.getLength(); ++i) {
				std::string exprname = (const char *)cexprs[i].getName();
				std::string exprstr = (const char *)cexprs[i];
				expressions[exprname] = Expression(exprstr);
			}
		}
		
		void process(Edit & ed) {
			std::map<std::string, double> dvarmap = ed.getDoubleMap();
			ed.setProp<std::map<std::string,double> >("expr_dmap_dbg", dvarmap);
			boost::lock_guard<boost::mutex> lock(mut);
			for(std::map<std::string,Expression>::iterator it = expressions.begin(); it != expressions.end(); ++it) {
				double dres = it->second.evaluate(dvarmap);
				std::string pname = it->first;
				switch(expr_result_type) {
					case RESTYPE_INT: ed.setProp<int>(pname, (int)dres); break;
					case RESTYPE_FLOAT: ed.setProp<float>(pname, (float)dres); break;
				}
			}
		}
		
	private:
		boost::mutex mut;
		struct Expression {
			std::string expression_string;
			char **variable_names;
			int num_variables;
			void * evaluator;
			
			void freeMembers() {
				if(evaluator) {
					evaluator_destroy(evaluator);
				}
				evaluator = NULL;
				variable_names = NULL;
				num_variables = 0;
			}
			
			void initMembers(const std::string & expr) {
				expression_string = expr;
				evaluator = evaluator_create(const_cast<char *>(expr.c_str()));
				if(!evaluator) {
					std::string errmsg = std::string("Invalid expression: ") + expr;
					throw std::runtime_error(errmsg.c_str());
				}
				evaluator_get_variables(evaluator, &variable_names, &num_variables);
			}
			
			Expression() : variable_names(NULL), evaluator(NULL), num_variables(0) {}
			Expression(const std::string & expr) : variable_names(NULL), evaluator(NULL), num_variables(0) {
				if(!expr.size()) return;
				initMembers(expr);
			}
			~Expression() {
				freeMembers();
			}
			Expression(const Expression & ex) : variable_names(NULL), evaluator(NULL), num_variables(0) {
				if(!ex.expression_string.size()) return;
				expression_string = ex.expression_string;
				initMembers(expression_string);
			}
			Expression & operator=(const Expression & ex) {
				if(!ex.expression_string.size()) return *this;
				freeMembers();
				expression_string = ex.expression_string;
				initMembers(expression_string);
				return *this;
			}
			
			double evaluate(std::map<std::string, double> & varmap) {
				std::vector<double> varvals(num_variables, 0.0);
				std::string nstr;
				for(int i = 0; i < num_variables; ++i) {
					nstr.assign(variable_names[i]);
					varvals[i] = varmap[nstr];
				}
				return evaluator_evaluate(evaluator, num_variables, variable_names, &varvals.front());
			}
		};
		
		std::map<std::string,Expression> expressions;
		enum { RESTYPE_INT, RESTYPE_FLOAT } expr_result_type;
};

class FloatSetCreator : public EditProcessor {
	public:
		FloatSetCreator(libconfig::Setting & cfg) : EditProcessor(cfg) {
			clip_low = false;
			clip_high = false;
			if(configuration.exists("clip_low")) {
				clip_low = true;
				clip_low_num = configuration["clip_low"];
			}
			if(configuration.exists("clip_high")) {
				clip_high = true;
				clip_high_num = configuration["clip_high"];
			}
		}
		
		void process(Edit & ed) {
			std::map<std::string,double> dmap = ed.getDoubleMap();
			std::vector<float> fset;
			libconfig::Setting & props = configuration["properties"];
			for(int i = 0; i < props.getLength(); ++i) {
				std::string propname = (const char *)props[i];
				if(ed.properties.count(propname)) if(ed.properties[propname].type() == typeid(std::vector<float>)) {
					std::vector<float> subvec = boost::any_cast<std::vector<float> >(ed.properties[propname]);
					for(std::vector<float>::iterator fit = subvec.begin(); fit != subvec.end(); ++fit) fset.push_back(clip(*fit));
					continue;
				}
				if(!dmap.count(propname)) throw std::runtime_error(std::string("No such property while creating float set: ") + propname);
				double d = dmap[propname];
				fset.push_back(clip((float)d));
			}
			std::string outpropname = (const char *)configuration["output"];
			ed.setProp<std::vector<float> >(outpropname, fset);
		}
	
	private:
		bool clip_low;
		float clip_low_num;
		bool clip_high;
		float clip_high_num;
	
		float clip(float n) {
			if(clip_low) {
				if(n < clip_low_num) n = clip_low_num;
				if(isinf(n) < 0) n = clip_low_num;
				if(isnan(n) || isnan(0.0 - n)) n = clip_low_num;
			}
			if(clip_high) {
				if(n > clip_high_num) n = clip_high_num;
				if(isinf(n) > 0) n = clip_high_num;
			}
			return n;
		}
};

class ApplyThreshold : public EditProcessor {
	public:
		ApplyThreshold(libconfig::Setting & cfg) : EditProcessor(cfg) {
			inprop = (const char *)configuration["in"];
			outprop = (const char *)configuration["out"];
			threshold = configuration["threshold"];
		}
		
		void process(Edit & ed) {
			float f = ed.getProp<float>(inprop);
			bool b = (f >= threshold);
			ed.setProp<bool>(outprop, b);
		}
		
	private:
		std::string inprop;
		std::string outprop;
		float threshold;
};



}


#endif
