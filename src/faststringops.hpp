#ifndef _FASTSTRINGOPS_HPP
#define _FASTSTRINGOPS_HPP

#include <string>
#include <stdexcept>
#include <map>
#include <vector>
#include <utility>
#include <stdlib.h>
#include <string.h>
#include <boost/scoped_array.hpp>

namespace WPCluebot {

template <class NodeValueType, int numsubnodes, int nodevaloffset>
class StrTree;

/* This class implements a trie-like structure designed to store words.
 * It associates values with words and allows O(log n) lookups, where n is
 * the length of the word, not the number of words.  This is good for storing
 * a lot of very short words - NOT for long words.
 * It also allows for incremental traversals. */
template <class NodeValueType, int numsubnodes, int nodevaloffset>
class StrTree {
	public:
		StrTree() {
			root = new Node;
		}
		~StrTree() {
			delete root;
		}
		void clear() {
			delete root;
			root = new Node;
		}
		void set(const char * str, const NodeValueType & val) {
			Node * nod = root;
			int c;
			for(; *str; ++str) {
				c = *str - nodevaloffset;
				if(c < 0 || c >= numsubnodes) throw std::runtime_error("String character out of range.");
				if(!nod->subnodes[c]) nod->subnodes[c] = new Node;
				nod = nod->subnodes[c];
			}
			nod->set(val);
		}
		void add(const char * str, const NodeValueType & val) {
			set(str, val);
		}
		void add(const std::string & str, const NodeValueType & val) {
			add(str.c_str(), val);
		}
		NodeValueType * find(const char * str) {
			Node * nod = root;
			int c;
			for(; *str; ++str) {
				c = *str - nodevaloffset;
				if(c < 0 || c >= numsubnodes) return NULL;
				if(!nod->subnodes[c]) return NULL;
				nod = nod->subnodes[c];
			}
			return nod->value;
		}
		NodeValueType * find(const std::string & str) {
			return find(str.c_str());
		}
	public:
		struct Node {
			struct Node * subnodes[numsubnodes];	// Each subnode represents a character, like a trie
			NodeValueType * value;
			void set(const NodeValueType & val) {
				value = new NodeValueType(val);
			}
			Node() {
				value = NULL;
				memset((void *)subnodes, 0, sizeof(subnodes));
			}
			Node(const NodeValueType & val) {
				set(val);
				memset((void *)subnodes, 0, sizeof(subnodes));
			}
			~Node() {
				delete value;
				for(int i = 0; i < numsubnodes; ++i) delete subnodes[i];
			}
			
		};
		
		Node * root;
		
		friend class Traverser;
	public:
	
		class Node;
	
		class Traverser {
			public:
				Traverser() {}
				Traverser(StrTree & Atree) {
					tree = &Atree;
					restart();
				}
				void setTree(StrTree & Atree) {
					tree = &Atree;
					restart();
				}
				inline void restart() {
					nod = tree->root;
					depth = 0;
				}
				inline NodeValueType * advance(char ch) {
					++depth;
					if(!nod) return NULL;
					int c = ch - nodevaloffset;
					if(c < 0 || c >= numsubnodes) {
						nod = NULL;
						return NULL;
					}
					if(!nod->subnodes[c]) {
						nod = NULL;
						return NULL;
					}
					nod = nod->subnodes[c];
					return nod->value;
				}
				inline NodeValueType * get() {
					if(!nod) return NULL;
					return nod->value;
				}
				inline unsigned int getDepth() {
					return depth;
				}
			private:
				unsigned int depth;
				StrTree<NodeValueType, numsubnodes, nodevaloffset> * tree;
				typename StrTree<NodeValueType, numsubnodes, nodevaloffset>::Node * nod;
		};
};


class EfficientStringMaker {
	public:
		EfficientStringMaker() {
			strbytes = (char *)malloc(2048);
			if(!strbytes) throw std::bad_alloc();
			cursize = 2048;
			curlen = 0;
		}
		~EfficientStringMaker() {
			free(strbytes);
		}
		inline void append(char c) {
			if(curlen >= cursize) {
				unsigned int nl = cursize * 2;
				char * n = (char *)realloc((void *)strbytes, nl);
				if(!n) throw std::bad_alloc();
				strbytes = n;
				cursize = nl;
			}
			strbytes[curlen] = c;
			++curlen;
		}
		inline void append(const char * s, unsigned int len) {
			if(curlen + len > cursize) {
				unsigned int nl = cursize * 2 + len;
				char * n = (char *)realloc((void *)strbytes, nl);
				if(!n) throw std::bad_alloc();
				strbytes = n;
				cursize = nl;
			}
			memcpy(strbytes + curlen, s, len);
			curlen += len;
		}
		inline void append(const char * s) {
			append(s, strlen(s));
		}
		inline void append(const std::string & s) {
			append(s.c_str(), s.size());
		}
		std::string getString() {
			std::string s(strbytes, curlen);
			return s;
		}
	private:
		char * strbytes;
		unsigned int curlen;
		unsigned int cursize;
};


/* Do not use when the search strings are long - memory usage blows up for
 * long search strings. */
template <int numtreebranches = 256, int charoffset = 0>
class MultiStrReplace {
	typedef StrTree<unsigned int, numtreebranches, charoffset> ReplaceTree;	// The unsigned int is an index
	public:
		MultiStrReplace() : maxsearchlen(0) {}
		void addReplacement(const std::string & search, const std::string & replace) {
			replacements.push_back(std::pair<std::string, unsigned int>(replace, 0));
			tree.add(search, replacements.size() - 1);
			if(search.size() > maxsearchlen) maxsearchlen = search.size();
		}
		void clearReplacements() {
			replacements.clear();
			tree.clear();
			maxsearchlen = 0;
		}
		unsigned int getCount(const std::string & searchstr) {
			unsigned int * idxptr = tree.find(searchstr);
			if(!idxptr) throw std::runtime_error("Unknown search string.");
			return replacements[*idxptr].second;
		}
		
		std::string processString(const std::string & str) {
			/* Essentially, this works by creating a bunch of tree traversers as a big state machine.
			 * The traversers operate at offsets, and wrap around in a circle.  The number of tree
			 * traversers is maxsearchlen.  The result string is constructed after all traversers have
			 * passed a point.  When a replacement is made, all traversers are reset. */
			/* If the max search len is zero, there are no substitutions */
			if(maxsearchlen == 0) return str;
			unsigned int num_traversers = maxsearchlen;
			unsigned int running_traversers = 0;
			unsigned int traverser_restart_ctr = 0;
			typename ReplaceTree::Traverser * traversers = new typename ReplaceTree::Traverser[num_traversers];
			for(unsigned int i = 0; i < num_traversers; ++i) traversers[i].setTree(tree);
			boost::scoped_array<typename ReplaceTree::Traverser> trav_scope_arr(traversers);	// Used only for freeing traversers
			EfficientStringMaker result;
			const char * source = str.c_str();
			unsigned int source_len = str.size();
			// Clear counts
			for(unsigned int i = 0; i < replacements.size(); ++i) replacements[i].second = 0;
			// Loop through every character on the input
			for(unsigned int i = 0; i < source_len; ++i) {
				char c = source[i];
				// If there are fewer traversers running than there are total, increase the number running
				if(running_traversers < num_traversers) {
					++running_traversers;
				} else {
					// Append the next character being pushed out of all traversers
					result.append(source[i - num_traversers]);
					// Reset the next traverser due to be reset
					traversers[traverser_restart_ctr].restart();
					if(++traverser_restart_ctr >= num_traversers) traverser_restart_ctr = 0;
				}
				// Submit the character to all running traversers
				int repl_idx = -1;
				unsigned int j;
				for(j = 0; j < running_traversers; ++j) {
					unsigned int * idxptr = traversers[j].advance(c);
					if(idxptr) {
						// Match found
						repl_idx = *idxptr;
						break;
					}
				}
				if(repl_idx >= 0) {
					unsigned int traverse_idx = j;
					// A match for replacement was found
					// Look up replacement information
					std::pair<std::string, unsigned int> & replacement = replacements[repl_idx];
					// Copy data before the replacement that hasn't been copied yet
					result.append(source + i - running_traversers + 1, running_traversers - traversers[traverse_idx].getDepth());
					// Copy replacement data
					result.append(replacement.first);
					// Increment replacement counter
					++replacement.second;
					// Reset all traversers
					running_traversers = 0;
					traverser_restart_ctr = 0;
					for(j = 0; j < num_traversers; ++j) traversers[j].restart();
				}
			}
			// Copy trailing uncopied data
			result.append(source + source_len - running_traversers, running_traversers);
			return result.getString();
		}
		
	private:
		ReplaceTree tree;
		std::vector<std::pair<std::string, unsigned int> > replacements;	// Index is replacement index.  String is replacement text.  Int is count.
		unsigned int maxsearchlen;
};


template <int numtreebranches = 256, int charoffset = 0>
class MultiStrFind {
	typedef StrTree<unsigned int, numtreebranches, charoffset> FindTree;	// The unsigned int is an index
	public:
		MultiStrFind() : maxsearchlen(0) {}
		void addSearch(const std::string & search) {
			if(searchExists(search)) return;
			searches.push_back(0);
			tree.add(search, searches.size() - 1);
			if(search.size() > maxsearchlen) maxsearchlen = search.size();
		}
		void addCategorySearch(const std::string & search, const std::string & category) {
			unsigned int catnum;
			if(searchExists(search)) return;
			if(categories.count(category)) {
				catnum = categories[category];
			} else {
				catnum = categories.size();
				categories[category] = catnum;
			}
			searches.push_back(0);
			tree.add(search, catnum);
			if(search.size() > maxsearchlen) maxsearchlen = search.size();
		}
		void addCategorySearches(std::map<std::string,std::vector<std::string> > catsrcs) {
			for(std::map<std::string,std::vector<std::string> >::iterator it = catsrcs.begin(); it != catsrcs.end(); ++it) {
				for(std::vector<std::string>::iterator it2 = it->second.begin(); it2 != it->second.end(); ++it2) {
					addCategorySearch(*it2, it->first);
				}
			}
		}
		void clearSearches() {
			searches.clear();
			tree.clear();
			maxsearchlen = 0;
		}
		unsigned int getCount(const std::string & searchstr) {
			unsigned int * idxptr = tree.find(searchstr);
			if(!idxptr) throw std::runtime_error("Unknown search string.");
			return searches[*idxptr];
		}
		unsigned int getCategoryCount(const std::string & category) {
			if(categories.count(category) == 0) return 0;
			unsigned int catnum = categories[category];
			return searches[catnum];
		}
		std::map<std::string,unsigned int> getCategoryCounts() {
			std::map<std::string,unsigned int> m;
			for(std::map<std::string,unsigned int>::iterator it = categories.begin(); it != categories.end(); ++it) {
				m[it->first] = getCategoryCount(it->first);
			}
			return m;
		}
		unsigned int getCount(int idx) {
			return searches[idx];
		}
		bool searchExists(const std::string & s) {
			unsigned int * idxptr = tree.find(s);
			if(idxptr) return true;
			return false;
		}
		
		void processString(const std::string & str) {
			/* Essentially, this works by creating a bunch of tree traversers as a big state machine.
			 * The traversers operate at offsets, and wrap around in a circle.  The number of tree
			 * traversers is maxsearchlen.  The result string is constructed after all traversers have
			 * passed a point.  When a replacement is made, all traversers are reset. */
			/* If the max search len is zero, there are no substitutions */
			if(maxsearchlen == 0) return;
			unsigned int num_traversers = maxsearchlen;
			unsigned int running_traversers = 0;
			unsigned int traverser_restart_ctr = 0;
			typename FindTree::Traverser * traversers = new typename FindTree::Traverser[num_traversers];
			for(unsigned int i = 0; i < num_traversers; ++i) traversers[i].setTree(tree);
			boost::scoped_array<typename FindTree::Traverser> trav_scope_arr(traversers);	// Used only for freeing traversers
			const char * source = str.c_str();
			unsigned int source_len = str.size();
			// Clear counts
			for(unsigned int i = 0; i < searches.size(); ++i) searches[i] = 0;
			// Loop through every character on the input
			for(unsigned int i = 0; i < source_len; ++i) {
				char c = source[i];
				// If there are fewer traversers running than there are total, increase the number running
				if(running_traversers < num_traversers) {
					++running_traversers;
				} else {
					// Reset the next traverser due to be reset
					traversers[traverser_restart_ctr].restart();
					if(++traverser_restart_ctr >= num_traversers) traverser_restart_ctr = 0;
				}
				// Submit the character to all running traversers
				int repl_idx = -1;
				unsigned int j;
				for(j = 0; j < running_traversers; ++j) {
					unsigned int * idxptr = traversers[j].advance(c);
					if(idxptr) {
						// Match found
						repl_idx = *idxptr;
						break;
					}
				}
				if(repl_idx >= 0) {
					unsigned int traverse_idx = j;
					// A match was found
					// Increment counter
					searches[repl_idx]++;
					// Reset all traversers
					running_traversers = 0;
					traverser_restart_ctr = 0;
					for(j = 0; j < num_traversers; ++j) traversers[j].restart();
				}
			}
		}
		
	private:
		FindTree tree;
		std::vector<unsigned int> searches;
		std::map<std::string,unsigned int> categories;
		unsigned int maxsearchlen;
};


}

#endif
